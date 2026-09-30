<?php

namespace App\Http\Controllers\User\History;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\HistoryUserLogin;
use App\Models\Subscription;
use App\Models\Voucher;
use App\Models\WalletTransfer;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Riwayat Saya" — replaces the dead "Subscription" link in the profile
 * dropdown (resources/views/layouts/partials/header.blade.php). One
 * page, tabs, everything scoped to Auth::id() only (never another
 * user's data): top-up history, voucher history, package purchases,
 * wallet transfers, login history (referral: halaman "Referral Saya").
 *
 * Deliberately its own namespace (User\History) rather than folded into
 * DepositController etc. — this is a read-only aggregation view over
 * data those other controllers own, not a place that writes anything.
 */
class HistoryUserController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $deposits = Deposit::where('user_id', $user->id)
            ->latest()
            ->paginate(10, ['*'], 'deposits_page');

        $vouchers = Voucher::where('user_id', $user->id)
            ->latest()
            ->paginate(10, ['*'], 'vouchers_page');

        $subscriptions = Subscription::where('user_id', $user->id)
            ->with('package')
            ->latest()
            ->paginate(10, ['*'], 'subscriptions_page');

        // Both directions: transfers this user sent AND received — see
        // App\Models\WalletTransfer / Dashboard\WalletTransferController.
        $transfers = WalletTransfer::where('sender_user_id', $user->id)
            ->orWhere('receiver_user_id', $user->id)
            ->with(['sender', 'receiver'])
            ->latest()
            ->paginate(10, ['*'], 'transfers_page');

        $loginHistories = HistoryUserLogin::where('user_id', $user->id)
            ->latest('last_login')
            ->paginate(10, ['*'], 'logins_page');

        return view('user.history.index', compact(
            'deposits', 'vouchers', 'subscriptions',
            'transfers',
            'loginHistories'
        ));
    }
}
