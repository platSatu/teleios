<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\ReferralCode;
use App\Models\ReferralCodeUsage;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUser;
use App\Models\VoucherUserRedemption;
use App\Models\Wallet;
use App\Notifications\PackagePurchasedNotification;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Package checkout: promo code (App\Models\VoucherUser) and/or referral
 * code (App\Models\ReferralCode) applied on top of a package's price,
 * paid for out of the user's wallet balance (App\Services\Wallet\
 * WalletLedgerService — the same helper the deposit/top-up flow uses).
 *
 * The two "apply" endpoints below exist purely so the checkout page can
 * validate a code and preview the discount in real time via fetch(),
 * without submitting anything yet. store() re-runs the exact same
 * validation server-side before charging the wallet — the client-side
 * check is a UX convenience, never the source of truth.
 *
 * On success this also generates a Voucher "activation code" for the
 * purchased package — see App\Models\Voucher and
 * Dashboard\VoucherRedeemController for the redeem step, which is a
 * deliberately separate action from the purchase itself.
 *
 * Referral codes work differently from promo codes: entering one is a
 * ONE-TIME action. The first time a user enters a valid referral code,
 * two things happen: they get the usual one-time price discount, AND
 * they're permanently linked to that referrer (users.referrer_id — see
 * migration 2026_07_30_200000). From then on, every future purchase this
 * user makes automatically pays the referrer a commission (their
 * referral_codes.percentage, default 20%) — no code re-entry needed —
 * for as long as the referral code stays active and this user's own
 * account isn't deactivated. See payReferralCommission() below.
 */
class PackageCheckoutController extends Controller
{
    public function show(Request $request, Package $package): View|RedirectResponse
    {
        abort_unless($package->status === 'active', 404);

        // A package purchase mints a Voucher tied to a company (see
        // store() below) — so a user has to have created their Company
        // first, same as every other company-scoped feature in this
        // app. Checked here too (not just in store()) so a user is
        // stopped at the checkout page itself, not just when they try
        // to submit it.
        if (! $this->userHasCompany()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Anda harus melengkapi data Company terlebih dahulu sebelum membeli package.');
        }

        $package->load('categoryApplication');
        $user = Auth::user();
        $wallet = $user->wallet;

        // Already linked to a referrer from a previous purchase? Show
        // that instead of the input field — it keeps earning them
        // commission automatically, nothing left for this user to type.
        $linkedReferrer = $user->referrer_id ? $user->referrer()->with('referralCode')->first() : null;

        // Kode dari link referral yang pernah diklik user ini — lihat
        // Auth\AuthController::rememberReferralCodeFromLink() (cookie
        // 'referral_code', nama sama persis, hardcoded di kedua tempat,
        // lihat komentar konstanta REFERRAL_COOKIE_NAME di sana untuk
        // alasannya). Ini CUMA jadi nilai default input di
        // checkout.blade.php supaya user tidak perlu ketik manual —
        // bukan validasi maupun penguncian apa pun. validateReferral()/
        // store() di bawah tetap jalan persis seperti sebelum ini ada,
        // sama sekali tidak berubah. Tidak relevan lagi begitu user
        // sudah terkunci ke satu referrer ($linkedReferrer di atas).
        $suggestedReferralCode = $linkedReferrer ? null : $request->cookie('referral_code');

        return view('dashboard.package.checkout', compact('package', 'wallet', 'linkedReferrer', 'suggestedReferralCode'));
    }

    public function applyPromo(Request $request, Package $package): JsonResponse
    {
        $code = $request->string('code')->trim()->value();
        $result = $this->validatePromo($code, Auth::id());

        return response()->json($result);
    }

    public function applyReferral(Request $request, Package $package): JsonResponse
    {
        $code = $request->string('code')->trim()->value();
        $result = $this->validateReferral($code, Auth::user());

        return response()->json($result);
    }

    public function store(Request $request, Package $package): RedirectResponse
    {
        abort_unless($package->status === 'active', 404);

        // Re-checked independently from show() — this is its own POST
        // route and can't assume the user actually went through the
        // checkout page first.
        $company = Company::where('user_id', Auth::id())->first();

        if (! $company) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Anda harus melengkapi data Company terlebih dahulu sebelum membeli package.');
        }

        $request->validate([
            'kode_voucher' => ['nullable', 'string', 'max:32'],
            'kode_referral' => ['nullable', 'string', 'max:32'],
        ]);

