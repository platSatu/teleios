<?php

namespace App\Http\Controllers\Tagihan;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use App\Models\TagihanPenerima;
use App\Models\TransactionStatusHistory;
use App\Services\Payment\DuitkuService;
use App\Services\Wallet\WalletLedgerService;
use App\Services\Wallet\WalletProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-to-server webhook Duitku posts ke sini KHUSUS untuk pembayaran
 * Tagihan -- routed dari routes/api.php (POST /api/tagihan/duitku/
 * callback), terpisah dari App\Http\Controllers\User\Deposit\
 * DuitkuCallbackController supaya lookup-nya tidak pernah campur antara
 * TagihanPenerima (prefix order_number "TGH-") dan Deposit (prefix
 * reference_number "DEP-"). Strukturnya sengaja disamakan persis
 * dengan DuitkuCallbackController -- persist webhook dulu SEBELUM
 * verifikasi signature, idempotency via lockForUpdate() di dalam
 * transaction, dst -- lihat docblock kelas itu untuk penjelasan
 * lengkap tiap langkahnya.
 *
 * Ini satu-satunya tempat yang boleh mengubah TagihanPenerima ke status
 * 'lunas' -- App\Http\Controllers\Tagihan\Public\TagihanPublicController
 * (yang menciptakan invoice-nya) tidak pernah menandai lunas sendiri,
 * persis prinsip Deposit di atas.
 *
 * BELUM mengirim notifikasi WhatsApp/email apa pun begitu lunas --
 * sesuai instruksi eksplisit "jangan sambungkan dulu ya dengan
 * whatsapp" saat fitur ini didiskusikan. App\Models\TagihanReminderLog
 * sudah disiapkan strukturnya untuk itu nanti.
 *
 * Update 22 September 2026 (fitur Saldo Branch/Tarik Saldo) -- begitu
 * resultCode '00' (lunas), SEKARANG juga meng-kredit App\Models\Wallet
 * milik BranchOffice tempat Tagihan ini dibuat (dapat/dibuat lewat
 * App\Services\Wallet\WalletProvisioningService::forBranch(), lalu
 * dikredit lewat App\Services\Wallet\WalletLedgerService::credit() --
 * di DALAM transaction lockForUpdate() yang sama persis dengan update
 * status lunas, jadi tidak mungkin lunas tercatat tanpa saldo ikut
 * bertambah atau sebaliknya). Wallet ini yang nanti jadi sumber dana
 * fitur "Transfer Fee" bulanan ke pengajar dan "Tarik Saldo" Company.
 */
