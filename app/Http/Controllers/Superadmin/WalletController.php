<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AdminWalletAction;
use App\Models\AuditLog;
use App\Models\Wallet;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Superadmin wallet balance management — point 1.3 of the deposit/
 * wallet request: "dapat mengurangi saldo dan menambah saldo lengkap
 * juga dengan history nya" (able to increase/decrease balance, with
 * full history). Every credit/debit goes through WalletLedgerService
 * (the same class the user-facing deposit flow uses) so it produces an
 * identical immutable LedgerEntry with balance_before/balance_after,
 * plus an AdminWalletAction row specifically recording that this
 * particular change was admin-initiated (who, why, when, self-approved
 * since only one admin role exists in this app today).
 *
 * Route-level protected by the `superadmin` middleware (app/Http/
 * Middleware/SuperadminMiddleware.php) — see the note on
 * Superadmin\DepositController for why this bypasses CrudAdmin.
 */
class WalletController extends Controller
{
    public function index(Request $request): View
    {
        $wallets = Wallet::with('user')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->value();
                $q->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.wallet.index', compact('wallets'));
    }

    /**
     * Full before/after ledger history for one wallet, plus the
     * admin-action trail (who adjusted it manually, and why).
     */
    public function history(string $walletId): View
    {
        $wallet = Wallet::with('user')->findOrFail($walletId);

        $entries = $wallet->ledgerEntries()
            ->with('transaction')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $adminActions = AdminWalletAction::where('wallet_id', $wallet->id)
            ->with(['admin', 'approver'])
            ->latest()
            ->get();

        return view('superadmin.wallet.history', compact('wallet', 'entries', 'adminActions'));
    }

    public function credit(Request $request, string $walletId): RedirectResponse
    {
        return $this->adjust($request, $walletId, 'CREDIT');
    }

    public function debit(Request $request, string $walletId): RedirectResponse
    {
        return $this->adjust($request, $walletId, 'DEBIT');
    }

    private function adjust(Request $request, string $walletId, string $direction): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $wallet = Wallet::findOrFail($walletId);

        try {
            $entry = DB::transaction(function () use ($wallet, $validated, $direction) {
                $before = (float) $wallet->balance;

                // Created up front (before the ledger mutation) so the
                // LedgerTransaction it produces can reference this
                // action's id — new_value is filled in right after,
                // once balance_after is known. Wrapped in this same
                // transaction so a failed debit (insufficient balance)
                // rolls the AdminWalletAction insert back too, instead
                // of leaving a phantom "approved" action with no
                // matching ledger entry.
                $action = AdminWalletAction::create([
                    'wallet_id'    => $wallet->id,
                    'admin_id'     => Auth::id(),
                    'action'       => $direction,
                    'amount'       => $validated['amount'],
                    'direction'    => $direction,
                    'reason'       => $validated['reason'],
                    'status'       => 'approved',
                    'approved_by'  => Auth::id(),
                    'approved_at'  => now(),
                    'old_value'    => ['balance' => $before],
                ]);

                $entry = $direction === 'CREDIT'
                    ? WalletLedgerService::credit($wallet, (float) $validated['amount'], AdminWalletAction::class, $action->id, $validated['reason'], Auth::id())
                    : WalletLedgerService::debit($wallet, (float) $validated['amount'], AdminWalletAction::class, $action->id, $validated['reason'], Auth::id());

                $action->update(['new_value' => ['balance' => $entry->balance_after]]);

                return $entry;
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::create([
            'actor_type'  => Auth::user() ? Auth::user()::class : null,
            'actor_id'    => Auth::id(),
            'action'      => "ADMIN_WALLET_{$direction}",
            'entity_type' => Wallet::class,
            'entity_id'   => $wallet->id,
            'old_value'   => ['balance' => $entry->balance_before],
            'new_value'   => ['balance' => $entry->balance_after, 'reason' => $validated['reason']],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'created_at'  => now(),
        ]);

        return redirect()
            ->route('wallet.history', $wallet->id)
            ->with('success', $direction === 'CREDIT' ? 'Saldo berhasil ditambahkan.' : 'Saldo berhasil dikurangi.');
    }
}
