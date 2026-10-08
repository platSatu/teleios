<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\PgInvoice;
use App\Models\PgMerchant;
use App\Services\PaymentGateway\PgPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API untuk server website pemakai (/api/pay/v1, middleware 'pg.signature').
 * Semua data dibatasi ke merchant milik signature -- invoice merchant lain
 * tidak pernah bisa dibaca/diubah.
 */
class PgApiController extends Controller
{
    public function store(Request $request, PgPaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'description' => ['required', 'string', 'max:255'],
            'customer.name' => ['required', 'string', 'max:150'],
            'customer.email' => ['nullable', 'email', 'max:150'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'return_url' => ['nullable', 'url', 'max:500'],
            'expiry_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ]);

        [$invoice, $created] = $payments->create($this->merchant($request), $data);

        return response()->json(['success' => true, 'created' => $created, 'data' => $invoice->toApi()], $created ? 201 : 200);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->find($request, $id)->toApi()]);
    }

    public function cancel(Request $request, string $id, PgPaymentService $payments): JsonResponse
    {
        $invoice = $this->find($request, $id);

        // Metode sudah dipilih = pembeli mungkin sedang membayar VA/QRIS; jangan dibatalkan.
        if ($invoice->status !== PgInvoice::STATUS_PENDING || $invoice->gateway_reference) {
            return response()->json(['success' => false, 'message' => 'Invoice tidak bisa dibatalkan (sudah diproses).', 'data' => $invoice->toApi()], 409);
        }

        $payments->close($invoice, PgInvoice::STATUS_CANCELLED);

        return response()->json(['success' => true, 'data' => $invoice->fresh()->toApi()]);
    }

    public function balance(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['balance' => (int) $this->merchant($request)->balance]]);
    }

    private function merchant(Request $request): PgMerchant
    {
        return $request->attributes->get('pgMerchant');
    }

    /** Cari lewat id teleios ATAU external_id milik merchant ini. */
    private function find(Request $request, string $id): PgInvoice
    {
        return PgInvoice::where('pg_merchant_id', $this->merchant($request)->id)
            ->where(fn ($q) => $q->where('external_id', $id)->orWhere('id', $id))
            ->firstOrFail();
    }
}
