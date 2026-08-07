<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\PaymentWebhook;
use App\Models\TransactionStatusHistory;
use App\Notifications\DepositExpiredNotification;
use App\Notifications\DepositPaymentReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs every minute (see bootstrap/app.php's ->withSchedule()) — has to
 * be minute-granular, not daily like package:send-expiry-reminders,
 * because a Duitku payment window is typically 60 minutes (see
 * services.duitku.expiry_minutes), not days.
 *
 * Duitku never pushes a native "expired" callback (confirmed against
 * DuitkuCallbackController — resultCode is only ever '00' success, '01'
 * pending, or an explicit failure code). So both halves of the
 * requirement below are driven entirely by this app's own clock against
 * Deposit::expires_at, which DepositController::proceedToDuitku() stamps
 * the moment a deposit is actually invoiced with Duitku (using the same
 * services.duitku.expiry_minutes value sent to Duitku itself, so this
 * check never disagrees with what Duitku is enforcing on its side).
 *
 * Two independent jobs each run:
 *
 *   1. Reminder — "segera selesaikan pembayaran Anda", sent once per
 *      deposit while it's still PENDING, once the remaining time drops
 *      under a lead window (min(15, half the total expiry window)
 *      minutes before expires_at). Idempotent via reminder_sent_at.
 *
 *   2. Expiry — once expires_at has actually passed and the deposit is
 *      still PENDING, flips it to EXPIRED (a deposit can only ever
 *      reach EXPIRED through this path, never SUCCESS/FAILED — those
 *      stay exclusively driven by DuitkuCallbackController). Guarded by
 *      the same lockForUpdate()-inside-a-transaction pattern used by
 *      the Duitku webhook handler, so a deposit that the webhook
 *      happens to be crediting/failing in the same instant can never
 *      also be marked EXPIRED out from under it — whichever transaction
 *      commits first wins, and the other sees a non-PENDING status once
 *      its lock is granted and simply skips the row.
 *
 * Every EXPIRED transition also writes a synthetic payment_webhooks row
 * (event_type PAYMENT_EXPIRED) purely for UAT/audit visibility — see
 * Superadmin\PaymentWebhookController — even though it didn't originate
 * from an actual inbound Duitku callback, since the requirement is to
 * have every "callback ketika expired" scenario visible in one place
 * alongside the real success/pending/failed callbacks Duitku does send.
 */
class ProcessDepositExpiry extends Command
{
    protected $signature = 'deposit:process-expiry';

    protected $description = 'Send payment reminders and expire PENDING Duitku deposits whose payment window has passed';

    public function handle(): int
    {
        $reminded = $this->sendReminders();
        $expired = $this->expireOverdueDeposits();

        $this->info("Reminder email(s) queued: {$reminded}. Deposit(s) expired: {$expired}.");

        return self::SUCCESS;
    }

    /**
     * Lead time before expires_at at which the reminder fires — 15
     * minutes by default, but never more than half of the total
     * expiry window (so a short custom expiry_minutes config still
     * gets a reminder that lands meaningfully inside the window rather
     * than firing before the invoice even existed).
     */
    private function reminderLeadMinutes(): int
    {
        $expiryMinutes = (int) config('services.duitku.expiry_minutes', 60);

        return min(15, max(1, intdiv($expiryMinutes, 2)));
    }

    private function sendReminders(): int
    {
        $leadMinutes = $this->reminderLeadMinutes();
        $now = now();

        $candidates = Deposit::query()
            ->where('status', 'PENDING')
            ->whereNotNull('expires_at')
            ->whereNull('reminder_sent_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addMinutes($leadMinutes))
            ->with('user')
            ->get();

        $queued = 0;

        foreach ($candidates as $deposit) {
            if (! $deposit->user?->email) {
                continue;
            }

            try {
                DB::transaction(function () use ($deposit) {
                    $locked = Deposit::whereKey($deposit->id)->lockForUpdate()->first();

                    if (! $locked || $locked->status !== 'PENDING' || $locked->reminder_sent_at !== null) {
                        return;
                    }

                    $locked->forceFill(['reminder_sent_at' => now()])->save();

                    $locked->loadMissing('user');
                    $locked->user?->notify(new DepositPaymentReminderNotification($locked));
                });

                $queued++;
            } catch (Throwable $e) {
                Log::error('deposit:process-expiry: failed to queue reminder', [
                    'deposit_id' => $deposit->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $queued;
    }

    private function expireOverdueDeposits(): int
    {
        $now = now();

        $candidates = Deposit::query()
            ->where('status', 'PENDING')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        $expiredCount = 0;

        foreach ($candidates as $deposit) {
            try {
                $result = DB::transaction(function () use ($deposit) {
                    $locked = Deposit::whereKey($deposit->id)->lockForUpdate()->first();

                    if (! $locked || $locked->status !== 'PENDING') {
                        return null;
                    }

                    $oldStatus = $locked->status;

                    $locked->update([
                        'status' => 'EXPIRED',
                        'failure_reason' => 'Waktu pembayaran Duitku habis tanpa konfirmasi (expires_at terlampaui).',
                    ]);

                    TransactionStatusHistory::create([
                        'entity_type' => Deposit::class,
                        'entity_id' => $locked->id,
                        'old_status' => $oldStatus,
                        'new_status' => 'EXPIRED',
                        'changed_by' => null,
                    ]);

                    AuditLog::create([
                        'actor_type' => 'SYSTEM',
                        'actor_id' => null,
                        'action' => 'DEPOSIT_EXPIRED',
                        'entity_type' => 'Deposit',
                        'entity_id' => $locked->id,
                        'old_value' => ['status' => $oldStatus],
                        'new_value' => ['status' => 'EXPIRED'],
                        'ip_address' => null,
                        'user_agent' => 'deposit:process-expiry (scheduled command)',
                        'created_at' => now(),
                    ]);

                    // Synthetic "callback" row for UAT visibility — not
                    // an actual inbound Duitku payload (there's no
                    // signature, since Duitku sent nothing here), but
                    // shaped the same way so it shows up next to real
                    // callbacks in Superadmin\PaymentWebhookController.
                    PaymentWebhook::create([
                        'provider' => 'DUITKU',
                        'event_type' => 'PAYMENT_EXPIRED',
                        'signature' => null,
                        'payload' => [
                            'source' => 'deposit:process-expiry',
                            'merchantOrderId' => $locked->reference_number,
                            'deposit_id' => $locked->id,
                            'amount' => (string) $locked->amount,
                            'status_before' => $oldStatus,
                            'status_after' => 'EXPIRED',
                            'expires_at' => optional($locked->expires_at)->toIso8601String(),
                            'expired_at' => now()->toIso8601String(),
                        ],
                        'processed' => true,
                        'processed_at' => now(),
                        'reference_type' => Deposit::class,
                        'reference_id' => $locked->id,
                    ]);

                    return $locked;
                });

                if (! $result) {
                    continue;
                }

                $expiredCount++;

                try {
                    $result->loadMissing('user');
                    $result->user?->notify(new DepositExpiredNotification($result));
                } catch (Throwable $e) {
                    Log::error('deposit:process-expiry: failed to queue expired email', [
                        'deposit_id' => $result->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('deposit:process-expiry: failed to expire deposit', [
                    'deposit_id' => $deposit->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $expiredCount;
    }
}
