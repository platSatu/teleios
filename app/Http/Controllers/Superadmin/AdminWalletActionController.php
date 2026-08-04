<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AdminWalletAction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global read-only listing of every admin-initiated wallet credit/debit
 * across all users — the cross-user counterpart to the per-wallet view
 * on Superadmin\WalletController::history(). Written to by
 * WalletController::adjust(); this controller only reads.
 */
class AdminWalletActionController extends Controller
{
    public function index(Request $request): View
    {
        $actions = AdminWalletAction::with(['wallet.user', 'admin'])
            ->when($request->filled('direction'), function ($q) use ($request) {
                $q->where('direction', $request->string('direction')->value());
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->whereHas('wallet.user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.admin-wallet-action.index', compact('actions'));
    }
}
