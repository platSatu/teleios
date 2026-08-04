<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\PaymentTransaction;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
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
        $deposits = Deposit::with('user')
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->string('status')->value());
            })
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
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.deposit.index', compact('deposits', 'users'));
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
