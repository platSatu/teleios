<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransfer;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use RuntimeException;

/**
 * Wallet-to-wallet transfer between two users, as a 4-step wizard
 * (resources/views/dashboard/wallet-transfer/index.blade.php):
 *   1. Enter recipient's phone or email -> lookupRecipient() confirms
 *      they exist before letting the user move on.
 *   2. Enter amount.
 *   3. Review/checkout screen.
 *   4. Enter PIN -> store() actually moves the money.
 *
 * Security notes:
 *   - Requires a PIN (users.pin, hashed) to be set first — see
 *     User\Settings\PinController. No PIN, no transfer.
 *   - PIN attempts are rate-limited (5 per 15 minutes per user) via
 *     Laravel's RateLimiter, so a stolen session can't be used to brute
 *     force the PIN.
 *   - Both wallets are locked in a consistent order (sorted by id)
 *     before any balance change, so two transfers between the same pair
 *     of users running concurrently in opposite directions can't
 *     deadlock each other.
 *   - Money movement itself still goes through WalletLedgerService (the
 *     same helper deposits/admin adjustments/purchases use), so the
 *     balance_before/after ledger trail is guaranteed consistent app-wide.
 */
class WalletTransferController extends Controller
{
    private const MIN_AMOUNT = 1000;
    private const MAX_PIN_ATTEMPTS = 5;
    private const PIN_LOCKOUT_SECONDS = 900; // 15 minutes

    public function index(): View
    {
        $user = Auth::user();
        $wallet = $user->wallet;
        $hasPin = ! is_null($user->pin);

        return view('dashboard.wallet-transfer.index', [
            'wallet' => $wallet,
            'hasPin' => $hasPin,
        ]);
    }

    public function lookupRecipient(Request $request): JsonResponse
    {
        $identifier = trim((string) $request->input('identifier'));

        if ($identifier === '') {
            return response()->json(['found' => false, 'message' => 'Masukkan nomor HP atau email.']);
        }

        $recipient = User::where(function ($q) use ($identifier) {
                $q->where('email', $identifier)->orWhere('handphone', $identifier);
            })
            ->first();

        if (! $recipient) {
            return response()->json(['found' => false, 'message' => 'User tidak ditemukan.']);
        }

        if ($recipient->id === Auth::id()) {
            return response()->json(['found' => false, 'message' => 'Tidak bisa transfer ke akun sendiri.']);
        }

        if ($recipient->status !== 'active') {
            return response()->json(['found' => false, 'message' => 'Akun user ini sedang tidak aktif.']);
        }

        return response()->json([
            'found' => true,
            'user' => [
                'id' => $recipient->id,
                'name' => $recipient->name,
                'contact' => $this->maskContact($recipient->email),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sender = Auth::user();

        if (is_null($sender->pin)) {
            return redirect()
                ->route('user-settings.pin.edit')
                ->with('error', 'Buat PIN transaksi terlebih dahulu sebelum transfer saldo.');
        }

        $validated = $request->validate([
            'receiver_id' => ['required', 'uuid', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:' . self::MIN_AMOUNT],
            'note' => ['nullable', 'string', 'max:255'],
            'pin' => ['required', 'digits:6'],
        ]);

        if ($validated['receiver_id'] === $sender->id) {
            return back()->withInput()->with('error', 'Tidak bisa transfer ke akun sendiri.');
        }

        $rateLimitKey = 'wallet-transfer-pin:' . $sender->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_PIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()->with('error', "Terlalu banyak percobaan PIN salah. Coba lagi dalam {$seconds} detik.");
        }

        if (! Hash::check($validated['pin'], $sender->pin)) {
            RateLimiter::hit($rateLimitKey, self::PIN_LOCKOUT_SECONDS);

            return back()->with('error', 'PIN salah.');
        }

        RateLimiter::clear($rateLimitKey);

        $receiver = User::findOrFail($validated['receiver_id']);

        if ($receiver->status !== 'active') {
            return back()->withInput()->with('error', 'Akun penerima sedang tidak aktif.');
        }

        $senderWallet = $sender->wallet;
        $receiverWallet = $receiver->wallet;

        if (! $senderWallet || ! $receiverWallet) {
            return back()->withInput()->with('error', 'Wallet tidak ditemukan.');
        }

        $amount = (float) $validated['amount'];

        if ((float) $senderWallet->balance < $amount) {
            return back()->withInput()->with('error', 'Saldo Anda tidak mencukupi.');
        }

        try {
            $transfer = DB::transaction(function () use ($sender, $receiver, $senderWallet, $receiverWallet, $amount, $validated) {
                // Lock BOTH wallets up front, in a consistent order (sorted
                // by id) regardless of who's sending/receiving — prevents
                // a deadlock if two transfers between the same two users
                // run concurrently in opposite directions.
                $ids = [$senderWallet->id, $receiverWallet->id];
                sort($ids);
                Wallet::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();

                // Created before the ledger mutation (balances filled in
                // right after) so debit()/credit() below can reference
                // this transfer's id, same "create the record first, then
                // point the ledger entries at it" pattern used for
                // Subscription purchases.
                $transfer = WalletTransfer::create([
                    'sender_user_id' => $sender->id,
                    'sender_wallet_id' => $senderWallet->id,
                    'receiver_user_id' => $receiver->id,
                    'receiver_wallet_id' => $receiverWallet->id,
                    'amount' => $amount,
                    'note' => $validated['note'] ?? null,
                    'sender_balance_before' => $senderWallet->balance,
                    'sender_balance_after' => $senderWallet->balance,
                    'receiver_balance_before' => $receiverWallet->balance,
                    'receiver_balance_after' => $receiverWallet->balance,
                    'status' => 'SUCCESS',
                ]);

                $senderEntry = WalletLedgerService::debit(
                    $senderWallet,
                    $amount,
                    WalletTransfer::class,
                    $transfer->id,
                    "Transfer ke {$receiver->name}",
                    $sender->id,
                    'TRANSFER_OUT'
                );

                $receiverEntry = WalletLedgerService::credit(
                    $receiverWallet,
                    $amount,
                    WalletTransfer::class,
                    $transfer->id,
                    "Transfer dari {$sender->name}",
                    $sender->id,
                    'TRANSFER_IN'
                );

                $transfer->update([
                    'sender_balance_before' => $senderEntry->balance_before,
                    'sender_balance_after' => $senderEntry->balance_after,
                    'receiver_balance_before' => $receiverEntry->balance_before,
                    'receiver_balance_after' => $receiverEntry->balance_after,
                ]);

                AuditLog::create([
                    'actor_type' => $sender::class,
                    'actor_id' => $sender->id,
                    'action' => 'WALLET_TRANSFER_SUCCESS',
                    'entity_type' => WalletTransfer::class,
                    'entity_id' => $transfer->id,
                    'new_value' => [
                        'receiver_id' => $receiver->id,
                        'amount' => $amount,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $transfer;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dashboard.wallet-transfer.success', $transfer->id)
            ->with('success', 'Transfer berhasil!');
    }

    public function success(string $transfer): View
    {
        $transfer = WalletTransfer::with(['sender', 'receiver'])->findOrFail($transfer);

        abort_unless(
            in_array(Auth::id(), [$transfer->sender_user_id, $transfer->receiver_user_id], true),
            403
        );

        return view('dashboard.wallet-transfer.success', compact('transfer'));
    }

    private function maskContact(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '') {
            return $email;
        }

        $visible = min(2, strlen($local));

        return substr($local, 0, $visible) . str_repeat('*', max(strlen($local) - $visible, 1)) . '@' . $domain;
    }
}
