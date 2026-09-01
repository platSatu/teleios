<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\FrontendVisitorLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global read-only listing of every visit logged from fe-konexa's
 * public pages (beranda/artikel/syarat-dan-ketentuan/video/kontak) —
 * see App\Http\Controllers\Api\Frontend\VisitorLogController, which is
 * the only thing that ever writes to this table. Same "controller only
 * reads" shape as HistoryUserLoginController alongside it.
 */
class FrontendVisitorLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = FrontendVisitorLog::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->where('path', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%");
            })
            // Terbaru selalu paling atas.
            ->latest('visited_at')
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.frontend-visitor-log.index', compact('logs'));
    }
}
