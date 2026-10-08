<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\PgFee;
use App\Models\PgInvoice;
use App\Services\PaymentGateway\PgDuitkuClient;
use App\Services\PaymentGateway\PgPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Halaman/popup checkout publik (akses = token acak invoice). Nominal
 * TIDAK pernah dibaca dari browser -- selalu dari baris invoice. Endpoint
 * JSON ada di routes/api.php (tanpa sesi/CSRF, karena popup berjalan di
 * iframe website lain), dibatasi throttle per IP.
 */
class PgCheckoutController extends Controller
{
    public function show(string $token): Response
    {
        $invoice = $this->invoice($token);

        return response()
            ->view('payment-gateway.checkout', [
                'invoice' => $invoice,
                'merchant' => $invoice->merchant,
                'methods' => $invoice->payment_method ? collect() : PgFee::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
                'state' => $this->state($invoice),
            ])
            ->header('Content-Security-Policy', "frame-ancestors 'self' ".$invoice->merchant->frameAncestors());
    }

    public function chooseMethod(Request $request, string $token, PgPaymentService $payments): JsonResponse
    {
        $invoice = $this->invoice($token);
        $fee = PgFee::where('is_active', true)->where('payment_method', (string) $request->input('method'))->first();

        if (! $fee || ! $invoice->merchant->isActive()) {
            return response()->json(['message' => 'Metode pembayaran tidak tersedia.'], 422);
        }

        try {
            $invoice = $payments->chooseMethod($invoice, $fee, app(PgDuitkuClient::class));
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'Metode ini sedang tidak bisa dipakai: '.$e->getMessage()], 422);
        }

        Cache::forget("pg:status:{$token}");

        return response()->json($this->state($invoice));
    }

    /** Dicek popup tiap beberapa detik; di-cache 2 detik supaya ringan saat ramai. */
    public function status(string $token): JsonResponse
    {
        return response()->json(Cache::remember("pg:status:{$token}", 2, fn () => $this->state($this->invoice($token))));
    }

    private function invoice(string $token): PgInvoice
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $token), 404);

        return PgInvoice::with('merchant:id,name,logo_url,allowed_domains,status')->where('token', $token)->firstOrFail();
    }

    private function state(PgInvoice $invoice): array
    {
        $status = $invoice->status === PgInvoice::STATUS_PENDING && $invoice->expires_at->isPast()
            ? PgInvoice::STATUS_EXPIRED
            : $invoice->status;

        return [
            'status' => $status,
            'amount' => (int) $invoice->amount,
            'method' => $invoice->payment_method,
            'method_name' => $invoice->payment_name,
            'va_number' => $invoice->va_number,
            'qr_svg' => $invoice->qr_string ? (string) QrCode::format('svg')->size(240)->margin(1)->generate($invoice->qr_string) : null,
            'payment_url' => $invoice->payment_url,
            'expires_at' => $invoice->expires_at->toIso8601String(),
            'return_url' => $invoice->return_url,
        ];
    }
}
