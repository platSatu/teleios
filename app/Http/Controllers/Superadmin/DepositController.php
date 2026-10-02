<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use App\Models\WalletWithdrawal;
use App\Services\Finance\FinanceSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin read-only view over every user's deposits.
 *
 * Covers points 1.1/1.2 of the deposit/wallet request: "melihat data
 * deposit seluruh user" (view every user's deposits) and "melihat
 * history sblm dan sesudah secara detail" (view detailed before/after
 * history) — the latter comes straight from LedgerEntry.balance_before
 * / balance_after via the deposit's ledgerTransaction relation.
 *
 * This controller talks to the models directly instead of going through
 * CrudAdmin: CrudAdmin::getAll() only supports flat where-like search on
 * the model's own columns, but this listing needs to filter/search
 * across the related user (name/email) as well. The authorization
 * guarantee CrudAdmin would have given up is instead enforced at the
 * route level by the `superadmin` middleware (app/Http/Middleware/
 * SuperadminMiddleware.php) — see routes/web.php.
 */
class DepositController extends Controller
{
    private const TABS = ['deposit', 'disbursement', 'penjualan', 'referral'];

    /** Status komisi referral (lihat superadmin/referral-code/_status). */
    private const REFERRAL_STATUSES = ['pending' => 'Tertahan', 'available' => 'Cair', 'cancelled' => 'Dibatalkan'];

    /**
     * Data Deposit superadmin (2 Oktober 2026): 3 tab (Deposit, Disbursement,
     * Penjualan Paket) + kartu ringkasan. Filter tanggal & user berlaku untuk
     * SEMUA kartu dan tabel; pencarian & status hanya untuk tabel di tab aktif.
     * Hanya tabel tab yang sedang dibuka yang di-query.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:100'],
        ], [], ['date_from' => 'Dari tanggal', 'date_to' => 'Sampai tanggal']);

        $tab = $validated['tab'] ?? 'deposit';
        $dateFrom = isset($validated['date_from']) ? Carbon::createFromFormat('Y-m-d', $validated['date_from'])->startOfDay() : null;
        $dateTo = isset($validated['date_to']) ? Carbon::createFromFormat('Y-m-d', $validated['date_to'])->endOfDay() : null;
        $userId = $validated['user_id'] ?? null;

        $summary = new FinanceSummaryService($dateFrom, $dateTo, $userId);
        $stats = [
            'deposit' => $summary->deposits(),
            'disbursement' => $summary->disbursements(),
            'sales' => $summary->packageSales(),
            'user_balance' => $summary->userBalance(),
            'referral' => $summary->referrals(),
        ];

        // Pilihan filter status per tab: [nilai => label].
        $statuses = match ($tab) {
            'deposit' => collect(['PENDING', 'SUCCESS', 'FAILED', 'EXPIRED'])->merge($stats['deposit']['statuses'])->unique()->mapWithKeys(fn ($s) => [$s => $s])->all(),
            'disbursement' => WalletWithdrawal::STATUS_LABELS,
            'penjualan' => ['ACTIVE' => 'ACTIVE', 'EXPIRED' => 'EXPIRED', 'CANCELLED' => 'CANCELLED'],
            'referral' => self::REFERRAL_STATUSES,
        };

        $status = array_key_exists((string) ($validated['status'] ?? ''), $statuses) ? $validated['status'] : null;
        $like = isset($validated['search']) && $validated['search'] !== ''
            ? '%'.addcslashes($validated['search'], '%_\\').'%'
            : null;

        $rows = $this->tabQuery($tab, $summary, $userId, $like)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.deposit.index', compact('tab', 'rows', 'users', 'stats', 'statuses', 'dateFrom', 'dateTo'));
    }

    /** Query tabel untuk tab aktif, sudah dibatasi tanggal, user, dan pencarian. */
    private function tabQuery(string $tab, FinanceSummaryService $summary, ?string $userId, ?string $like): Builder
    {
        $userMatches = fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like);

        return match ($tab) {
            'deposit' => $summary->inRange(Deposit::query()->with('user'))
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when($like, fn ($q) => $q->where(fn ($q) => $q
                    ->where('reference_number', 'like', $like)
                    ->orWhereHas('user', $userMatches))),
            'disbursement' => $summary->inRange(WalletWithdrawal::query()->with(['requestedBy', 'wallet.user', 'branchOffice']))
                ->when($userId, fn ($q) => $q->whereIn('wallet_id', $summary->userWalletIds()))
                ->when($like, fn ($q) => $q->where(fn ($q) => $q
                    ->where('account_name', 'like', $like)
                    ->orWhere('duitku_disburse_id', 'like', $like)
                    ->orWhereHas('requestedBy', $userMatches))),
            'referral' => $summary->referralUsages()->with(['referralCode.user', 'usedBy', 'subscription.package'])
                ->when($like, fn ($q) => $q->where(fn ($q) => $q
                    ->whereHas('referralCode', fn ($q) => $q->where('code', 'like', $like)->orWhereHas('user', $userMatches))
                    ->orWhereHas('usedBy', $userMatches))),
            'penjualan' => $summary->inRange(Subscription::query()->with(['user', 'package']))
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when($like, fn ($q) => $q->where(fn ($q) => $q
                    ->whereHas('user', $userMatches)
                    ->orWhereHas('package', fn ($q) => $q->where('name', 'like', $like)))),
        };
    }

    public function show(string $id): View
    {
        $deposit = Deposit::with(['user', 'ledgerTransaction.entries.wallet'])->findOrFail($id);

        $paymentTransactions = PaymentTransaction::where('reference_type', Deposit::class)
            ->where('reference_id', $deposit->id)
            ->latest()
            ->get();

        $statusHistory = TransactionStatusHistory::where('entity_type', Deposit::class)
            ->where('entity_id', $deposit->id)
            ->latest()
            ->get();

        return view('superadmin.deposit.show', compact('deposit', 'paymentTransactions', 'statusHistory'));
    }
}
