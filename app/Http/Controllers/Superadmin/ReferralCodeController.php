<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReferralCode;
use App\Models\ReferralCodeUsage;
use App\Models\Setting;
use App\Services\Referral\ReferralService;
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

        $settings = ReferralService::settings();

        return view('superadmin.referral-code.index', compact('referralCodes', 'settings'));
    }

    public function edit(string $id, ReferralService $referrals): View
    {
        $referralCode = ReferralCode::with('user')->findOrFail($id);

        $usages = ReferralCodeUsage::where('referral_code_id', $id)
            ->with(['usedBy', 'subscription.package'])
            ->latest()
            ->get();

        $totals = $referrals->totals(ReferralCodeUsage::where('referral_code_id', $id));
        $settings = ReferralService::settings();

        return view('superadmin.referral-code.edit', compact('referralCode', 'usages', 'totals', 'settings'));
    }

    /**
     * Global usage history across ALL referral codes — dipakai oleh siapa
     * (used_by_user_id), milik siapa kodenya (referralCode.user), kapan,
     * dan untuk pembelian package apa. Written to by Dashboard\
     * PackageCheckoutController::store() every time a referral code is
     * successfully applied at checkout — which only happens after
     * validateReferral() confirms the user isn't the code's own owner.
     */
    public function usageHistory(Request $request, ReferralService $referrals): View
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

        $totals = $referrals->totals(clone $query);

        $usages = $query->with(['referralCode.user', 'usedBy', 'subscription.package'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.referral-code.history', compact('usages', 'totals'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        // Kosong = ikut default Pengaturan Referral.
        $validated = $request->validate([
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'buyer_discount_amount' => ['nullable', 'numeric', 'min:0', 'max:'.ReferralService::setting('referral_max_buyer_discount')],
        ], [
            'buyer_discount_amount.max' => 'Diskon untuk customer maksimal Rp :max (lihat Pengaturan Referral).',
        ]);

        $referralCode = ReferralCode::findOrFail($id);
        $before = $referralCode->toArray();

        $referralCode->update($validated);

        $this->logAudit('update', $referralCode, $before, $referralCode->toArray());

        return redirect()
            ->route('referral-code.index')
            ->with('success', 'Komisi & diskon referral berhasil diperbarui.');
    }

    /** Pengaturan default referral (berlaku untuk kode yang tidak di-override). */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'referral_commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'referral_buyer_discount' => ['required', 'numeric', 'min:0', 'lte:referral_max_buyer_discount'],
            'referral_max_buyer_discount' => ['required', 'numeric', 'min:0'],
            'referral_hold_days' => ['required', 'integer', 'min:0', 'max:365'],
        ], [
            'referral_buyer_discount.lte' => 'Diskon default tidak boleh melebihi batas maksimal diskon.',
        ]);

        $before = ReferralService::settings();

        foreach ($validated as $key => $value) {
            Setting::set($key, (string) $value);
        }

        AuditLog::create([
            'actor_type' => Auth::user() ? Auth::user()::class : null,
            'actor_id' => Auth::id(),
            'action' => 'referral_setting.update',
            'entity_type' => Setting::class,
            'entity_id' => 'referral_setting',
            'old_value' => $before,
            'new_value' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return redirect()->route('referral-code.index')->with('success', 'Pengaturan referral berhasil disimpan.');
    }

    /** Batalkan komisi yang masih tertahan (ada komplain/refund). */
    public function cancelUsage(Request $request, string $usageId, ReferralService $referrals): RedirectResponse
    {
        $validated = $request->validate(['cancel_reason' => ['required', 'string', 'max:255']]);

        if (! $referrals->cancel($usageId, $validated['cancel_reason'])) {
            return back()->with('error', 'Komisi ini tidak bisa dibatalkan (sudah dicairkan atau sudah dibatalkan).');
        }

        AuditLog::create([
            'actor_type' => Auth::user() ? Auth::user()::class : null,
            'actor_id' => Auth::id(),
            'action' => 'referral_commission.cancel',
            'entity_type' => ReferralCodeUsage::class,
            'entity_id' => $usageId,
            'new_value' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Komisi dibatalkan dan tidak akan dicairkan.');
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
