<?php

namespace App\Http\Controllers\User\Deposit;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\DuitkuSetting;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use App\Models\TransactionStatusHistory;
use App\Models\Wallet;
use App\Notifications\DepositReceivedNotification;
use App\Services\Payment\DuitkuService;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-to-server webhook Duitku posts to after a payment attempt —
 * NOT the browser redirect (that's DepositController::returnFromDuitku,
 * purely informational). This is the only thing allowed to flip a
 * Deposit to SUCCESS and credit a wallet, since it's the only leg of
 * this flow Duitku itself signs.
 *
 * Routed from routes/api.php (POST /api/duitku/callback — the URL
 * registered on the Duitku merchant dashboard), not routes/web.php:
 * the "api" route group is stateless by default (no session, no CSRF
 * middleware), which is exactly what a server-to-server webhook needs
 * — Duitku isn't a logged-in user of this app and carries no CSRF
 * token. Trust comes entirely from the signature check below.
 *
 * Every inbound call is persisted to payment_webhooks FIRST, before
 * signature verification — so even a rejected/malformed callback
 * leaves a record to debug from, matching this table's original design
 * intent (see its migration's docblock).
 */
class DuitkuCallbackController extends Controller
{
    public function handle(Request $request): Response
    {
        $notification = $request->only([
            'merchantCode',
            'amount',
            'merchantOrderId',
            'productDetail',
            'additionalParam',
            'paymentCode',
            'resultCode',
            'merchantUserId',
            'reference',
            'signature',
            'spUserHash',
        ]);

        Log::info('duitku-callback: received', [
            'merchantOrderId' => $notification['merchantOrderId'] ?? null,
            'resultCode' => $notification['resultCode'] ?? null,
            'reference' => $notification['reference'] ?? null,
            'amount' => $notification['amount'] ?? null,
            'ip' => $request->ip(),
        ]);

        try {
            // event_type starts generic and gets refined below once the
            // outcome is actually known (PAYMENT_SUCCESS / _PENDING /
            // _FAILED / _ERROR / _IGNORED_DUPLICATE — see
            // Superadmin\PaymentWebhookController for how these are
            // surfaced for UAT).
            $webhook = PaymentWebhook::create([
                'provider' => 'DUITKU',
                'event_type' => 'PAYMENT_CALLBACK_RECEIVED',
                'signature' => $notification['signature'] ?? null,
                'payload' => $notification,
                'processed' => false,
            ]);
        } catch (Throwable $e) {
            // Can't even persist the raw callback — nothing safe to do
            // but log loudly and let Duitku's own retry mechanism try
            // again later (500 tells it this delivery failed).
            Log::error('duitku-callback: failed to persist payment_webhooks row', [
                'merchantOrderId' => $notification['merchantOrderId'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response('Internal error', 500);
        }

        $duitku = DuitkuService::make();

        if (! $duitku->verifyCallbackSignature($notification)) {
            Log::warning('duitku-callback: invalid signature', [
                'webhook_id' => $webhook->id,
                'merchantOrderId' => $notification['merchantOrderId'] ?? null,
            ]);

            $webhook->update(['event_type' => 'PAYMENT_ERROR', 'processing_error' => 'Invalid signature']);

            // Plain 400 (not "OK") so Duitku's dashboard shows this
            // callback as failed rather than acknowledged — an invalid
            // signature is never something a retry would fix, but it
            // also should never look silently successful.
            return response('Invalid signature', 400);
        }

        $deposit = Deposit::where('reference_number', $notification['merchantOrderId'] ?? null)->first();

        if (! $deposit) {
            Log::warning('duitku-callback: no matching deposit for merchantOrderId', [
                'webhook_id' => $webhook->id,
                'merchantOrderId' => $notification['merchantOrderId'] ?? null,
            ]);

            $webhook->update([
                'event_type' => 'PAYMENT_ERROR',
                'processing_error' => 'Deposit not found for merchantOrderId: ' . ($notification['merchantOrderId'] ?? ''),
            ]);

            // Still 200/OK: the signature was genuinely valid, so this
            // really did come from Duitku — but retrying won't ever
            // make a matching Deposit appear, so there's nothing to
            // gain from Duitku resending it.
            return response('OK', 200);
        }

        $webhook->update([
            'reference_type' => Deposit::class,
            'reference_id' => $deposit->id,
        ]);

        // Nominal di callback (ikut ditandatangani Duitku) wajib sama dengan
        // total yang ditagihkan (top up + biaya QRIS kalau ada) -- kalau beda,
        // saldo TIDAK ditambahkan. Saldo yang masuk tetap $deposit->amount.
        if ((int) round((float) ($notification['amount'] ?? 0)) !== $deposit->chargedAmount()) {
            Log::warning('duitku-callback: amount mismatch, not crediting', [
                'webhook_id' => $webhook->id,
                'deposit_id' => $deposit->id,
                'callback_amount' => $notification['amount'] ?? null,
                'deposit_amount' => $deposit->chargedAmount(),
            ]);

            $webhook->update(['event_type' => 'PAYMENT_ERROR', 'processing_error' => 'Amount mismatch']);

            return response('OK', 200);
        }

        $resultCode = $notification['resultCode'] ?? null;

        // Tanda tangan callback tidak mencakup resultCode, jadi klaim "lunas"
        // wajib dipastikan langsung ke Duitku dulu (lihat DuitkuService::
        // unconfirmedPaymentReason). Sengaja di LUAR transaction: panggilan
        // jaringan tidak boleh menahan lock baris deposit. Kalau belum
        // terkonfirmasi, balas non-OK supaya Duitku mengirim ulang nanti.
        if ($resultCode === '00' && $deposit->status !== 'SUCCESS'
            && ($reason = $duitku->unconfirmedPaymentReason($deposit->reference_number, $deposit->chargedAmount()))) {
            Log::warning('duitku-callback: payment not confirmed by Duitku, not crediting', [
                'webhook_id' => $webhook->id,
                'deposit_id' => $deposit->id,
                'reason' => $reason,
            ]);

            $webhook->update(['event_type' => 'PAYMENT_ERROR', 'processing_error' => $reason]);

            return response('Payment not confirmed', 409);
        }

        Log::info('duitku-callback: matched deposit, processing', [
            'webhook_id' => $webhook->id,
            'deposit_id' => $deposit->id,
            'deposit_status_before' => $deposit->status,
            'resultCode' => $resultCode,
        ]);

        // Pembayaran yang SUDAH dikonfirmasi Duitku di atas tetap dikreditkan
        // walau deposit-nya sempat EXPIRED/FAILED (mis. user bayar tepat di
        // menit terakhir, atau tab lama membatalkan) -- uangnya benar-benar
        // sudah diterima. Satu-satunya yang tidak pernah diproses ulang
        // adalah deposit yang sudah SUCCESS.
        //
        // Idempotency: Duitku can and does resend the same callback
        // (network hiccups, no ack received in time, etc.), and two
        // deliveries can genuinely overlap in time. A deposit that's
        // already SUCCESS must never be re-processed — otherwise a
        // resent "success" callback would credit the wallet twice for
        // one real payment. The plain ->status check alone isn't
        // enough to guard against that: without a row lock, two
        // concurrent requests can both read a not-yet-SUCCESS status
        // before either commits its update. So the fetch is redone
        // HERE, inside the transaction, with lockForUpdate() — a
        // second request that arrives while the first is still
        // running blocks on this lock, and once it's granted (after
        // the first commits), MySQL's locking-read semantics guarantee
        // it re-reads the now-committed 'SUCCESS' status
        // rather than the stale snapshot from before the first request
        // started.
        try {
            $outcome = DB::transaction(function () use ($deposit, $notification, $resultCode, $webhook) {
                $locked = Deposit::whereKey($deposit->id)->lockForUpdate()->first();

                if (! $locked || $locked->status === 'SUCCESS' || ($resultCode !== '00' && $locked->status !== 'PENDING')) {
                    return ['status' => 'ignored', 'deposit' => $locked];
                }

                $deposit = $locked;
                $oldStatus = $deposit->status;
                $outcomeStatus = 'pending';

                if ($resultCode === '00') {
                    $outcomeStatus = 'success';
                    $deposit->update([
                        'status' => 'SUCCESS',
                        'payment_method' => $notification['paymentCode'] ?? $deposit->payment_method,
                        'provider_transaction_id' => $notification['reference'] ?? $deposit->provider_transaction_id,
                        'paid_at' => now(),
                        'failure_reason' => null,
                    ]);

                    PaymentTransaction::create([
                        'reference_type' => Deposit::class,
                        'reference_id' => $deposit->id,
                        'provider' => 'DUITKU',
                        'provider_transaction_id' => $notification['reference'] ?? null,
                        'payment_method' => $notification['paymentCode'] ?? null,
                        'amount' => $deposit->amount,
                        'currency' => 'IDR',
                        'status' => 'SUCCESS',
                        'response_payload' => $notification,
                        'callback_received_at' => now(),
                    ]);

                    $wallet = Wallet::where('user_id', $deposit->user_id)->lockForUpdate()->first();

                    if ($wallet) {
                        WalletLedgerService::credit(
                            $wallet,
                            (float) $deposit->amount,
                            Deposit::class,
                            $deposit->id,
                            'Duitku top-up: ' . $deposit->reference_number,
                            null,
                            'DEPOSIT'
                        );

                        // Biaya QRIS dibebankan ke customer (5 Oktober 2026): tarif
                        // disimpan saat checkout (metadata), dipotong hanya kalau
                        // pembayarannya memang QRIS (paymentCode dari Duitku).
                        $qrisFee = $this->qrisFeeFor($deposit, $notification['paymentCode'] ?? null);

                        if ($qrisFee > 0) {
                            WalletLedgerService::debit(
                                $wallet,
                                (float) $qrisFee,
                                Deposit::class,
                                $deposit->id,
                                'Biaya QRIS top-up: ' . $deposit->reference_number,
                                null,
                                'DEPOSIT_QRIS_FEE'
                            );

                            $deposit->update(['fee_amount' => $qrisFee]);
                        }
                    }

                    AuditLog::create([
                        'actor_type' => 'SYSTEM',
                        'actor_id' => null,
                        'action' => 'DUITKU_CALLBACK_SUCCESS',
                        'entity_type' => 'Deposit',
                        'entity_id' => $deposit->id,
                        'old_value' => ['status' => $oldStatus],
                        'new_value' => ['status' => 'SUCCESS', 'amount' => $deposit->amount],
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'created_at' => now(),
                    ]);
                } elseif ($resultCode === '01') {
                    $outcomeStatus = 'pending';

                    // Still pending on Duitku's side — nothing to change on
                    // our end yet, just record that we heard from them.
                    PaymentTransaction::create([
                        'reference_type' => Deposit::class,
                        'reference_id' => $deposit->id,
                        'provider' => 'DUITKU',
                        'provider_transaction_id' => $notification['reference'] ?? null,
                        'payment_method' => $notification['paymentCode'] ?? null,
                        'amount' => $deposit->amount,
                        'currency' => 'IDR',
                        'status' => 'PENDING',
                        'response_payload' => $notification,
                        'callback_received_at' => now(),
                    ]);
                } else {
                    $outcomeStatus = 'failed';

                    $deposit->update([
                        'status' => 'FAILED',
                        'failure_reason' => 'Duitku resultCode: ' . ($resultCode ?? 'null'),
                    ]);

                    PaymentTransaction::create([
                        'reference_type' => Deposit::class,
                        'reference_id' => $deposit->id,
                        'provider' => 'DUITKU',
                        'provider_transaction_id' => $notification['reference'] ?? null,
                        'payment_method' => $notification['paymentCode'] ?? null,
                        'amount' => $deposit->amount,
                        'currency' => 'IDR',
                        'status' => 'FAILED',
                        'response_payload' => $notification,
                        'callback_received_at' => now(),
                        'failure_reason' => 'Duitku resultCode: ' . ($resultCode ?? 'null'),
                    ]);

                    AuditLog::create([
                        'actor_type' => 'SYSTEM',
                        'actor_id' => null,
                        'action' => 'DUITKU_CALLBACK_FAILED',
                        'entity_type' => 'Deposit',
                        'entity_id' => $deposit->id,
                        'old_value' => ['status' => $oldStatus],
                        'new_value' => ['status' => 'FAILED', 'resultCode' => $resultCode],
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'created_at' => now(),
                    ]);
                }

                if ($deposit->fresh()->status !== $oldStatus) {
                    TransactionStatusHistory::create([
                        'entity_type' => Deposit::class,
                        'entity_id' => $deposit->id,
                        'old_status' => $oldStatus,
                        'new_status' => $deposit->fresh()->status,
                        'changed_by' => null,
                    ]);
                }

                $webhook->update([
                    'event_type' => match ($outcomeStatus) {
                        'success' => 'PAYMENT_SUCCESS',
                        'pending' => 'PAYMENT_PENDING',
                        'failed' => 'PAYMENT_FAILED',
                        default => 'PAYMENT_NOTIFICATION',
                    },
                    'processed' => true,
                    'processed_at' => now(),
                ]);

                return ['status' => $outcomeStatus, 'deposit' => $deposit];
            });
        } catch (Throwable $e) {
            // Transaction rolled back automatically — deposit keeps its
            // previous status, so this is safe for Duitku to retry. Nothing
            // partial was committed (status, PaymentTransaction, wallet
            // credit, AuditLog all-or-nothing together).
            Log::error('duitku-callback: exception while processing callback, transaction rolled back', [
                'webhook_id' => $webhook->id,
                'deposit_id' => $deposit->id,
                'resultCode' => $resultCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $webhook->update([
                'event_type' => 'PAYMENT_ERROR',
                'processing_error' => 'Exception: ' . $e->getMessage(),
            ]);

            return response('Internal error', 500);
        }

        match ($outcome['status']) {
            'ignored' => Log::info('duitku-callback: ignored — deposit already processed (duplicate/late callback)', [
                'webhook_id' => $webhook->id,
                'deposit_id' => $deposit->id,
            ]),
            'success' => Log::info('duitku-callback: deposit marked SUCCESS and wallet credited', [
                'webhook_id' => $webhook->id,
                'deposit_id' => $outcome['deposit']->id,
                'user_id' => $outcome['deposit']->user_id,
                'amount' => $outcome['deposit']->amount,
            ]),
            'pending' => Log::info('duitku-callback: deposit still PENDING per Duitku resultCode 01', [
                'webhook_id' => $webhook->id,
                'deposit_id' => $deposit->id,
            ]),
            'failed' => Log::warning('duitku-callback: deposit marked FAILED', [
                'webhook_id' => $webhook->id,
                'deposit_id' => $deposit->id,
                'resultCode' => $resultCode,
            ]),
        };

        if ($outcome['status'] === 'ignored') {
            $webhook->update([
                'event_type' => 'PAYMENT_IGNORED_DUPLICATE',
                'processed' => true,
                'processed_at' => now(),
                'processing_error' => 'Diabaikan — deposit ini sudah selesai diproses sebelumnya (callback ganda/terlambat)',
            ]);
        }

        // "Terima kasih, deposit Anda sudah diterima" — only on the
        // specific callback that actually flipped the deposit to SUCCESS
        // (never on a retried/duplicate callback, since those return
        // 'ignored' above and never reach here). Queued
        // (DepositReceivedNotification implements ShouldQueue), so this
        // call just inserts a row into `jobs` and returns immediately —
        // the actual SMTP send happens on the queue worker, not here.
        // Wrapped separately so a mail/queue failure can never turn a
        // successful, already-committed deposit+wallet-credit into a
        // failed webhook response back to Duitku.
        if ($outcome['status'] === 'success') {
            try {
                $outcome['deposit']->loadMissing('user');
                $outcome['deposit']->user?->notify(new DepositReceivedNotification($outcome['deposit']));

                Log::info('duitku-callback: deposit-received email queued', [
                    'deposit_id' => $outcome['deposit']->id,
                    'user_id' => $outcome['deposit']->user_id,
                ]);
            } catch (Throwable $e) {
                Log::error('duitku-callback: failed to queue deposit-received email', [
                    'deposit_id' => $outcome['deposit']->id,
                    'user_id' => $outcome['deposit']->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Duitku expects a plain "OK" text body (not JSON) to
        // acknowledge the callback — anything else, it treats as a
        // failure and retries.
        return response('OK', 200);
    }

    /** Biaya QRIS untuk deposit ini (0 kalau bukan QRIS / saklar mati saat checkout). */
    private function qrisFeeFor(Deposit $deposit, ?string $paymentCode): int
    {
        $meta = (array) $deposit->metadata;
        $percent = (float) ($meta['qris_fee_percent'] ?? 0);
        $qrisCode = (string) ($meta['qris_payment_code'] ?? '');

        if ($percent <= 0 || $qrisCode === '' || $paymentCode === null || strcasecmp($paymentCode, $qrisCode) !== 0) {
            return 0;
        }

        return DuitkuSetting::qrisFeeFor((int) round((float) $deposit->amount), $percent);
    }
}
