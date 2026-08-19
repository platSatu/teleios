<?php

namespace App\Console\Commands;

use App\Jobs\SendScheduledWaMessage;
use App\Models\WaMessageSchedule;
use App\Models\WaMessageScheduleLog;
use App\Models\WaMessageScheduleStep;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Runs every minute (see bootstrap/app.php's ->withSchedule()). Covers
 * all 3 WaMessageSchedule types now that "Pesan Terjadwal", "Forward &
 * Campaign Broadcast", and "Balasan Otomatis" have been merged into one
 * entity — this replaces both this command's original single-type
 * version AND the old, now-retired wa-sequences:dispatch-due:
 *
 *   - 'once'/'recurring': due once today's date falls in
 *     [date_start, date_end] and schedule_time has arrived — one send
 *     per recipient (step_order 0).
 *   - 'drip': due once, for each of the schedule's active
 *     WaMessageScheduleStep rows, date_start + step.delay_days lands on
 *     today and schedule_time has arrived — one send per (recipient,
 *     step).
 *
 * Either way, "due" only turns into a dispatched job the moment this
 * command successfully claims a brand-new App\Models\WaMessageScheduleLog
 * row for (schedule, recipient, day, step) — see claimAndDispatch(),
 * which locks/creates that row the same race-safe way
 * App\Services\PackageLimitService::lockOrCreateUsage() claims a usage
 * counter row: a locked lookup first, then a create guarded by a catch
 * on that table's unique index (wa_message_schedule_logs_unique_per_day
 * — see that migration), so two overlapping ticks (or two overlapping
 * runs of this command) racing for the exact same combination can never
 * both win. That's what makes every branch below idempotent across
 * ticks: work that's already claimed — 'pending', 'sent', or 'failed' —
 * is never re-dispatched by a later run. A 'failed' outcome is final for
 * that (recipient, day, step) — the job's own tries/backoff covers
 * retries within one attempt, and editing a schedule
 * (MessageScheduleController::update()) clears today's pending/failed
 * rows to allow a same-day retry after a fix.
 *
 * Deliberately thin either way: this command only finds and *claims*
 * work — SendScheduledWaMessage does the actual sending.
 */
class DispatchDueWaMessageSchedules extends Command
{
    protected $signature = 'wa-schedules:dispatch-due';

    protected $description = 'Enqueue due WhatsApp schedules (once/recurring/drip, per recipient) for sending';

    /**
     * Randomized gap (in seconds) between one recipient's send and the
     * next WITHIN the same schedule (i.e. the same device) — this is an
     * anti-ban measure. Before this, every recipient of a schedule was
     * dispatched to the queue in the same instant with zero spacing, so
     * a queue worker would fire them at whatever the Go backend/WhatsApp
     * round-trip allows, back-to-back — a classic pattern WhatsApp's own
     * anti-spam detection flags and bans numbers for (a real human never
     * sends a burst of near-identical messages to unrelated numbers with
     * ~0ms between them). A random range instead of a fixed interval on
     * purpose: a perfectly uniform gap is itself a detectable automation
     * signature, so this deliberately jitters like an inconsistent human
     * sending pace would.
     *
     * Different schedules (which each send through their own
     * device_id — see WaMessageSchedule) are NOT staggered against each
     * other, only recipients within the same schedule — the risk this
     * guards against is one device blasting messages too fast, not the
     * system's aggregate throughput across many devices.
     */
    private const MIN_SEND_GAP_SECONDS = 5;

    private const MAX_SEND_GAP_SECONDS = 15;

    public function handle(): int
    {
        $today = now()->toDateString();

        $dispatched = $this->dispatchOnceAndRecurring($today) + $this->dispatchDrip($today);

        $this->info("Dispatched {$dispatched} due schedule-recipient(s) for {$today}.");

        return self::SUCCESS;
    }

    private function dispatchOnceAndRecurring(string $today): int
    {
        $due = WaMessageSchedule::query()
            ->whereIn('type', ['once', 'recurring'])
            ->where('status', 'active')
            ->where('date_start', '<=', $today)
            ->where('date_end', '>=', $today)
            // schedule_time is a TIME column — TIMESTAMP(today, time)
            // combines it with today's date for a single "has this
            // moment arrived yet" comparison against now().
            ->whereRaw('TIMESTAMP(?, schedule_time) <= ?', [$today, now()])
            ->get(['id', 'recipients']);

        $count = 0;

        foreach ($due as $schedule) {
            // Reset per schedule — each schedule sends through its own
            // device, so the stagger only needs to space out THIS
            // schedule's own recipient list, not run continuously across
            // every schedule this tick happens to find due.
            $delaySeconds = 0;

            foreach ($schedule->recipientKeys() as $recipientKey) {
                $dispatched = $this->claimAndDispatch($schedule->id, $recipientKey, $today, 0, $delaySeconds);
                $count += $dispatched;

                // Only advance the clock for recipients that actually got
                // a fresh job queued — a recipient claimAndDispatch skips
                // (already claimed by an earlier tick) shouldn't eat into
                // the pacing of the ones still left to send.
                if ($dispatched) {
                    $delaySeconds += random_int(self::MIN_SEND_GAP_SECONDS, self::MAX_SEND_GAP_SECONDS);
                }
            }
        }

        return $count;
    }

