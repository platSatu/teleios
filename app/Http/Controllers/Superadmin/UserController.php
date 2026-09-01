<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\AdminWalletAction;
use App\Models\AuditLog;
use App\Models\CompanyToUser;
use App\Models\Deposit;
use App\Models\HistoryUserLogin;
use App\Models\LedgerEntry;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use App\Models\ReferralCodeUsage;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUserRedemption;
use App\Models\Wallet;
use App\Models\WalletTransfer;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Data Users" in the sidebar — previously pointed at a raw url() with
 * no route or controller behind it at all. No create() (users register
 * themselves via App\Http\Controllers\Auth\RegisteredUserController).
 * destroy() exists but is intentionally not a guaranteed operation —
 * see the comment on it below.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with('wallet')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Summary cards above the table — fixed to the whole dataset,
        // not affected by the search filter above (same rule as
        // Chat\MessageScheduleController / Superadmin\DepositController).
        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'superadmin' => User::where('user_type', 'SUPERADMIN')->count(),
            'total_balance' => Wallet::sum('balance'),
        ];

        return view('superadmin.user.index', compact('users', 'stats'));
    }

    public function create(): View
    {
        return view('superadmin.user.create');
    }

    /**
     * Lets a superadmin add a user directly, bypassing the public
     * registration form entirely (no email to send, no "confirm your
     * password" step — the account exists the moment this submits).
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Optional, bare national number only (no leading 0, no '62'
            // country code) — same shape as every other handphone input
            // in this app (Auth\AuthController::register(), User\Profile\
            // CompanyUserController), normalized to the '62'-prefixed
            // digit-only form every other phone number here expects
            // right after validation, below.
            'handphone' => ['nullable', 'regex:/^[1-9][0-9]{9,13}$/'],
            'status' => ['required', 'in:active,inactive'],
            'user_type' => ['required', 'in:USER,SUPERADMIN'],
        ], [
            'handphone.regex' => 'Nomor WhatsApp harus 10-14 digit angka, tanpa awalan 0 atau kode negara 62 (contoh: 81286800080).',
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request) {
            $this->validateUniqueHandphone($validator, $request);
        });

        if ($validator->fails()) {
            return redirect()
                ->route('superadmin-users.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Same normalization as CompanyUserController::store() — see
        // that method's comment on the field.
        $validated['handphone'] = filled($validated['handphone'] ?? null) ? '62'.$validated['handphone'] : null;

        // password hashes automatically — User::casts() has
        // 'password' => 'hashed', so CrudAdmin::store's plain
        // Model::create() call hashes it on the way in.
        CrudAdmin::store(User::class, $validated, afterCreate: function ($model) {
            // Admin-created accounts skip email verification: there's
            // no signup flow here for the user to click a link from,
            // and a superadmin manually adding someone already vouches
            // for the email being real. Without this, the account
            // would be stuck behind the 'verified' middleware on every
            // dashboard route with no way to actually verify.
            // Direct property assignment (not ->update()) because
            // email_verified_at isn't in User::$fillable.
            $model->email_verified_at = now();
            $model->save();
        });

        return redirect()
            ->route('superadmin-users.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * "Show" button on each row of index() — a single page pulling
     * together everything tied to this user's id across the app
     * (deposit, ledger/saldo history, login history, vouchers, admin
     * actions they've performed, and the audit trail of their own
     * actions), instead of superadmin having to go check each of the
     * separate list pages (Data Deposits, Ledger Entries, History User
     * Login, etc.) one by one and filter by this one user manually.
     */
    public function show(string $id): View
    {
        $user = User::with(['wallet', 'referralCode'])->findOrFail($id);

        $deposits = Deposit::where('user_id', $id)
            ->latest()
            ->paginate(10, ['*'], 'deposits_page');

        $ledgerEntries = LedgerEntry::where('user_id', $id)
            ->with('transaction')
            ->latest()
            ->paginate(10, ['*'], 'ledger_page');

        $loginHistories = HistoryUserLogin::where('user_id', $id)
            ->latest('last_login')
            ->paginate(10, ['*'], 'logins_page');

        $vouchers = Voucher::where('user_id', $id)
            ->latest()
            ->get();

        // Actions this user performed AS an admin on other users'
        // wallets (only meaningful if user_type === SUPERADMIN, but
        // shown regardless in case a user was demoted after acting).
        $adminActions = AdminWalletAction::where('admin_id', $id)
            ->with('wallet.user')
            ->latest()
            ->paginate(10, ['*'], 'admin_actions_page');

        // Everything this user did that CrudAdmin/WalletController
        // logged to the immutable audit trail (again, mostly relevant
        // for a superadmin account).
        $auditLogs = AuditLog::where('actor_id', $id)
            ->latest('created_at')
            ->paginate(10, ['*'], 'audit_page');

        // Riwayat kode referral: dua sisi berbeda untuk user yang sama.
        // (1) Sebagai PEMILIK kode — siapa saja yang pernah memakai kode
        // referral milik user ini dan berapa komisi yang didapat tiap
        // kali. (2) Sebagai PEMAKAI — kode referral siapa yang pernah
        // dipakai user ini saat checkout (biasanya cuma satu baris,
        // karena referrer_id di-lock permanen di pemakaian pertama).
        $referralUsagesAsOwner = $user->referralCode
            ? ReferralCodeUsage::where('referral_code_id', $user->referralCode->id)
                ->with(['usedBy', 'subscription.package'])
                ->latest()
                ->paginate(10, ['*'], 'referral_owner_page')
            : null;

        $referralUsagesAsUser = ReferralCodeUsage::where('used_by_user_id', $id)
            ->with(['referralCode.user', 'subscription.package'])
            ->latest()
            ->paginate(10, ['*'], 'referral_user_page');

        // Riwayat pemakaian kode voucher promo (VoucherUser, bukan
        // Voucher aktivasi) oleh user ini — kode apa yang dipakai dan
        // untuk pembelian package apa.
        $voucherPromoRedemptions = VoucherUserRedemption::where('user_id', $id)
            ->with(['voucherUser', 'subscription.package'])
            ->latest()
            ->paginate(10, ['*'], 'voucher_promo_page');

        // Company tab: companies this user owns, plus every company
        // they've been added to as a member (owner or invited) via
        // company_to_users — see App\Models\User::companies() /
        // companyMemberships(). Superadmin\CompanyController is where
        // "Edit"/"Delete" on any of these actually lives; this tab is
        // just a jump-off point so a superadmin doesn't have to go
        // search the Data Company list for this user's rows manually.
        $ownedCompanies = $user->companies()->latest()->get();

        $companyMemberships = CompanyToUser::where('user_id', $id)
            ->with(['company', 'role'])
            ->latest()
            ->get();

        return view('superadmin.user.show', compact(
            'user', 'deposits', 'ledgerEntries', 'loginHistories', 'vouchers', 'adminActions', 'auditLogs',
            'referralUsagesAsOwner', 'referralUsagesAsUser', 'voucherPromoRedemptions',
            'ownedCompanies', 'companyMemberships'
        ));
    }

    public function edit(string $id): View
    {
        $user = CrudAdmin::find(User::class, $id);

        return view('superadmin.user.edit', compact('user'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        // A superadmin demoting/deactivating their own only account
        // would lock themselves out with no other way back in (there's
        // no separate "reactivate" path) — worth a hard stop rather
        // than a silent footgun.
        if ($id === Auth::id() && ($request->input('user_type') !== 'SUPERADMIN' || $request->input('status') !== 'active')) {
            return back()->with('error', 'Tidak bisa mengubah status/tipe akun sendiri menjadi non-superadmin/non-aktif.');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'handphone' => ['nullable', 'regex:/^[1-9][0-9]{9,13}$/'],
            'status' => ['required', 'in:active,inactive'],
            'user_type' => ['required', 'in:USER,SUPERADMIN'],
        ], [
            'handphone.regex' => 'Nomor WhatsApp harus 10-14 digit angka, tanpa awalan 0 atau kode negara 62 (contoh: 81286800080).',
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request, $id) {
            $this->validateUniqueHandphone($validator, $request, $id);
        });

        if ($validator->fails()) {
            return redirect()
                ->route('superadmin-users.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // An empty submission CLEARS the number (explicit null), rather
        // than leaving whatever was there before untouched — same
        // behavior as CompanyUserController::update().
        $validated['handphone'] = filled($validated['handphone'] ?? null) ? '62'.$validated['handphone'] : null;

        CrudAdmin::update(User::class, $id, $validated);

        return redirect()
            ->route('superadmin-users.index')
            ->with('success', 'Data user berhasil diperbarui.');
    }

    /**
     * Deletes a user — but in practice this will fail for almost any
     * account that has ever done anything financial, and that's by
     * design, not a bug to work around.
     *
     * Every user gets a Wallet the moment they're created (see
     * User::boot()), and wallets.user_id is a restrictOnDelete foreign
     * key specifically so a wallet can't be silently orphaned. If that
     * wallet has any ledger activity, the wallet itself can't be
     * deleted either — ledger_entries.wallet_id is also
     * restrictOnDelete, and LedgerEntry blocks deletion outright at
     * the model level (it's meant to be permanent, tamper-evident
     * history). So a user with real deposit/wallet history is
     * genuinely not hard-deletable, and the honest thing to do here is
     * catch that and say so, not quietly cascade/force it away and
     * lose financial records. For that case, deactivating the account
     * via Edit is the actual path.
     */
    public function destroy(string $id): RedirectResponse
    {
        if ($id === Auth::id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        try {
            CrudAdmin::delete(User::class, $id);
        } catch (QueryException $e) {
            return back()->with(
                'error',
                'User tidak bisa dihapus karena masih memiliki data terkait (wallet, deposit, riwayat ledger, dsb). '
                . 'Nonaktifkan user ini lewat Edit sebagai gantinya.'
            );
        }

        return redirect()
            ->route('superadmin-users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    /**
     * "Reset" button on index() — wipes this user's deletable
     * transactional/history data (login history, vouchers, voucher-promo
     * redemptions, referral code usage, company memberships, non-active
     * subscriptions, non-success deposits) and zeroes their wallet
     * balance. The account itself is NOT touched — only what happened
     * under it.
     *
     * Deliberately does NOT touch: LedgerEntry, AdminWalletAction,
     * AuditLog, PaymentTransaction, PaymentWebhook — these are
     * immutable financial/audit records, blocked from deletion both at
     * the DB level (restrictOnDelete) and by a deleting() hook on the
     * model itself that unconditionally throws (see LedgerEntry,
     * AdminWalletAction, AuditLog). Also skips any Deposit already
     * SUCCESS or Subscription still ACTIVE — same reasoning, see the
     * deleting() guards on those two models. Companies this user OWNS
     * are left alone too; deleting one would cascade into every other
     * member's CompanyToUser row, which is collateral damage outside
     * this one user's own data — only this user's *membership* rows
     * are removed.
     */
    public function reset(string $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $counts = DB::transaction(function () use ($user) {
            $counts = [
                'login_histories' => HistoryUserLogin::where('user_id', $user->id)->delete(),
                'vouchers' => Voucher::where('user_id', $user->id)->delete(),
                'voucher_redemptions' => VoucherUserRedemption::where('user_id', $user->id)->delete(),
                'referral_code_usages' => ReferralCodeUsage::where('used_by_user_id', $user->id)->delete(),
                'company_memberships' => CompanyToUser::where('user_id', $user->id)->delete(),
                'subscriptions' => Subscription::where('user_id', $user->id)
                    ->where('status', '!=', 'ACTIVE')
                    ->delete(),
                'deposits' => Deposit::where('user_id', $user->id)
                    ->where('status', '!=', 'SUCCESS')
                    ->delete(),
            ];

            // Zero the wallet balance rather than delete the row — it's
            // referenced (restrictOnDelete) by ledger_entries /
            // admin_wallet_actions / wallet_transfers, which are never
            // deleted here, so the wallet row itself can never actually
            // go away.
            if ($user->wallet) {
                $user->wallet->update(['balance' => 0]);
            }

            return $counts;
        });

        // Single manual audit row (not CrudAdmin::delete, which only
        // records one entity per call) capturing how many rows of each
        // type were wiped, same conventions CrudAdmin's writeAudit()
        // uses elsewhere.
        AuditLog::create([
            'actor_type' => Auth::user()::class,
            'actor_id' => Auth::id(),
            'action' => 'reset',
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'old_value' => null,
            'new_value' => $counts,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('superadmin-users.index')
            ->with('success', sprintf(
                'Data user "%s" berhasil direset — login: %d, voucher: %d, redemption voucher: %d, '
                . 'referral usage: %d, keanggotaan company: %d, subscription: %d, deposit: %d, saldo wallet: Rp 0. '
                . 'Ledger entry, audit log, admin wallet action, riwayat pembayaran, deposit SUCCESS, dan '
                . 'subscription ACTIVE tidak ikut dihapus karena merupakan data finansial permanen.',
                $user->name,
                $counts['login_histories'],
                $counts['vouchers'],
                $counts['voucher_redemptions'],
                $counts['referral_code_usages'],
                $counts['company_memberships'],
                $counts['subscriptions'],
                $counts['deposits'],
            ));
    }

    /**
     * "Hapus Total" — permanent, always-available hard-delete of a user
     * ACCOUNT INCLUDING its financial/audit history, on superadmin's
     * explicit request: unlike reset() above, this exists specifically
     * so a limited pool of real WhatsApp numbers can be reused by test
     * accounts before launch, and superadmin decided it should stay a
     * standing feature with no SUCCESS-deposit/ACTIVE-subscription
     * restriction — usable "kapan saja selama superadmin membutuhkan".
     *
     * Deletes, in FK-safe order (bulk Model::where(...)->delete() calls
     * bypass each model's per-instance static::deleting() guard — same
     * bypass technique reset() already uses, just without reset()'s
     * SUCCESS/ACTIVE exclusions): payment_transactions/payment_webhooks
     * matching this user's deposits/subscriptions (morphTo, no real FK,
     * so found by reference_type/reference_id instead of a relation),
     * wallet_transfers, admin_wallet_actions, ledger_entries, deposits,
     * subscriptions, then the wallet itself, then the smaller history
     * tables reset() also clears. referral_codes cascades away
     * automatically at the DB level once the user row is deleted below
     * (referral_codes.user_id is cascadeOnDelete) — including, as a
     * side effect, any referral_code_usages row where an OTHER user
     * redeemed THIS user's code (that table cascades from
     * referral_code_id too), since the code itself no longer has an
     * owner to belong to.
     *
     * Deliberately NEVER touched:
     * - AuditLog. It has no real foreign key to users at all
     *   (actor_id/entity_id are plain indexed strings) precisely so it
     *   can outlive the accounts it references — an orphaned reference
     *   after this runs is the intended, permanent behavior, not a bug.
     *   The AuditLog row this method itself writes below immediately
     *   becomes exactly that kind of orphaned-but-permanent reference.
     *
     * Still refused, but for reasons that have nothing to do with
     * financial-history immutability — these are separate business-data
     * safety rails this method does not attempt to route around:
     * - Owning a company (companies.user_id). It cascades on delete,
     *   and that cascade chains into jadwal_kelas/jadwal_student/
     *   branch_offices/etc — hard-deleting this user would silently
     *   wipe that company's entire operational data, including for any
     *   OTHER member still using it. Checked explicitly up front rather
     *   than left to fail, because unlike the pengajar_id case below it
     *   would NOT fail — it would silently succeed and take the company
     *   down with it.
     * - Still assigned as `pengajar_id` on a jadwal_kelas/jadwal_student
     *   row (restrictOnDelete at the DB level). Left to fail naturally
     *   with its own distinct QueryException message below — teaching
     *   assignments are real business data outside what was asked for
     *   here, not something to silently cascade through.
     */
    public function forceDestroy(string $id): RedirectResponse
    {
        if ($id === Auth::id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        $user = User::with('wallet')->findOrFail($id);

        if ($user->companies()->exists()) {
            return back()->with('error', sprintf(
                'User "%s" masih menjadi pemilik company (%s). Hapus atau pindahkan company tersebut dulu lewat '
                . 'Data Company sebelum menghapus total user ini — menghapus usernya akan ikut menghapus seluruh '
                . 'jadwal & data operasional company itu.',
                $user->name,
                $user->companies()->pluck('name')->implode(', ')
            ));
        }

        $before = $user->toArray();

        try {
            $counts = DB::transaction(function () use ($user) {
                $walletId = optional($user->wallet)->id;
                $depositIds = Deposit::where('user_id', $user->id)->pluck('id');
                $subscriptionIds = Subscription::where('user_id', $user->id)->pluck('id');

                $counts = [];

                $counts['payment_transactions'] = PaymentTransaction::where(function ($q) use ($depositIds) {
                    $q->where('reference_type', Deposit::class)->whereIn('reference_id', $depositIds);
                })->orWhere(function ($q) use ($subscriptionIds) {
                    $q->where('reference_type', Subscription::class)->whereIn('reference_id', $subscriptionIds);
                })->delete();

                $counts['payment_webhooks'] = PaymentWebhook::where(function ($q) use ($depositIds) {
                    $q->where('reference_type', Deposit::class)->whereIn('reference_id', $depositIds);
                })->orWhere(function ($q) use ($subscriptionIds) {
                    $q->where('reference_type', Subscription::class)->whereIn('reference_id', $subscriptionIds);
                })->delete();

                $counts['wallet_transfers'] = WalletTransfer::where('sender_user_id', $user->id)
                    ->orWhere('receiver_user_id', $user->id)
                    ->when($walletId, fn ($q) => $q->orWhere('sender_wallet_id', $walletId)->orWhere('receiver_wallet_id', $walletId))
                    ->delete();

                $counts['admin_wallet_actions'] = AdminWalletAction::where('admin_id', $user->id)
                    ->when($walletId, fn ($q) => $q->orWhere('wallet_id', $walletId))
                    ->delete();

                $counts['ledger_entries'] = LedgerEntry::where('user_id', $user->id)
                    ->when($walletId, fn ($q) => $q->orWhere('wallet_id', $walletId))
                    ->delete();

                $counts['deposits'] = Deposit::where('user_id', $user->id)->delete();
                $counts['subscriptions'] = Subscription::where('user_id', $user->id)->delete();

                if ($walletId) {
                    Wallet::where('id', $walletId)->delete();
                }

                $counts['login_histories'] = HistoryUserLogin::where('user_id', $user->id)->delete();
                $counts['vouchers'] = Voucher::where('user_id', $user->id)->delete();
                $counts['voucher_redemptions'] = VoucherUserRedemption::where('user_id', $user->id)->delete();
                $counts['referral_code_usages'] = ReferralCodeUsage::where('used_by_user_id', $user->id)->delete();
                $counts['company_memberships'] = CompanyToUser::where('user_id', $user->id)->delete();

                // Baris User itu sendiri, terakhir — referral_codes milik
                // dia cascade otomatis dari sini (lihat docblock di
                // atas). Kalau masih jadi pengajar_id aktif di jadwal
                // manapun, restrictOnDelete di DB akan menolak baris ini
                // dengan QueryException, ditangkap di catch() bawah.
                $user->delete();

                return $counts;
            });
        } catch (QueryException $e) {
            return back()->with('error', sprintf(
                'User "%s" tidak bisa dihapus total: kemungkinan masih tercatat sebagai pengajar (pengajar_id) '
                . 'aktif di jadwal kelas atau data murid. Pindahkan/hapus jadwal tersebut dulu lewat menu Jadwal, '
                . 'baru coba lagi. (Detail teknis: %s)',
                $before['name'],
                $e->getMessage()
            ));
        }

        // Ditulis setelah user-nya benar-benar hilang — actor_id tetap
        // superadmin yang melakukan, entity_id tetap id user yang baru
        // dihapus (jadi referensi "orphan" yang disengaja, sama seperti
        // audit_logs lain yang menunjuk ke entity yang sudah tidak ada).
        AuditLog::create([
            'actor_type' => Auth::user()::class,
            'actor_id' => Auth::id(),
            'action' => 'force_delete',
            'entity_type' => User::class,
            'entity_id' => $id,
            'old_value' => $before,
            'new_value' => $counts,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('superadmin-users.index')
            ->with('success', sprintf(
                'User "%s" berhasil DIHAPUS TOTAL beserta seluruh riwayat finansialnya — wallet, %d deposit, '
                . '%d subscription, %d ledger entry, %d admin wallet action, %d wallet transfer, '
                . '%d payment transaction, %d payment webhook, %d login history, %d voucher, '
                . '%d redemption voucher, %d referral usage, %d keanggotaan company. '
                . 'Audit log tetap permanen dan tidak ikut terhapus.',
                $before['name'],
                $counts['deposits'],
                $counts['subscriptions'],
                $counts['ledger_entries'],
                $counts['admin_wallet_actions'],
                $counts['wallet_transfers'],
                $counts['payment_transactions'],
                $counts['payment_webhooks'],
                $counts['login_histories'],
                $counts['vouchers'],
                $counts['voucher_redemptions'],
                $counts['referral_code_usages'],
                $counts['company_memberships'],
            ));
    }

    /**
     * Shared by store()/update(): checked against the NORMALIZED
     * ('62'-prefixed) number, not the raw form input — same reasoning
     * and same pattern as User\Profile\CompanyUserController::
     * validateUniqueHandphone(). $ignoreUserId excludes the user's own
     * current row on update() so re-saving their own unchanged number
     * doesn't false-positive against itself.
     */
    private function validateUniqueHandphone(ValidatorContract $validator, Request $request, ?string $ignoreUserId = null): void
    {
        $raw = $request->input('handphone');

        if (blank($raw)) {
            return;
        }

        if ($validator->errors()->has('handphone')) {
            return;
        }

        $normalized = '62'.$raw;

        $exists = User::where('handphone', $normalized)
            ->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))
            ->exists();

        if ($exists) {
            $validator->errors()->add('handphone', 'Nomor WhatsApp ini sudah terdaftar untuk user lain.');
        }
    }
}
