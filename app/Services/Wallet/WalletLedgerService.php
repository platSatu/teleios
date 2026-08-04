<?php

namespace App\Services\Wallet;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns the one invariant that must never be violated anywhere a wallet
 * balance changes: every mutation is a DB::transaction that (1) locks the
 * wallet row (lockForUpdate) so concurrent credits/debits can't race each
 * other, (2) writes an immutable LedgerEntry recording the
 * balance_before/balance_after snapshot, and only then (3) updates
 * Wallet.balance to match.
 *
 * Both the user-facing deposit flow (App\Http\Controllers\User\Deposit\
 * DepositController) and the superadmin manual balance-adjustment flow
 * (App\Http\Controllers\Superadmin\WalletController) go through this
 * class instead of touching Wallet::balance directly, so "history sebelum
 * dan sesudah" (before/after history) is guaranteed to exist for every
 * change, not just the ones a controller author remembered to log.
 *
 * Usage:
 *   WalletLedgerService::credit($wallet, 100000, Deposit::class, $deposit->id, 'Manual top-up', Auth::id());
 *   WalletLedgerService::debit($wallet, 50000, AdminWalletAction::class, $action->id, 'Koreksi saldo', Auth::id());
 */
class WalletLedgerService
{
    public static function credit(
        Wallet $wallet,
        float $amount,
        string $referenceType,
        ?string $referenceId,
        string $description,
        ?string $actorId = null,
        string $transactionType = 'ADJUSTMENT',
    ): LedgerEntry {
        return self::move(
            $wallet, $amount, 'CREDIT',
            $referenceType, $referenceId, $description, $actorId, $transactionType
        );
    }

    public static function debit(
        Wallet $wallet,
        float $amount,
        string $referenceType,
        ?string $referenceId,
        string $description,
        ?string $actorId = null,
        string $transactionType = 'ADJUSTMENT',
    ): LedgerEntry {
        return self::move(
            $wallet, $amount, 'DEBIT',
            $referenceType, $referenceId, $description, $actorId, $transactionType
        );
    }

    /**
     * @throws RuntimeException if amount <= 0, or a DEBIT would push the
     *                           balance below zero.
     */
    private static function move(
        Wallet $wallet,
        float $amount,
        string $direction,
        string $referenceType,
        ?string $referenceId,
        string $description,
        ?string $actorId,
        string $transactionType,
    ): LedgerEntry {
        if ($amount <= 0) {
            throw new RuntimeException('Jumlah harus lebih besar dari 0.');
        }

        return DB::transaction(function () use (
            $wallet, $amount, $direction,
            $referenceType, $referenceId, $description, $actorId, $transactionType
        ) {
            // Re-fetch and lock inside the transaction — the $wallet
            // instance the caller passed in may already be a few moments
            // stale, so we can't trust its ->balance without locking the
            // real row first.
            $locked = Wallet::whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            $before = (float) $locked->balance;
            $after  = $direction === 'CREDIT' ? $before + $amount : $before - $amount;

            if ($direction === 'DEBIT' && $after < 0) {
                throw new RuntimeException('Saldo tidak mencukupi untuk melakukan pengurangan ini.');
            }

            $ledgerTransaction = LedgerTransaction::create([
                'transaction_type' => $transactionType,
                'reference_type'   => $referenceType,
                'reference_id'     => $referenceId,
                'status'           => 'SUCCESS',
                'description'      => $description,
                'created_by'       => $actorId,
            ]);

            $entry = LedgerEntry::create([
                'transaction_id'  => $ledgerTransaction->id,
                'wallet_id'       => $locked->id,
                'user_id'         => $locked->user_id,
                'direction'       => $direction,
                'amount'          => $amount,
                'balance_before'  => $before,
                'balance_after'   => $after,
            ]);

            $locked->update(['balance' => $after]);

            return $entry;
        });
    }
}