        $user = Auth::user();
        $promoCode = trim((string) $request->input('kode_voucher'));
        $referralCodeInput = trim((string) $request->input('kode_referral'));

        $voucherUser = null;
        $discountPercent = 0;

        // $referralCodeForDiscount: only set when the user freshly typed
        // a code THIS time — that's what earns them the one-time price
        // discount below. $referralCodeForCommission: the code that
        // should pay the referrer a commission on THIS purchase, which
        // also covers the "auto-continue" case (no input needed) once a
        // user is already linked to a referrer from a previous purchase.
        $referralCodeForDiscount = null;
        $referralCodeForCommission = null;
        $isNewReferralLink = false;

        if ($promoCode !== '') {
            $promoResult = $this->validatePromo($promoCode, $user->id);

            if (! $promoResult['valid']) {
                return back()->withInput()->with('error', $promoResult['message']);
            }

            $voucherUser = $promoResult['data'];
            $discountPercent += (float) $voucherUser->percentase;
        }

        if ($referralCodeInput !== '') {
            $referralResult = $this->validateReferral($referralCodeInput, $user);

            if (! $referralResult['valid']) {
                return back()->withInput()->with('error', $referralResult['message']);
            }

            $referralCodeForDiscount = $referralResult['data'];
            $referralCodeForCommission = $referralCodeForDiscount;
            $discountPercent += (float) $referralCodeForDiscount->percentage;
            $isNewReferralLink = ! $user->referrer_id;
        } elseif ($user->referrer_id) {
            // No code typed this time, but this user is already
            // permanently linked to a referrer from an earlier purchase
            // — that referrer still earns commission on this purchase,
            // automatically, exactly as requested ("cukup input sekali").
            $referralCodeForCommission = ReferralCode::where('user_id', $user->referrer_id)
                ->where('status', 'active')
                ->first();
        }

        // Additive/stacked when both codes are valid, capped at 100% so
        // the price can never go negative.
        $discountPercent = min($discountPercent, 100);

        $price = (float) $package->price;
        $discountAmount = round($price * $discountPercent / 100, 2);
        $finalPrice = max(0, round($price - $discountAmount, 2));

        $wallet = $user->wallet;

        if (! $wallet) {
            return back()->with('error', 'Wallet Anda tidak ditemukan.');
        }

        if ($finalPrice > 0 && (float) $wallet->balance < $finalPrice) {
            return back()->withInput()->with('error', 'Saldo wallet Anda tidak mencukupi untuk membeli package ini.');
        }

        // Submit-ganda guard: tanpa ini, double-click atau retry
        // browser karena koneksi lambat bisa membuat dua request
        // store() diproses nyaris bersamaan, masing-masing membuat
        // Subscription-nya sendiri dan mendebit wallet sendiri-sendiri
        // — keduanya valid secara individual (WalletLedgerService tidak
        // tahu ini "permintaan yang sama"), jadi kalau saldo cukup untuk
        // dua-duanya, user bisa kepotong dua kali untuk satu niat beli.
        // Cache::lock ini atomic (backed by CACHE_STORE=database, bukan
        // file/array — lihat CLAUDE.md poin 2), non-blocking: request
        // kedua yang datang selagi request pertama masih diproses akan
        // langsung ditolak dengan pesan, bukan menunggu lalu ikut lolos.
        $checkoutLock = Cache::lock("package-checkout:{$user->id}", 15);

        if (! $checkoutLock->get()) {
            return back()->withInput()->with('error', 'Ada proses pembelian lain yang sedang berjalan untuk akun Anda. Silakan tunggu beberapa detik lalu coba lagi.');
        }

