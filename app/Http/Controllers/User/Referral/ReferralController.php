<?php

namespace App\Http\Controllers\User\Referral;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReferralCodeUsage;
use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Referral Saya" -- untuk semua user: kode & link, customer yang diajak
 * (tanpa data kontak), komisi tertahan/cair, dan nominal diskon yang
 * diberikan ke customer baru (dibatasi Pengaturan Referral superadmin).
 * Semua aturan hitung ada di App\Services\Referral\ReferralService.
 */
class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $code = $user->referralCode;
        abort_unless($code, 404);

        $usageQuery = ReferralCodeUsage::where('referral_code_id', $code->id);

        $customers = $user->referredUsers()
            ->select('id', 'name', 'referrer_id', 'created_at')
            ->with([
                'companies:id,user_id,name',
                'vouchers' => fn ($query) => $query->select('id', 'user_id', 'package_id', 'status', 'valid_until')
                    ->whereNotNull('valid_until')
                    ->with('package:id,name')
                    ->orderByDesc('valid_until'),
            ])
            ->latest()
            ->paginate(10, ['*'], 'customer_page')
            ->withQueryString();

        $usages = (clone $usageQuery)
            ->with(['usedBy:id,name', 'subscription:id,package_id', 'subscription.package:id,name'])
            ->latest()
            ->paginate(10, ['*'], 'komisi_page')
            ->withQueryString();

        $stats = $this->referrals->totals(clone $usageQuery) + [
            'customers' => $user->referredUsers()->count(),
            'active_customers' => $user->referredUsers()->whereHas('vouchers', fn ($query) => $query->currentlyActive())->count(),
        ];

        return view('user.referral.index', [
            'code' => $code,
            'link' => route('register', ['ref' => $code->code]),
            'commissionPercent' => $this->referrals->commissionPercent($code),
            'buyerDiscount' => $this->referrals->buyerDiscount($code),
            'maxDiscount' => ReferralService::setting('referral_max_buyer_discount'),
            'holdDays' => (int) ReferralService::setting('referral_hold_days'),
            'stats' => $stats,
            'customers' => $customers,
            'usages' => $usages,
        ]);
    }

    public function updateDiscount(Request $request): RedirectResponse
    {
        $code = $request->user()->referralCode;
        abort_unless($code, 404);

        $max = ReferralService::setting('referral_max_buyer_discount');
        $validated = $request->validate([
            'buyer_discount_amount' => ['required', 'numeric', 'min:0', 'max:'.$max],
        ], [
            'buyer_discount_amount.max' => 'Diskon maksimal Rp '.number_format($max, 0, ',', '.').'.',
        ]);

        $before = $code->buyer_discount_amount;
        $code->update(['buyer_discount_amount' => $validated['buyer_discount_amount']]);

        AuditLog::create([
            'actor_type' => $request->user()::class,
            'actor_id' => $request->user()->id,
            'action' => 'referral_code.update_discount',
            'entity_type' => $code::class,
            'entity_id' => $code->id,
            'old_value' => ['buyer_discount_amount' => $before],
            'new_value' => ['buyer_discount_amount' => $validated['buyer_discount_amount']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Diskon untuk customer baru berhasil disimpan.');
    }
}
