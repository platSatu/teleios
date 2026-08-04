<?php

namespace App\Http\Controllers\User\Deposit;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use App\Models\TransactionStatusHistory;
use App\Models\Wallet;
use App\Services\Payment\DuitkuService;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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

        $webhook = PaymentWebhook::create([
            'provider' => 'DUITKU',
            'event_type' => 'PAYMENT_NOTIFICATION',
            'signature' => $notification['signature'] ?? null,
            'payload' => $notification,
            'processed' => false,
        ]);

        $duitku = DuitkuService::make();

        if (! $duitku->verifyCallbackSignature($notification)) {
            $webhook->update(['processing_error' => 'Invalid signature']);

            // Plain 400 (not "OK") so Duitku's dashboard shows this
            // callback as failed rather than acknowledged — an invalid
            // signature is never something a retry would fix, but it
            // also should never look silently successful.
            return response('Invalid signature', 400);
        }

        $deposit = Deposit::where('reference_number', $notification['merchantOrderId'] ?? null)->first();

        if (! $deposit) {
            $webhook->update(['processing_error' => 'Deposit not found for merchantOrderId: ' . ($notification['merchantOrderId'] ?? '')]);

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

        $resultCode = $notification['resultCode'] ?? null;

        // Idempotency: Duitku can and does resend the same callback
        // (network hiccups, no ack received in time, etc.), and two
        // deliveries can genuinely overlap in time. A deposit that's
        // already left PENDING must never be re-processed — otherwise a
        // resent "success" callback would credit the wallet twice for
        // one real payment. The plain ->status check alone isn't
        // enough to guard against that: without a row lock, two
        // concurrent requests can both read status === 'PENDING'
        // before either commits its update. So the fetch is redone
        // HERE, inside the transaction, with lockForUpdate() — a
        // second request that arrives while the first is still
        // running blocks on this lock, and once it's granted (after
        // the first commits), MySQL's locking-read semantics guarantee
        // it re-reads the now-committed 'SUCCESS'/'FAILED' status
        // rather than the stale snapshot from before the first request
        // started.
        $ignored = DB::transaction(function () use ($deposit, $notification, $resultCode, $webhook) {
            $locked = Deposit::whereKey($deposit->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'PENDING') {
                return true;
            }

            $deposit = $locked;
            $oldStatus = $deposit->status;

            if ($resultCode === '00') {
                $deposit->update([
                    'status' => 'SUCCESS',
                    'payment_method' => $notification['paymentCode'] ?? $deposit->payment_method,
                    'provider_transaction_id' => $notification['reference'] ?? $deposit->provider_transaction_id,
                    'paid_at' => now(),
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
                'processed' => true,
                'processed_at' => now(),
            ]);

            return false;
        });

        if ($ignored) {
            $webhook->update([
                'processed' => true,
                'processed_at' => now(),
                'processing_error' => 'Ignored — deposit already left PENDING (duplicate/late callback)',
            ]);
        }

        // Duitku expects a plain "OK" text body (not JSON) to
        // acknowledge the callback — anything else, it treats as a
        // failure and retries.
        return response('OK', 200);
    }
}
