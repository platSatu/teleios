<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use App\Notifications\PackageExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Runs once a day (see bootstrap/app.php's ->withSchedule()). Queues one
 * PackageExpiringNotification email per active Voucher that's landing on
 * one of 4 milestones today: H-7, H-3, H-1, or H (valid_until itself).
 *
 * "H-N" is computed off calendar dates only (valid_until's date minus
 * today's date), not the time-of-day — so whatever hour this command
 * actually runs at, a voucher expiring at any time on a given date is
 * still treated as reaching each milestone on that whole day.
 *
 * Idempotent per voucher+milestone via the reminder_{7,3,1,0}d_sent_at
 * columns (see migration 2026_08_05_090000_add_expiry_reminder_columns_to_vouchers_table):
 * once a column is stamped, that milestone is never re-sent for this
 * voucher even if the command runs again the same day or is re-run
 * manually.
 *
 * "Sudah diperpanjang, tidak perlu dikirim lagi" (per the request) is
 * handled by isSuperseded(): a renewal buys + redeems a NEW voucher row
 * that chains its valid_from off the old one's valid_until (see
 * Dashboard\VoucherRedeemController's $previousActive logic) — the OLD
 * voucher row's own status stays 'active' and its valid_until is never
 * rewritten. So "already renewed" is detected by asking, at send time:
 * does this user already have another 'active' voucher for the SAME
 * package whose valid_until is LATER than this one? If yes, this
 * voucher's window has been superseded and its reminder is skipped —
 * the newer voucher will get its own H-7/H-3/H-1/H0 reminders in turn,
 * off its own (later) valid_until.
 */
class SendPackageExpiryReminders extends Command
{
    protected $signature = 'package:send-expiry-reminders';

    protected $description = 'Queue H-7/H-3/H-1/H0 package expiry reminder emails for active vouchers';

    /**
     * Days-left => the vouchers column that tracks whether that specific
     * milestone has already been sent for a given voucher.
     *
     * @var array<int, string>
     */
    private const MILESTONE_COLUMNS = [
        7 => 'reminder_7d_sent_at',
        3 => 'reminder_3d_sent_at',
        1 => 'reminder_1d_sent_at',
        0 => 'reminder_0d_sent_at',
    ];

    public function handle(): int
    {
        $today = Carbon::today();

        // Narrow to vouchers that could POSSIBLY hit a milestone today
        // (valid_until anywhere from today through 7 days out) before
        // doing the exact day-diff check per row below — keeps this from
        // scanning every active voucher in the table regardless of how
        // far off its expiry is.
        $candidates = Voucher::query()
            ->where('status', 'active')
            ->whereNotNull('valid_until')
            ->whereBetween('valid_until', [
                $today->copy()->startOfDay(),
                $today->copy()->addDays(7)->endOfDay(),
            ])
            ->with(['user', 'package'])
            ->get();

        $queued = 0;
        $skippedSuperseded = 0;

        foreach ($candidates as $voucher) {
            if (! $voucher->user?->email) {
                continue;
            }

            $daysLeft = $this->daysUntil($today, $voucher->valid_until);

            // Only exactly 7/3/1/0 days out counts as a milestone — a
            // voucher sitting at, say, 5 days out simply isn't due for
            // any reminder yet and is left for a later run.
            if (! array_key_exists($daysLeft, self::MILESTONE_COLUMNS)) {
                continue;
            }

            $column = self::MILESTONE_COLUMNS[$daysLeft];

            if ($voucher->{$column} !== null) {
                continue;
            }

            if ($this->isSuperseded($voucher)) {
                $skippedSuperseded++;

                continue;
            }

            $voucher->user->notify(new PackageExpiringNotification($voucher, $daysLeft));

            // Stamped immediately (not inside the queued job) so a
            // second run later today — or a slow queue worker — can
            // never double-send this same milestone.
            $voucher->forceFill([$column => now()])->save();

            $queued++;
        }

        $this->info("Queued {$queued} package expiry reminder email(s), skipped {$skippedSuperseded} already-renewed voucher(s).");

        return self::SUCCESS;
    }

    private function daysUntil(Carbon $today, Carbon $validUntil): int
    {
        $expiryDate = $validUntil->copy()->startOfDay();

        return (int) round(($expiryDate->timestamp - $today->timestamp) / 86400);
    }

    private function isSuperseded(Voucher $voucher): bool
    {
        return Voucher::where('user_id', $voucher->user_id)
            ->where('package_id', $voucher->package_id)
            ->where('id', '!=', $voucher->id)
            ->where('status', 'active')
            ->where('valid_until', '>', $voucher->valid_until)
            ->exists();
    }
}
