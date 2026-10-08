<?php

namespace App\Services\PaymentGateway;

use App\Jobs\SendPgWebhook;
use App\Models\PgFee;
use App\Models\PgInvoice;
use App\Models\PgLedger;
use App\Models\PgMerchant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Semua perubahan status pembayaran Payment Gateway lewat sini.
 * Dirancang untuk lonjakan (mis. war tiket):
 *  - create(): satu INSERT tanpa memanggil Duitku; dobel external_id
 *    ditangkap unique index -> invoice yang sama dikembalikan.
 *  - chooseMethod(): Duitku baru dipanggil saat pembeli memilih metode.
 *  - markPaid(): lock baris invoice + merchant saja, transaksi singkat.
 */
class PgPaymentService
{
    public function create(PgMerchant $merchant, array $data): array
    {
        try {
            $invoice = PgInvoice::create([
                'pg_merchant_id' => $merchant->id,
                'external_id' => $data['external_id'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'customer_name' => $data['customer']['name'],
                'customer_email' => $data['customer']['email'] ?? null,
                'customer_phone' => $data['customer']['phone'] ?? null,
                'return_url' => $data['return_url'] ?? null,
                'expires_at' => now()->addMinutes((int) ($data['expiry_minutes'] ?? 60)),
            ]);

            return [$invoice, true];
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $existing = PgInvoice::where('pg_merchant_id', $merchant->id)->where('external_id', $data['external_id'])->first();

            if (! $existing) {
                throw $e;
            }

            return [$existing, false];
        }
    }

    /** Kunci metode bayar (sekali saja per invoice) lalu minta VA/QRIS/URL ke Duitku. */
    public function chooseMethod(PgInvoice $invoice, PgFee $fee, PgDuitkuClient $duitku): PgInvoice
    {
        // Tahap 1 (singkat): klaim invoice supaya klik ganda / dua tab tidak
        // membuat dua transaksi Duitku untuk order yang sama.
        [$claimed, $isNew] = DB::transaction(function () use ($invoice, $fee) {
            $locked = PgInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_method || ! $locked->isPayable()) {
                return [$locked, false];
            }

            $feeAmount = $fee->feeFor((float) $locked->amount);

            if ($feeAmount >= (float) $locked->amount) {
                throw new RuntimeException('Nominal terlalu kecil untuk metode ini.');
            }

            $locked->forceFill([
                'payment_method' => $fee->payment_method,
                'payment_name' => $fee->name,
                'fee' => $feeAmount,
                'net_amount' => (float) $locked->amount - $feeAmount,
            ])->save();

            return [$locked, true];
        });

        if (! $isNew) {
            return $claimed;
        }

        $claimed->setRelation('merchant', $invoice->merchant);

        try {
            $result = $duitku->inquiry($claimed, $fee->payment_method);
        } catch (RuntimeException $e) {
            // Gagal di Duitku: lepas lagi metodenya supaya pembeli bisa coba metode lain.
            PgInvoice::whereKey($claimed->id)->whereNull('gateway_reference')
                ->update(['payment_method' => null, 'payment_name' => null, 'fee' => null, 'net_amount' => null]);

            throw $e;
        }

        $claimed->forceFill([
            'gateway_reference' => $result['reference'],
            'va_number' => $result['va_number'],
            'qr_string' => $result['qr_string'],
            'payment_url' => $result['payment_url'],
        ])->save();

        return $claimed;
    }

    /**
     * Dari callback Duitku yang SUDAH diverifikasi & dikonfirmasi. Idempotent.
     * Uang yang benar-benar masuk tetap dicatat walau invoice sempat
     * kedaluwarsa/dibatalkan di sisi teleios.
     */
    public function markPaid(string $orderNumber): ?PgInvoice
    {
        $paid = DB::transaction(function () use ($orderNumber) {
            $invoice = PgInvoice::where('order_number', $orderNumber)->lockForUpdate()->first();

            if (! $invoice || $invoice->status === PgInvoice::STATUS_PAID) {
                return null;
            }

            $net = (float) ($invoice->net_amount ?? $invoice->amount);

            $invoice->forceFill(['status' => PgInvoice::STATUS_PAID, 'paid_at' => now()])->save();

            $merchant = PgMerchant::whereKey($invoice->pg_merchant_id)->lockForUpdate()->firstOrFail();
            $merchant->forceFill(['balance' => (float) $merchant->balance + $net])->save();

            PgLedger::create([
                'pg_merchant_id' => $merchant->id,
                'pg_invoice_id' => $invoice->id,
                'type' => 'credit',
                'amount' => $net,
                'balance_after' => $merchant->balance,
                'description' => "Pembayaran {$invoice->external_id} ({$invoice->payment_name})",
            ]);

            return $invoice;
        });

        if ($paid) {
            SendPgWebhook::dispatch($paid->id, 'invoice.paid');
        }

        return $paid;
    }

    /** pending -> expired/failed/cancelled secara atomik; webhook hanya kalau benar berubah. */
    public function close(PgInvoice $invoice, string $status): bool
    {
        $changed = PgInvoice::whereKey($invoice->id)
            ->where('status', PgInvoice::STATUS_PENDING)
            ->update(['status' => $status, 'updated_at' => now()]) === 1;

        if ($changed) {
            SendPgWebhook::dispatch($invoice->id, 'invoice.'.$status);
        }

        return $changed;
    }
}
