<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PgFee;
use App\Models\PgInvoice;
use App\Models\PgMerchant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin > Payment Gateway: aktivasi website pemakai, tarif per metode
 * (dipotong dari dana masuk), dan pantauan semua transaksi + fee teleios.
 */
class PgAdminController extends Controller
{
    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['merchant', 'tarif', 'transaksi'], true) ? $request->query('tab') : 'merchant';

        $paid = PgInvoice::where('status', PgInvoice::STATUS_PAID)->where('paid_at', '>=', now()->startOfMonth());

        return view('superadmin.payment-gateway.index', [
            'tab' => $tab,
            'summary' => [
                'gross' => (int) (clone $paid)->sum('amount'),
                'fee' => (int) (clone $paid)->sum('fee'),
                'count' => (clone $paid)->count(),
            ],
            'merchants' => $tab === 'merchant' ? PgMerchant::with('company:id,name')->withCount('invoices')->latest()->paginate(25)->withQueryString() : null,
            'fees' => PgFee::orderBy('sort_order')->orderBy('name')->get(),
            'invoices' => $tab === 'transaksi'
                ? PgInvoice::with('merchant:id,name')
                    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                    ->latest()->paginate(30)->withQueryString()
                : null,
        ]);
    }

    public function updateMerchant(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([PgMerchant::STATUS_PENDING, PgMerchant::STATUS_ACTIVE, PgMerchant::STATUS_SUSPENDED])],
            'rate_limit_per_minute' => ['required', 'integer', 'min:10', 'max:100000'],
        ]);

        PgMerchant::findOrFail($id)->update($validated);

        return back()->with('success', 'Merchant diperbarui.');
    }

    public function saveFee(Request $request): RedirectResponse
    {
        $fee = $request->filled('id') ? PgFee::findOrFail($request->input('id')) : new PgFee;

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('pg_fees', 'payment_method')->ignore($fee->id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(PgFee::TYPES))],
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'fee_flat' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $fee->fill($validated + ['is_active' => $request->boolean('is_active'), 'sort_order' => $validated['sort_order'] ?? 0])
            ->forceFill(['payment_method' => strtoupper($validated['payment_method'])])
            ->save();

        return redirect()->route('superadmin.payment-gateway.index', ['tab' => 'tarif'])->with('success', "Tarif {$fee->name} disimpan.");
    }

    public function deleteFee(string $id): RedirectResponse
    {
        PgFee::findOrFail($id)->delete();

        return redirect()->route('superadmin.payment-gateway.index', ['tab' => 'tarif'])->with('success', 'Tarif dihapus.');
    }
}
