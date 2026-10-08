<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\PgInvoice;
use App\Services\PaymentGateway\PgDuitkuClient;
use App\Services\PaymentGateway\PgPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Callback Duitku khusus Payment Gateway (merchantOrderId prefix "PG-"),
 * terpisah dari callback Deposit & Tagihan. Lunas hanya kalau: signature
 * valid + Duitku sendiri mengonfirmasi lunas dengan nominal yang sama.
 * Balasan non-200 membuat Duitku mengirim ulang callback-nya.
 */
class PgDuitkuCallbackController extends Controller
{
    public function handle(Request $request, PgDuitkuClient $duitku, PgPaymentService $payments): Response
    {
        $n = $request->only(['merchantCode', 'amount', 'merchantOrderId', 'resultCode', 'reference', 'signature']);
        $orderNumber = (string) ($n['merchantOrderId'] ?? '');

        if (! str_starts_with($orderNumber, 'PG-') || ! $duitku->verifyCallback($n)) {
            Log::warning('pg-callback: ditolak', ['merchantOrderId' => $orderNumber]);

            return response('Invalid', 400);
        }

        $invoice = PgInvoice::where('order_number', $orderNumber)->first(['id', 'amount', 'status']);

        if (! $invoice) {
            return response('Not found', 404);
        }

        if (($n['resultCode'] ?? null) !== '00') {
            $payments->close($invoice, PgInvoice::STATUS_FAILED);

            return response('OK');
        }

        if ((int) round((float) $n['amount']) !== (int) $invoice->amount) {
            Log::error('pg-callback: nominal beda', ['merchantOrderId' => $orderNumber]);

            return response('Amount mismatch', 400);
        }

        $reason = $duitku->unconfirmedReason($orderNumber, (int) $invoice->amount);

        if ($reason !== null) {
            Log::warning('pg-callback: belum terkonfirmasi', ['merchantOrderId' => $orderNumber, 'reason' => $reason]);

            return response('Not confirmed', 503);
        }

        $payments->markPaid($orderNumber);

        return response('OK');
    }
}
