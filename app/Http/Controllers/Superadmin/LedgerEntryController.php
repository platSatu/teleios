<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global read-only listing of every immutable ledger entry across every
 * user's wallet. LedgerEntry itself blocks update/delete at the model
 * level, so this is necessarily read-only.
 */
class LedgerEntryController extends Controller
{
    public function index(Request $request): View
    {
        $entries = LedgerEntry::with(['wallet.user', 'transaction'])
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

        return view('superadmin.ledger-entry.index', compact('entries'));
    }
}
