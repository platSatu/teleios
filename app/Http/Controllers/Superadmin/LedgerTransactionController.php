<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global read-only listing of every ledger transaction header (the
 * group a set of double-entry ledger_entries belongs to). show()
 * drills into that transaction's entries + which wallet/user they hit.
 */
class LedgerTransactionController extends Controller
{
    public function index(Request $request): View
    {
        $transactions = LedgerTransaction::with('creator')
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->string('status')->value());
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('transaction_number', 'like', '%' . $request->string('search')->value() . '%');
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.ledger-transaction.index', compact('transactions'));
    }

    public function show(string $id): View
    {
        $transaction = LedgerTransaction::with(['entries.wallet.user', 'creator'])->findOrFail($id);

        return view('superadmin.ledger-transaction.show', compact('transaction'));
    }
}
