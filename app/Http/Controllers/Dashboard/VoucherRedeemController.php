<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TransactionStatusHistory;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Redeem step for the activation-code Voucher generated at package
 * purchase (Dashboard\PackageCheckoutController::store()). Deliberately
 * separate from the purchase itself: valid_from/valid_until are only
 * stamped in here, at redeem time, based on the package's `duration` —
 * so a user can buy now and only "start the clock" whenever they're
 * actually ready to use the package.
 */
class VoucherRedeemController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();

        $pendingVouchers = Voucher::where('user_id', $userId)
            ->where('status', 'pending')
            ->with('package.categoryApplication')
            ->latest()
            ->get();

        $activeVouchers = Voucher::where('user_id', $userId)
            ->where('status', 'active')
            ->with('package.categoryApplication')
            ->latest()
            ->get();

        return view('dashboard.voucher-redeem.index', compact('pendingVouchers', 'activeVouchers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_voucher' => ['required', 'string', 'max:32'],
        ]);

        $code = trim($validated['kode_voucher']);

        // Whole redeem wrapped in one locked transaction — previously
        // this read the voucher (and the "previous active voucher for
        // the same package" used for chaining below) with no lock at
        // all. Redeeming the same code twice nearly simultaneously
        // (double-click), or redeeming two different pending vouchers
        // for the SAME package at the same time, could both pass the
        // status/window checks before either commits: the first case
        // just double-writes the same row harmlessly, but the second
        // case is a real correctness bug — both requests would compute
        // their new valid_from/valid_until from the same stale
        // "previous active voucher" snapshot instead of chaining onto
        // each other, producing an overlapping window and silently
        // wasting one voucher's duration. lockForUpdate() on both reads
        // closes that gap: a second request blocks until the first
        // commits, then re-reads the now-current state.
        try {
            $voucher = DB::transaction(function () use ($code, $request) {
                $voucher = Voucher::where('kode_voucher', $code)
                    ->where('user_id', Auth::id())
                    ->with('package')
                    ->lockForUpdate()
                    ->first();

                if (! $voucher) {
                    throw new RuntimeException('Kode voucher tidak ditemukan atau bukan milik Anda.');
                }

                if ($voucher->status === 'active') {
                    throw new RuntimeException('Kode voucher ini sudah pernah di-redeem sebelumnya.');
                }

                if ($voucher->status !== 'pending') {
                    throw new RuntimeException('Kode voucher ini tidak bisa di-redeem (status: ' . ucfirst($voucher->status) . ').');
                }

                $days = (int) ($voucher->package?->duration ?? 30);
                $oldStatus = $voucher->status;

                // Accumulate instead of overlap: if this user already has
                // another active, not-yet-expired voucher for the SAME
                // package, chain the new period on top of it (start =
                // that voucher's valid_until) rather than resetting from
                // today. Otherwise, redeeming a second voucher for a
                // package you're already covered on would just give you
                // an identical/overlapping window and silently waste it.
                $previousActive = Voucher::where('user_id', $voucher->user_id)
                    ->where('package_id', $voucher->package_id)
                    ->where('id', '!=', $voucher->id)
                    ->where('status', 'active')
                    ->orderByDesc('valid_until')
                    ->lockForUpdate()
                    ->first();

                $validFrom = ($previousActive && $previousActive->valid_until && $previousActive->valid_until->gte(now()))
                    ? $previousActive->valid_until
                    : now();

                $voucher->update([
                    'status' => 'active',
                    'valid_from' => $validFrom,
                    'valid_until' => (clone $validFrom)->addDays($days),
                    'redeemed_at' => now(),
                ]);

                AuditLog::create([
                    'actor_type' => Auth::user()::class,
                    'actor_id' => Auth::id(),
                    'action' => 'VOUCHER_REDEEM_SUCCESS',
                    'entity_type' => Voucher::class,
                    'entity_id' => $voucher->id,
                    'old_value' => ['status' => $oldStatus],
                    'new_value' => [
                        'status' => 'active',
                        // Was toDateString() — the audit trail is the one
                        // place an admin can later prove exactly when a
                        // package actually started/expired, so it should
                        // keep the same minute-level precision as
                        // valid_from/valid_until themselves.
                        'valid_from' => $voucher->valid_from?->toDateTimeString(),
                        'valid_until' => $voucher->valid_until?->toDateTimeString(),
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                TransactionStatusHistory::create([
                    'entity_type' => Voucher::class,
                    'entity_id' => $voucher->id,
                    'old_status' => $oldStatus,
                    'new_status' => 'active',
                    'changed_by' => Auth::id(),
                ]);

                return $voucher;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dashboard.voucher-redeem.index')
            ->with('success', "Voucher berhasil di-redeem! Aktif sampai {$voucher->valid_until->format('d M Y H:i')}.");
    }
}