        try {
            $subscription = DB::transaction(function () use (
                $package, $user, $wallet, $price, $discountPercent, $discountAmount, $finalPrice,
                $voucherUser, $referralCodeForDiscount, $referralCodeForCommission, $isNewReferralLink,
                $company
            ) {
                // Re-check the promo quota INSIDE the transaction, under a
                // row lock on voucher_users, right before we commit to
                // using it. The check in validatePromo() above (used for
                // the real-time "Gunakan" preview and the outer guard) is
                // a plain COUNT() with no lock, so two concurrent
                // checkouts using the same near-exhausted code could both
                // pass it before either row exists yet — this second
                // check closes that gap so the quota can never be
                // oversold under concurrency.
                if ($voucherUser) {
                    $lockedVoucherUser = VoucherUser::where('id', $voucherUser->id)->lockForUpdate()->first();

                    $totalUsed = VoucherUserRedemption::where('voucher_user_id', $lockedVoucherUser->id)->count();

                    if ($totalUsed >= $lockedVoucherUser->limit) {
                        throw new RuntimeException('Kuota kode promo ini sudah habis.');
                    }

                    $usedByThisUser = VoucherUserRedemption::where('voucher_user_id', $lockedVoucherUser->id)
                        ->where('user_id', $user->id)
                        ->count();

                    if ($usedByThisUser >= $lockedVoucherUser->use_by_user) {
                        throw new RuntimeException('Anda sudah mencapai batas pemakaian kode promo ini.');
                    }
                }

                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'amount' => $finalPrice,
                    'currency' => 'IDR',
                    'start_date' => now(),
                    'end_date' => now()->addDays((int) $package->duration),
                    'status' => 'ACTIVE',
                    'auto_renew' => false,
                    'metadata' => [
                        'package_name' => $package->name,
                        'original_price' => $price,
                        'discount_percent' => $discountPercent,
                        'discount_amount' => $discountAmount,
                        'kode_voucher' => $voucherUser?->kode_voucher,
                        'kode_referral' => $referralCodeForDiscount?->code,
                    ],
                ]);

                if ($finalPrice > 0) {
                    WalletLedgerService::debit(
                        $wallet,
                        $finalPrice,
                        Subscription::class,
                        $subscription->id,
                        "Pembelian package {$package->name}",
                        $user->id,
                        'PURCHASE'
                    );
                }

                $paymentTransaction = PaymentTransaction::create([
                    'reference_type' => Subscription::class,
                    'reference_id' => $subscription->id,
                    'provider' => 'WALLET',
                    'payment_method' => 'WALLET_BALANCE',
                    'amount' => $finalPrice,
                    'currency' => 'IDR',
                    'status' => 'SUCCESS',
                    'response_payload' => [
                        'discount_percent' => $discountPercent,
                        'discount_amount' => $discountAmount,
                    ],
                    'callback_received_at' => now(),
                ]);

                $subscription->update(['payment_transaction_id' => $paymentTransaction->id]);

                // Activation code for the purchased package — not valid
                // yet, only becomes so once redeemed via
                // Dashboard\VoucherRedeemController (valid_from/until are
                // computed there, from package.duration, at redeem time).
                Voucher::create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'package_id' => $package->id,
                    'subscription_id' => $subscription->id,
                    'kode_voucher' => Voucher::generateUniqueCode(),
                    'status' => 'pending',
                ]);

                if ($voucherUser) {
                    VoucherUserRedemption::create([
                        'voucher_user_id' => $voucherUser->id,
                        'user_id' => $user->id,
                        'subscription_id' => $subscription->id,
                    ]);
                }

                // First time this user ever enters a valid referral code:
                // permanently link them to that referrer. Every purchase
                // from here on (this one included, via
                // $referralCodeForCommission below) pays that referrer a
                // commission automatically — no code re-entry needed.
                if ($isNewReferralLink && $referralCodeForDiscount) {
                    $user->update(['referrer_id' => $referralCodeForDiscount->user_id]);
                }

                if ($referralCodeForCommission) {
                    $commissionAmount = $this->payReferralCommission($referralCodeForCommission, $user, $subscription);

                    ReferralCodeUsage::create([
                        'referral_code_id' => $referralCodeForCommission->id,
                        'used_by_user_id' => $user->id,
                        'subscription_id' => $subscription->id,
                        'discount_percent' => $referralCodeForCommission->percentage,
                        'commission_amount' => $commissionAmount,
                    ]);
                }

                // Purchase cashback/point straight back to the BUYER's
                // own wallet — separate from referral commission above
                // (that pays the referrer; this pays the buyer). Rate is
                // superadmin-configurable (Setting / Superadmin\
                // PointSettingController), default "every complete
                // Rp 10.000 spent earns Rp 100".
                $this->payPurchaseCashback($wallet, $user, $subscription);

                AuditLog::create([
                    'actor_type' => $user::class,
                    'actor_id' => $user->id,
                    'action' => 'PACKAGE_PURCHASE_SUCCESS',
                    'entity_type' => Subscription::class,
                    'entity_id' => $subscription->id,
                    'new_value' => [
                        'package_id' => $package->id,
                        'amount' => $finalPrice,
                        'kode_voucher' => $voucherUser?->kode_voucher,
                        'kode_referral' => $referralCodeForCommission?->code,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                TransactionStatusHistory::create([
                    'entity_type' => Subscription::class,
                    'entity_id' => $subscription->id,
                    'old_status' => null,
                    'new_status' => 'ACTIVE',
                    'changed_by' => $user->id,
                ]);

                return $subscription;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } finally {
            $checkoutLock->release();
        }

        // Sent only after the DB transaction above has fully committed
        // (Subscription + PaymentTransaction + activation Voucher all
        // saved) — never inside the transaction itself, so a mail/queue
        // failure can't roll back a successful purchase. Queued (see
        // PackagePurchasedNotification), so this doesn't slow down the
        // redirect below.
        $subscription->loadMissing(['package', 'voucher']);
        $user->notify(new PackagePurchasedNotification($subscription));

        return redirect()
            ->route('dashboard.package.invoice', $subscription->id)
            ->with('success', 'Pembelian berhasil! Kode aktivasi telah dikirim ke halaman Redeem Voucher.');
    }

    public function invoice(string $subscription): View
    {
        $subscription = Subscription::with(['package.categoryApplication', 'paymentTransaction', 'voucher', 'user'])
            ->findOrFail($subscription);

        abort_unless($subscription->user_id === Auth::id(), 403);

        return view('dashboard.package.invoice', compact('subscription'));
    }

    /**
     * Whether the logged-in user has already created their Company —
     * required before checkout since the Voucher minted on purchase now
     * carries a company_id. Same lookup used across every other
     * company-scoped controller in this app
     * (Company::where('user_id', ...)), just non-throwing here since
     * both callers need to redirect instead of aborting outright.
     */
    private function userHasCompany(): bool
    {
        return Company::where('user_id', Auth::id())->exists();
    }

    /**
     * @return array{valid: bool, message: string, data?: VoucherUser, discount_percent?: float}
     */
    private function validatePromo(string $code, string $userId): array
    {
        if ($code === '') {
            return ['valid' => false, 'message' => 'Masukkan kode promo.'];
        }

        $voucherUser = VoucherUser::where('kode_voucher', $code)->first();

        if (! $voucherUser) {
            return ['valid' => false, 'message' => 'Kode promo tidak ditemukan.'];
        }

        if ($voucherUser->status !== 'active') {
            return ['valid' => false, 'message' => 'Kode promo tidak aktif.'];
        }

        // Was Carbon::today() (date-only) compared against valid_from/
        // valid_until — that let a promo code stay valid through the
        // entire expiry day regardless of the hour it was actually
        // supposed to lapse. Both columns are real datetimes now (see
        // 2026_07_31_050100_change_voucher_users_valid_dates_to_datetime),
        // so this compares down to the minute like it always should have.
        $now = now();

        if ($voucherUser->valid_from && $now->lt($voucherUser->valid_from)) {
            return ['valid' => false, 'message' => 'Kode promo belum berlaku.'];
        }

        if ($voucherUser->valid_until && $now->gt($voucherUser->valid_until)) {
            return ['valid' => false, 'message' => 'Kode promo sudah kadaluarsa.'];
        }

        $totalUsed = VoucherUserRedemption::where('voucher_user_id', $voucherUser->id)->count();

        if ($totalUsed >= $voucherUser->limit) {
            return ['valid' => false, 'message' => 'Kuota kode promo ini sudah habis.'];
        }

        $usedByThisUser = VoucherUserRedemption::where('voucher_user_id', $voucherUser->id)
            ->where('user_id', $userId)
            ->count();

        if ($usedByThisUser >= $voucherUser->use_by_user) {
            return ['valid' => false, 'message' => 'Anda sudah mencapai batas pemakaian kode promo ini.'];
        }

        return [
            'valid' => true,
            'message' => "Kode promo valid! Diskon {$this->formatPercent($voucherUser->percentase)}%.",
            'data' => $voucherUser,
            'discount_percent' => (float) $voucherUser->percentase,
        ];
    }

    /**
     * @return array{valid: bool, message: string, data?: ReferralCode, discount_percent?: float}
     */
    private function validateReferral(string $code, User $user): array
    {
        if ($code === '') {
            return ['valid' => false, 'message' => 'Masukkan kode referral.'];
        }

        $referralCode = ReferralCode::where('code', $code)->first();

        if (! $referralCode) {
            return ['valid' => false, 'message' => 'Kode referral tidak ditemukan.'];
        }

        if ($referralCode->status !== 'active') {
            return ['valid' => false, 'message' => 'Kode referral tidak aktif / diblokir.'];
        }

        if ($referralCode->user_id === $user->id) {
            return ['valid' => false, 'message' => 'Tidak bisa memakai kode referral milik sendiri.'];
        }

        // Referral is a one-time link (see users.referrer_id): once set,
        // it can't be swapped to a different referrer by typing another
        // code — that would let someone "steal" a referral mid-way
        // through a user's lifetime. Re-entering the SAME code they're
        // already linked to is harmless and just falls through as valid.
        if ($user->referrer_id && $user->referrer_id !== $referralCode->user_id) {
            return ['valid' => false, 'message' => 'Anda sudah terhubung dengan kode referral lain sebelumnya dan tidak bisa menggantinya.'];
        }

        return [
            'valid' => true,
            'message' => "Kode referral valid! Diskon {$this->formatPercent($referralCode->percentage)}%.",
            'data' => $referralCode,
            'discount_percent' => (float) $referralCode->percentage,
        ];
    }

    /**
     * Pays the referrer their commission for one purchase made by a user
     * they referred. Returns the rupiah amount actually credited (0 if
     * nothing was paid, e.g. referred user's account is inactive, the
     * referral code got blocked, the referrer has no wallet, or there's
     * simply nothing to take a percentage of).
     *
     * "Komisi stop ketika user tersebut off" is enforced by the
     * $referredUser->status check below — no cron job needed, since
     * commission only ever gets paid at the moment of an actual
     * purchase; if the user has no active subscription and isn't buying
     * anything, there's naturally no commission event to begin with.
     */
    private function payReferralCommission(ReferralCode $referralCode, User $referredUser, Subscription $subscription): float
    {
        if ($referralCode->status !== 'active') {
            return 0.0;
        }

        if ($referredUser->status !== 'active') {
            return 0.0;
        }

        $commissionAmount = round((float) $subscription->amount * (float) $referralCode->percentage / 100, 2);

        if ($commissionAmount <= 0) {
            return 0.0;
        }

        $referrer = $referralCode->user ?? User::find($referralCode->user_id);
        $referrerWallet = $referrer?->wallet;

        if (! $referrerWallet) {
            return 0.0;
        }

        WalletLedgerService::credit(
            $referrerWallet,
            $commissionAmount,
            Subscription::class,
            $subscription->id,
            "Komisi referral {$this->formatPercent($referralCode->percentage)}% dari pembelian {$referredUser->name}",
            $referredUser->id,
            'REFERRAL_COMMISSION'
        );

        return $commissionAmount;
    }

    /**
     * Purchase cashback/point: every complete multiple of
     * `point_amount_threshold` (default Rp 10.000) actually paid earns
     * `point_value` (default Rp 100), credited straight into the
     * buyer's OWN wallet. Both numbers live in the settings table and
     * are editable by superadmin (Superadmin\PointSettingController) —
     * see App\Models\Setting.
     *
     * Uses intdiv() (integer division, truncates) so a Rp 25.000
     * purchase earns 2 x Rp 100 = Rp 200, not 2.5 x — "dan kelipatannya"
     * (and its multiples) means whole multiples only, per the request.
     */
    private function payPurchaseCashback(Wallet $wallet, User $user, Subscription $subscription): float
    {
        if (! filter_var(Setting::get('point_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return 0.0;
        }

        $threshold = (float) Setting::get('point_amount_threshold', 10000);
        $pointValue = (float) Setting::get('point_value', 100);

        if ($threshold <= 0 || $pointValue <= 0) {
            return 0.0;
        }

        $multiples = intdiv((int) $subscription->amount, (int) $threshold);
        $cashback = round($multiples * $pointValue, 2);

        if ($cashback <= 0) {
            return 0.0;
        }

        $packageName = $subscription->metadata['package_name'] ?? 'package';

        WalletLedgerService::credit(
            $wallet,
            $cashback,
            Subscription::class,
            $subscription->id,
            "Point pembelian {$packageName}",
            $user->id,
            'PURCHASE_CASHBACK'
        );

        return $cashback;
    }

    private function formatPercent(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
