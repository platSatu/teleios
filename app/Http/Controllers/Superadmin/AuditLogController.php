<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global read-only listing of the immutable audit trail — written to by
 * CrudAdmin (app/Helpers/CrudAdmin.php) on every store/update/delete,
 * plus Superadmin\WalletController for manual balance adjustments.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('action'), function ($q) use ($request) {
                $q->where('action', 'like', '%' . $request->string('action')->value() . '%');
            })
            ->when($request->filled('entity_type'), function ($q) use ($request) {
                $q->where('entity_type', 'like', '%' . $request->string('entity_type')->value() . '%');
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('superadmin.audit-log.index', compact('logs'));
    }
}
