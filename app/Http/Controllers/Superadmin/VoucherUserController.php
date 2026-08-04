<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\VoucherUser;
use App\Models\VoucherUserRedemption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin CRUD for shared/promo voucher codes (voucher_users table —
 * see App\Models\VoucherUser for how this differs from the per-user
 * App\Models\Voucher). All data access goes through CrudAdmin
 * (app/Helpers/CrudAdmin.php), which enforces the superadmin-only guard
 * and writes every store/update/delete to the audit_logs table — same
 * pattern as Superadmin\PackageController and Superadmin\VoucherController.
 */
class VoucherUserController extends Controller
{
    public function index(Request $request): View
    {
        $voucherUsers = CrudAdmin::getAll(
            modelClass: VoucherUser::class,
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'kode_voucher'],
        );

        return view('superadmin.voucher-user.index', compact('voucherUsers'));
    }

    public function create(): View
    {
        return view('superadmin.voucher-user.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::store(VoucherUser::class, $validated);

        return redirect()
            ->route('voucher-user.index')
            ->with('success', 'Voucher berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $voucherUser = CrudAdmin::find(VoucherUser::class, $id);

        return view('superadmin.voucher-user.show', compact('voucherUser'));
    }

    public function edit(string $id): View
    {
        $voucherUser = CrudAdmin::find(VoucherUser::class, $id);

        return view('superadmin.voucher-user.edit', compact('voucherUser'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(VoucherUser::class, $id, $validated);

        return redirect()
            ->route('voucher-user.index')
            ->with('success', 'Voucher berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(VoucherUser::class, $id);

        return redirect()
            ->route('voucher-user.index')
            ->with('success', 'Voucher berhasil dihapus.');
    }

    /**
     * Global redemption history across ALL voucher_users codes — who used
     * which code, when, and for which package purchase. Written to by
     * Dashboard\PackageCheckoutController::store() every time a promo
     * code is successfully applied at checkout.
     */
    public function redemptions(Request $request): View
    {
        $redemptions = VoucherUserRedemption::with(['voucherUser', 'user', 'subscription.package'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->whereHas('voucherUser', function ($q) use ($search) {
                    $q->where('kode_voucher', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                })->orWhereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.voucher-user.redemptions', compact('redemptions'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'percentase' => ['required', 'numeric', 'min:0', 'max:100'],
            // Optional on input: left blank, VoucherUser::boot() rolls a
            // fresh unique 6-digit code. If the superadmin does supply
            // one, it must be exactly 6 digits and unique.
            'kode_voucher' => [
                'nullable',
                'digits:6',
                Rule::unique('voucher_users', 'kode_voucher')->ignore($request->route('id')),
            ],
            'limit' => ['required', 'integer', 'min:1'],
            'use_by_user' => ['required', 'integer', 'min:1'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'status' => ['required', 'in:active,expire,used,inactive'],
        ]);
    }
}
