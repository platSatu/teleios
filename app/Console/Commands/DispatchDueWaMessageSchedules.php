<?php

namespace App\Console\Commands;

use App\Jobs\SendScheduledWaMessage;
use App\Models\WaMessageSchedule;
use App\Models\WaMessageScheduleLog;
use App\Models\WaMessageScheduleStep;
use Illuminate\Console\Command;

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
 * row (firstOrCreate against that table's unique index on
 * (schedule, recipient, day, step) — see that migration). That's what
 * makes every branch below idempotent across ticks: work that's already
 * claimed — 'pending', 'sent', or 'failed' — is never re-dispatched by a
 * later run. A 'failed' outcome is final for that (recipient, day, step)
 * — the job's own tries/backoff covers retries within one attempt, and
 * editing a schedule (MessageScheduleController::update()) clears
 * today's pending/failed rows to allow a same-day retry after a fix.
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

    private function claimAndDispatch(string $scheduleId, string $recipientKey, string $today, int $stepOrder, int $delaySeconds): int
    {
        $log = WaMessageScheduleLog::firstOrCreate(
            [
                'wa_message_schedule_id' => $scheduleId,
                'recipient_key' => $recipientKey,
                'send_date' => $today,
                'step_order' => $stepOrder,
            ],
            ['status' => 'pending']
        );

        if (! $log->wasRecentlyCreated) {
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
