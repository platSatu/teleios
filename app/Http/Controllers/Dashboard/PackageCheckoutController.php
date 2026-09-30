<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\ReferralCode;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUser;
use App\Models\VoucherUserRedemption;
use App\Models\Wallet;
use App\Notifications\PackagePurchasedNotification;
use App\Services\Package\BranchSubscriptionService;
use App\Services\Referral\ReferralService;
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
 * Referral: semua aturannya (diskon Rupiah sekali di pembelian pertama,
 * komisi berulang, masa tahan komplain, anti-curang) ada di
 * App\Services\Referral\ReferralService -- controller ini hanya memanggilnya.
 */
class PackageCheckoutController extends Controller
{
    public function __construct(private readonly ReferralService $referrals)
    {
    }

    public function show(Request $request, Package $package, BranchSubscriptionService $branchSubscriptions): View|RedirectResponse
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

        // Paket dibeli PER BRANCH -- company harus sudah punya minimal satu
        // branch (alur Company -> Branch -> Paket).
        $branchOptions = $branchSubscriptions->branchesWithActiveVoucher(
            Company::where('user_id', Auth::id())->first()
        );

        if ($branchOptions->isEmpty()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Buat branch terlebih dahulu. Paket dibeli untuk masing-masing branch.');
        }

        $selectedBranchId = old('branch_office_id', $request->query('branch_office_id'));

        $package->load(['categoryApplication', 'categoryApplications']);
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
        // bukan validasi maupun penguncian apa pun -- ReferralService::
        // validate() di store() tetap penentunya. Tidak relevan lagi begitu user
        // sudah terkunci ke satu referrer ($linkedReferrer di atas).
        $suggestedReferralCode = $linkedReferrer ? null : $request->cookie('referral_code');

