<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\VoucherHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global read-only listing of every voucher create/update/delete action.
 * Written to by Superadmin\VoucherController (store/update/destroy);
 * this controller only reads.
 */
class VoucherHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $histories = VoucherHistory::with(['voucher', 'user', 'actionBy'])
            ->when($request->filled('action'), function ($q) use ($request) {
                $q->where('action', $request->string('action')->value());
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->whereHas('voucher', function ($q) use ($search) {
                    $q->where('kode_voucher', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.voucher-history.index', compact('histories'));
    }
}