class TagihanDuitkuCallbackController extends Controller
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

        Log::info('tagihan-duitku-callback: received', [
            'merchantOrderId' => $notification['merchantOrderId'] ?? null,
            'resultCode' => $notification['resultCode'] ?? null,
            'reference' => $notification['reference'] ?? null,
            'amount' => $notification['amount'] ?? null,
            'ip' => $request->ip(),
        ]);

        try {
            $webhook = PaymentWebhook::create([
                'provider' => 'DUITKU',
                'event_type' => 'TAGIHAN_PAYMENT_CALLBACK_RECEIVED',
                'signature' => $notification['signature'] ?? null,
                'payload' => $notification,
                'processed' => false,
            ]);
        } catch (Throwable $e) {
            Log::error('tagihan-duitku-callback: failed to persist payment_webhooks row', [
                'merchantOrderId' => $notification['merchantOrderId'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response('Internal error', 500);
        }

        $duitku = DuitkuService::make();

        if (! $duitku->verifyCallbackSignature($notification)) {
            Log::warning('tagihan-duitku-callback: invalid signature', [
                'webhook_id' => $webhook->id,
                'merchantOrderId' => $notification['merchantOrderId'] ?? null,
            ]);

            $webhook->update(['event_type' => 'TAGIHAN_PAYMENT_ERROR', 'processing_error' => 'Invalid signature']);

            return response('Invalid signature', 400);
        }

        $penerima = TagihanPenerima::where('order_number', $notification['merchantOrderId'] ?? null)->first();

        if (! $penerima) {
            Log::warning('tagihan-duitku-callback: no matching tagihan_penerima for merchantOrderId', [
                'webhook_id' => $webhook->id,
                'merchantOrderId' => $notification['merchantOrderId'] ?? null,
            ]);

            $webhook->update([
                'event_type' => 'TAGIHAN_PAYMENT_ERROR',
                'processing_error' => 'TagihanPenerima not found for merchantOrderId: '.($notification['merchantOrderId'] ?? ''),
            ]);

            return response('OK', 200);
        }

        $webhook->update([
            'reference_type' => TagihanPenerima::class,
            'reference_id' => $penerima->id,
        ]);

        $resultCode = $notification['resultCode'] ?? null;

        Log::info('tagihan-duitku-callback: matched tagihan_penerima, processing', [
            'webhook_id' => $webhook->id,
            'tagihan_penerima_id' => $penerima->id,
            'status_before' => $penerima->status,
            'resultCode' => $resultCode,
        ]);

        try {
            $outcome = DB::transaction(function () use ($penerima, $notification, $resultCode, $webhook) {
                $locked = TagihanPenerima::whereKey($penerima->id)->lockForUpdate()->first();

                if (! $locked || $locked->status !== TagihanPenerima::STATUS_BELUM_BAYAR) {
                    return ['status' => 'ignored', 'penerima' => $locked];
                }

                $penerima = $locked;
                $oldStatus = $penerima->status;
                $outcomeStatus = 'pending';

                if ($resultCode === '00') {
                    $outcomeStatus = 'success';

                    $penerima->update([
                        'status' => TagihanPenerima::STATUS_LUNAS,
                        'paid_at' => now(),
                    ]);

                    PaymentTransaction::create([
                        'reference_type' => TagihanPenerima::class,
                        'reference_id' => $penerima->id,
                        'provider' => 'DUITKU',
                        'provider_transaction_id' => $notification['reference'] ?? null,
                        'payment_method' => $notification['paymentCode'] ?? null,
                        'amount' => (float) $penerima->amount + (float) $penerima->denda_amount,
                        'currency' => 'IDR',
                        'status' => 'SUCCESS',
                        'response_payload' => $notification,
                        'callback_received_at' => now(),
                    ]);

                    AuditLog::create([
                        'actor_type' => 'SYSTEM',
                        'actor_id' => null,
                        'action' => 'TAGIHAN_DUITKU_CALLBACK_SUCCESS',
                        'entity_type' => 'TagihanPenerima',
                        'entity_id' => $penerima->id,
                        'old_value' => ['status' => $oldStatus],
                        'new_value' => ['status' => TagihanPenerima::STATUS_LUNAS],
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'created_at' => now(),
                    ]);

                    // Fitur Saldo Branch (22 September 2026) -- lihat
                    // docblock class di atas. branch_office_id WAJIB ada
                    // di TagihanPenerima (lihat migration create_tagihan_
                    // penerima_table.php), jadi tidak perlu guard null di
                    // sini seperti attachment_path yang memang nullable.
                    $totalDibayar = (float) $penerima->amount + (float) $penerima->denda_amount;

                    // Guard >0 -- WalletLedgerService::credit() melempar
                    // RuntimeException untuk amount<=0, yang kalau tidak
                    // dijaga di sini akan ikut membatalkan (rollback)
                    // penandaan lunas di atas walau resultCode-nya jelas
                    // sukses. Nominal 0 seharusnya tidak pernah terjadi
                    // untuk Tagihan asli, tapi lebih aman diam-diam
                    // dilewati daripada menggagalkan seluruh callback.
                    if ($totalDibayar > 0) {
                        $branchWallet = WalletProvisioningService::forBranch($penerima->branch_office_id);

                        WalletLedgerService::credit(
                            $branchWallet,
                            $totalDibayar,
                            TagihanPenerima::class,
                            $penerima->id,
                            'Pembayaran Tagihan lunas — '.($penerima->order_number ?: $penerima->id),
                            null,
                            'TAGIHAN_PAYMENT',
                        );
                    }
                } elseif ($resultCode === '01') {
                    $outcomeStatus = 'pending';

                    PaymentTransaction::create([
                        'reference_type' => TagihanPenerima::class,
                        'reference_id' => $penerima->id,
                        'provider' => 'DUITKU',
                        'provider_transaction_id' => $notification['reference'] ?? null,
                        'payment_method' => $notification['paymentCode'] ?? null,
                        'amount' => (float) $penerima->amount + (float) $penerima->denda_amount,
                        'currency' => 'IDR',
                        'status' => 'PENDING',
                        'response_payload' => $notification,
                        'callback_received_at' => now(),
                    ]);
                } else {
                    // Gagal di sisi Duitku TIDAK menandai TagihanPenerima
                    // 'kadaluarsa' -- pelanggan masih boleh coba bayar
                    // lagi selama expires_at belum lewat, beda dari
                    // Deposit yang langsung FAILED. Cukup dicatat di
                    // PaymentTransaction, status tetap belum_bayar.
                    $outcomeStatus = 'failed';

                    PaymentTransaction::create([
                        'reference_type' => TagihanPenerima::class,
                        'reference_id' => $penerima->id,
                        'provider' => 'DUITKU',
                        'provider_transaction_id' => $notification['reference'] ?? null,
                        'payment_method' => $notification['paymentCode'] ?? null,
                        'amount' => (float) $penerima->amount + (float) $penerima->denda_amount,
                        'currency' => 'IDR',
                        'status' => 'FAILED',
                        'response_payload' => $notification,
                        'callback_received_at' => now(),
                        'failure_reason' => 'Duitku resultCode: '.($resultCode ?? 'null'),
                    ]);
                }

                if ($penerima->fresh()->status !== $oldStatus) {
                    TransactionStatusHistory::create([
                        'entity_type' => TagihanPenerima::class,
                        'entity_id' => $penerima->id,
                        'old_status' => $oldStatus,
                        'new_status' => $penerima->fresh()->status,
                        'changed_by' => null,
                    ]);
                }

                $webhook->update([
                    'event_type' => match ($outcomeStatus) {
                        'success' => 'TAGIHAN_PAYMENT_SUCCESS',
                        'pending' => 'TAGIHAN_PAYMENT_PENDING',
                        'failed' => 'TAGIHAN_PAYMENT_FAILED',
                        default => 'TAGIHAN_PAYMENT_NOTIFICATION',
                    },
                    'processed' => true,
                    'processed_at' => now(),
                ]);

                return ['status' => $outcomeStatus, 'penerima' => $penerima];
            });
        } catch (Throwable $e) {
            Log::error('tagihan-duitku-callback: exception while processing callback, transaction rolled back', [
                'webhook_id' => $webhook->id,
                'tagihan_penerima_id' => $penerima->id,
                'resultCode' => $resultCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $webhook->update([
                'event_type' => 'TAGIHAN_PAYMENT_ERROR',
                'processing_error' => 'Exception: '.$e->getMessage(),
            ]);

            return response('Internal error', 500);
        }

        if ($outcome['status'] === 'ignored') {
            Log::info('tagihan-duitku-callback: ignored — tagihan_penerima already left belum_bayar (duplicate/late callback)', [
                'webhook_id' => $webhook->id,
                'tagihan_penerima_id' => $penerima->id,
            ]);

            $webhook->update([
                'event_type' => 'TAGIHAN_PAYMENT_IGNORED_DUPLICATE',
                'processed' => true,
                'processed_at' => now(),
                'processing_error' => 'Ignored — tagihan_penerima already left belum_bayar (duplicate/late callback)',
            ]);
        }

        return response('OK', 200);
    }
}