        return view('dashboard.package.checkout', compact('package', 'wallet', 'linkedReferrer', 'suggestedReferralCode', 'branchOptions', 'selectedBranchId'));
    }

    public function applyPromo(Request $request, Package $package): JsonResponse
    {
        $code = $request->string('code')->trim()->value();

        // Model voucher (kuota, batas pakai, dll.) tidak dikirim ke browser.
        return response()->json(collect($this->validatePromo($code, Auth::id()))->only(['valid', 'message', 'discount_percent']));
    }

    /**
     * Pratinjau diskon referral. Model kode tidak dikirim ke browser (berisi
     * data pemilik & persentase komisi) -- hanya pesan & nominal diskon.
     */
    public function applyReferral(Request $request, Package $package): JsonResponse
    {
        $user = Auth::user();
        $result = $this->referrals->validate($request->string('code')->trim()->value(), $user);

        if (! $result['valid']) {
            return response()->json($result);
        }

        $promoPercent = $this->promoPercent($request->string('promo')->trim()->value(), $user->id);
        $base = (float) $package->price - round((float) $package->price * $promoPercent / 100, 2);
        $firstPurchase = ! $user->referrer_id;
        $discount = $this->referrals->quote($result['data'], $base, $firstPurchase)['discount'];

        return response()->json([
            'valid' => true,
            'message' => match (true) {
                ! $firstPurchase => 'Anda sudah terhubung dengan kode ini. Diskon referral hanya berlaku di pembelian pertama.',
                $discount > 0 => 'Kode referral valid! Anda dapat diskon Rp '.number_format($discount, 0, ',', '.').'.',
                default => 'Kode referral valid.',
            },
            'discount_amount' => $discount,
        ]);
    }

    public function store(Request $request, Package $package, BranchSubscriptionService $branchSubscriptions): RedirectResponse
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
            'branch_office_id' => ['required', 'uuid'],
            'kode_voucher' => ['nullable', 'string', 'max:32'],
            'kode_referral' => ['nullable', 'string', 'max:32'],
        ]);

        // Branch tujuan wajib milik company owner ini, dan tidak sedang
        // aktif dengan paket lain (tidak ada upgrade/downgrade). Dicek
        // lagi saat redeem -- lihat BranchSubscriptionService.
        try {
            $branch = $branchSubscriptions->branchOfCompanyOrFail($company, $request->input('branch_office_id'));
            $branchSubscriptions->assertCanActivate($company, $branch, $package);

            if ($package->is_trial) {
                $branchSubscriptions->assertTrialAvailable(Auth::user());
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $user = Auth::user();
        $promoCode = trim((string) $request->input('kode_voucher'));
        $referralCodeInput = trim((string) $request->input('kode_referral'));

        $voucherUser = null;
        $discountPercent = 0;

        if ($promoCode !== '') {
            $promoResult = $this->validatePromo($promoCode, $user->id);

            if (! $promoResult['valid']) {
                return back()->withInput()->with('error', $promoResult['message']);
            }

            $voucherUser = $promoResult['data'];
            $discountPercent = min((float) $voucherUser->percentase, 100);
        }

        $price = (float) $package->price;
        $promoDiscount = round($price * $discountPercent / 100, 2);

        // Referral: kode yang diketik sekarang, atau otomatis kode pemilik
        // yang sudah terhubung (komisi berulang). Diskon hanya untuk
        // pembelian pertama (saat baru terhubung) -- lihat ReferralService.
        $referralCode = null;
        $isNewReferralLink = false;

        if ($referralCodeInput !== '') {
            $referralResult = $this->referrals->validate($referralCodeInput, $user);

            if (! $referralResult['valid']) {
                return back()->withInput()->with('error', $referralResult['message']);
            }

            $referralCode = $referralResult['data'];
            $isNewReferralLink = ! $user->referrer_id;
        } elseif ($user->referrer_id) {
            $referralCode = ReferralCode::where('user_id', $user->referrer_id)->where('status', 'active')->first();
        }

        $referralQuote = $referralCode ? $this->referrals->quote($referralCode, $price - $promoDiscount, $isNewReferralLink) : null;
        $discountAmount = round($promoDiscount + ($referralQuote['discount'] ?? 0), 2);
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
                $voucherUser, $referralCode, $referralQuote, $isNewReferralLink,
                $company, $branch, $branchSubscriptions
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
                        'kode_referral' => $isNewReferralLink ? $referralCode->code : null,
                        'referral_discount' => $referralQuote['discount'] ?? 0,
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

                // Trial sekali per nomor HP -- unique index di tabel klaim
                // menolak klaim kedua dan me-rollback checkout ini.
                if ($package->is_trial) {
                    $branchSubscriptions->claimTrial($user, $company, $branch, $package, $subscription);
                }

                // Activation code for the purchased package — not valid
                // yet, only becomes so once redeemed via
                // Dashboard\VoucherRedeemController (valid_from/until are
                // computed there, from package.duration, at redeem time).
                Voucher::create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'branch_office_id' => $branch->id,
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

                // Pertama kali terhubung: kunci customer ke pemilik kode
                // (permanen), lalu catat komisinya -- lihat ReferralService.
                if ($isNewReferralLink) {
                    $user->update(['referrer_id' => $referralCode->user_id]);
                }

                if ($referralCode) {
                    $this->referrals->record($referralCode, $user, $subscription, $referralQuote, $isNewReferralLink);
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
                        'branch_office_id' => $branch->id,
                        'amount' => $finalPrice,
                        'kode_voucher' => $voucherUser?->kode_voucher,
                        'kode_referral' => $referralCode?->code,
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

    /** Persen diskon kode promo (0 kalau kosong/tidak valid) -- untuk pratinjau referral. */
    private function promoPercent(string $code, string $userId): float
    {
        $result = $code !== '' ? $this->validatePromo($code, $userId) : ['valid' => false];

        return $result['valid'] ? min((float) $result['data']->percentase, 100) : 0.0;
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
