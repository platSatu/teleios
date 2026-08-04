<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\HistoryUserLogin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global read-only listing of every user's login/logout history.
 * Already written to by App\Http\Controllers\Auth\
 * AuthenticatedSessionController on every login (last_login) and
 * logout (last_logout + duration) — this controller only reads.
 */
class HistoryUserLoginController extends Controller
{
    public function index(Request $request): View
    {
        $histories = HistoryUserLogin::with('user')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('last_login')
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.history-user-login.index', compact('histories'));
    }
}
