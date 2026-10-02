<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\PaymentTransaction;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ], [], ['date_from' => 'Dari tanggal', 'date_to' => 'Sampai tanggal']);

        $dateFrom = isset($validated['date_from']) ? Carbon::createFromFormat('Y-m-d', $validated['date_from'])->startOfDay() : null;
        $dateTo = isset($validated['date_to']) ? Carbon::createFromFormat('Y-m-d', $validated['date_to'])->endOfDay() : null;

        // Filter bersama tabel & kartu ringkasan: tanggal dibuat, user, pencarian.
        // Rentang pakai >= / <= pada created_at (bukan whereDate) supaya index kolom tetap terpakai.
        $filtered = fn () => Deposit::query()
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->when($request->filled('user_id'), function ($q) use ($request) {
                $q->where('user_id', $request->string('user_id')->value());
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->where(function ($q) use ($search) {
                    $q->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });

        $deposits = $filtered()
            ->with('user')
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->string('status')->value());
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        // Kartu ringkasan (2 Oktober 2026): mengikuti filter tanggal, user, dan
        // pencarian -- supaya total per periode kelihatan -- tapi TIDAK filter
        // status, karena kartu justru memecah per status.
        //
        // FIX (2 Oktober 2026): dulu cuma menghitung FAILED, jadi deposit
        // EXPIRED tidak masuk kartu mana pun dan angkanya tidak cocok dengan
        // tabel. Sekarang 1 query GROUP BY status (bukan 4 query terpisah),
        // semua status ikut terhitung, dan Total = jumlah semua status.
        $byStatus = $filtered()
            ->selectRaw('status, COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (string $status) => (int) ($byStatus[$status]->total_count ?? 0);

        $stats = [
            'total' => (int) $byStatus->sum('total_count'),
            'success' => $count('SUCCESS'),
            'success_amount' => (float) ($byStatus['SUCCESS']->total_amount ?? 0),
            'pending' => $count('PENDING'),
            'failed' => $count('FAILED'),
            'expired' => $count('EXPIRED'),
        ];

        // Pilihan filter status: status baku + status lain yang benar-benar ada di data.
        $statuses = collect(['PENDING', 'SUCCESS', 'FAILED', 'EXPIRED'])
            ->merge($byStatus->keys())
            ->filter()
            ->unique()
            ->values();

        return view('superadmin.deposit.index', compact('deposits', 'users', 'stats', 'statuses', 'dateFrom', 'dateTo'));
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