    private function dispatchDrip(string $today): int
    {
        // Narrowed to schedules whose time-of-day has passed today —
        // still has to loop each active step in PHP below to find the
        // ones whose date_start + delay_days lands on today specifically,
        // since that math isn't something worth pushing into raw SQL
        // across a joined table for what's normally a handful of rows.
        $schedules = WaMessageSchedule::query()
            ->where('type', 'drip')
            ->where('status', 'active')
            ->whereRaw('TIMESTAMP(?, schedule_time) <= ?', [$today, now()])
            ->with(['steps' => fn ($q) => $q->where('status', 'active')])
            ->get(['id', 'recipients', 'date_start', 'schedule_time']);

        $count = 0;

        foreach ($schedules as $schedule) {
            foreach ($schedule->steps as $step) {
                /** @var WaMessageScheduleStep $step */
                if ($step->dueDate($schedule->date_start)->toDateString() !== $today) {
                    continue;
                }

                // Reset per (schedule, step) — same reasoning as above:
                // this step's own recipient batch is one send run on one
                // device, staggered independently of every other step.
                $delaySeconds = 0;

                foreach ($schedule->recipientKeys() as $recipientKey) {
                    $dispatched = $this->claimAndDispatch($schedule->id, $recipientKey, $today, $step->sequence_order, $delaySeconds);
                    $count += $dispatched;

                    if ($dispatched) {
                        $delaySeconds += random_int(self::MIN_SEND_GAP_SECONDS, self::MAX_SEND_GAP_SECONDS);
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Claims (schedule, recipient, day, step) by inserting its
     * WaMessageScheduleLog row — returns whether THIS call was the one
     * that created it (0/1 dispatched), never re-claiming a row an
     * earlier tick already owns.
     *
     * Race-safe the same way App\Services\PackageLimitService::
     * lockOrCreateUsage() is: a locked lookup first (lockForUpdate()
     * only helps once the row exists, so this alone can't stop two
     * concurrent callers both racing to insert the FIRST row for a
     * brand-new combination), then a create wrapped in a catch for the
     * QueryException that the table's own unique index
     * (wa_message_schedule_logs_unique_per_day) throws when a second,
     * simultaneous caller loses that race — re-fetched, not re-thrown,
     * since "someone else already claimed it a moment ago" is exactly
     * the outcome this method exists to detect, not an error. Plain
     * firstOrCreate() used to be used here instead, which has this exact
     * gap: two overlapping runs of this command (e.g. a slow tick still
     * running when the next minute's cron fires) could both read "no row
     * yet" and both try to insert, and without this catch the loser's
     * INSERT would bubble up as an uncaught QueryException and crash the
     * whole command run — including every other due schedule it hadn't
     * gotten to yet.
     */
    private function claimAndDispatch(string $scheduleId, string $recipientKey, string $today, int $stepOrder, int $delaySeconds): int
    {
        $claimed = DB::transaction(function () use ($scheduleId, $recipientKey, $today, $stepOrder) {
            $find = fn () => WaMessageScheduleLog::where('wa_message_schedule_id', $scheduleId)
                ->where('recipient_key', $recipientKey)
                ->where('send_date', $today)
                ->where('step_order', $stepOrder)
                ->lockForUpdate()
                ->first();

            if ($find()) {
                return false;
            }

            try {
                WaMessageScheduleLog::create([
                    'wa_message_schedule_id' => $scheduleId,
                    'recipient_key' => $recipientKey,
                    'send_date' => $today,
                    'step_order' => $stepOrder,
                    'status' => 'pending',
                ]);

                return true;
            } catch (QueryException $e) {
                // Lost the race for this exact combination — another
                // overlapping run of this command claimed it a moment
                // ago and already committed. Confirm their row exists
                // (rather than assuming from the exception alone) and
                // report "not claimed by us"; a genuinely different
                // failure (e.g. a real DB outage) still surfaces.
                if ($find()) {
                    return false;
                }

                throw $e;
            }
        });

        if (! $claimed) {
            return 0;
        }

        $pending = SendScheduledWaMessage::dispatch($scheduleId, $recipientKey, $today, $stepOrder);

        // First recipient (delaySeconds === 0) goes out on the queue's
        // normal schedule — no ->delay() call at all, rather than
        // ->delay(now()), so it isn't held back waiting on a delayed-job
        // mechanism for no reason.
        if ($delaySeconds > 0) {
            $pending->delay(now()->addSeconds($delaySeconds));
        }

        return 1;
    }
}
