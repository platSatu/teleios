<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReferralCode;
use App\Models\ReferralCodeUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Superadmin management of per-user referral codes (1:1 with User, see
 * App\Models\ReferralCode / App\Models\User::boot()). Follows the same
 * plain-Eloquent + manual AuditLog pattern as Superadmin\WalletController
 * rather than CrudAdmin — a referral code is never created "from scratch"
 * through this controller (it's always auto-created at registration), so
 * there's no store()/destroy() here, only read + edit(percentage/status)
 * + block/unblock + regenerate.
 */
class ReferralCodeController extends Controller
{
    public function index(Request $request): View
    {
        $referralCodes = ReferralCode::with('user')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.referral-code.index', compact('referralCodes'));
    }

    public function edit(string $id): View
    {
        $referralCode = ReferralCode::with('user')->findOrFail($id);

        $usages = ReferralCodeUsage::where('referral_code_id', $id)
            ->with(['usedBy', 'subscription.package'])
            ->latest()
            ->get();

        $totalCommission = $usages->sum('commission_amount');

        return view('superadmin.referral-code.edit', compact('referralCode', 'usages', 'totalCommission'));
    }

    /**
     * Global usage history across ALL referral codes — dipakai oleh siapa
     * (used_by_user_id), milik siapa kodenya (referralCode.user), kapan,
     * dan untuk pembelian package apa. Written to by Dashboard\
     * PackageCheckoutController::store() every time a referral code is
     * successfully applied at checkout — which only happens after
     * validateReferral() confirms the user isn't the code's own owner.
     */
    public function usageHistory(Request $request): View
    {
        $query = ReferralCodeUsage::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->whereHas('referralCode', function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%");
                })->orWhereHas('usedBy', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        $totalCommission = (clone $query)->sum('commission_amount');

        $usages = $query->with(['referralCode.user', 'usedBy', 'subscription.package'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.referral-code.history', compact('usages', 'totalCommission'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $referralCode = ReferralCode::findOrFail($id);
        $before = $referralCode->toArray();

        $referralCode->update($validated);

        $this->logAudit('update', $referralCode, $before, $referralCode->toArray());

        return redirect()
            ->route('referral-code.index')
            ->with('success', 'Persentase referral berhasil diperbarui.');
    }

    public function block(string $id): RedirectResponse
    {
        return $this->setStatus($id, 'blocked', 'diblokir');
    }

    public function unblock(string $id): RedirectResponse
    {
        return $this->setStatus($id, 'active', 'diaktifkan kembali');
    }

    private function setStatus(string $id, string $status, string $label): RedirectResponse
    {
        $referralCode = ReferralCode::findOrFail($id);
        $before = $referralCode->toArray();

        $referralCode->update(['status' => $status]);

        $this->logAudit('update', $referralCode, $before, $referralCode->toArray());

        return redirect()
            ->route('referral-code.index')
            ->with('success', "Kode referral berhasil {$label}.");
    }

    /**
     * Rolls a brand new unique code for this user (e.g. if the old one
     * leaked or the superadmin just wants to reset it). Percentage and
     * status are left untouched.
     */
    public function regenerate(string $id): RedirectResponse
    {
        $referralCode = ReferralCode::with('user')->findOrFail($id);
        $before = $referralCode->toArray();

        $referralCode->update([
            'code' => ReferralCode::generateUniqueCode($referralCode->user?->name),
        ]);

        $this->logAudit('update', $referralCode, $before, $referralCode->toArray());

        return redirect()
            ->route('referral-code.index')
            ->with('success', 'Kode referral berhasil digenerate ulang.');
    }

    private function logAudit(string $action, ReferralCode $referralCode, array $old, array $new): void
    {
        AuditLog::create([
            'actor_type' => Auth::user() ? Auth::user()::class : null,
            'actor_id' => Auth::id(),
            'action' => "referral_code.{$action}",
            'entity_type' => ReferralCode::class,
            'entity_id' => $referralCode->id,
            'old_value' => $old,
            'new_value' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
