<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\TransactionStatusHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global read-only listing of every status transition across entities
 * (today: only Deposit — App\Http\Controllers\User\Deposit\
 * DepositController writes here on create/pay, e.g. null → SUCCESS or
 * PENDING → SUCCESS). This is the "History Deposits" sidebar page —
 * distinct from "Data Deposits" (Superadmin\DepositController), which
 * lists the deposits themselves rather than their status changes.
 */
class TransactionStatusHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $histories = TransactionStatusHistory::with('changer')
            ->when($request->filled('entity_type'), function ($q) use ($request) {
                $q->where('entity_type', 'like', '%' . $request->string('entity_type')->value() . '%');
            })
            ->when($request->filled('new_status'), function ($q) use ($request) {
                $q->where('new_status', $request->string('new_status')->value());
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.transaction-status-history.index', compact('histories'));
    }
}
