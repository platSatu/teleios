<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global read-only listing of every payment-gateway transaction record
 * (today: only MANUAL simulated deposits via App\Http\Controllers\
 * User\Deposit\DepositController; this is the same table a real
 * payment gateway integration would write callbacks into later).
 */
class PaymentTransactionController extends Controller
{
    public function index(Request $request): View
    {
        $transactions = PaymentTransaction::with('reference.user')
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->string('status')->value());
            })
            ->when($request->filled('provider'), function ($q) use ($request) {
                $q->where('provider', $request->string('provider')->value());
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.payment-transaction.index', compact('transactions'));
    }
}
