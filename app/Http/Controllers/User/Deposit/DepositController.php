<?php

namespace App\Http\Controllers\User\Deposit;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\DuitkuSetting;
use App\Models\PaymentTransaction;
use App\Models\TransactionStatusHistory;
use App\Services\Payment\DuitkuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Wallet top-up, now backed by Duitku (POP flow) instead of the old
 * instant-SUCCESS manual simulation. Flow:
 *
 *   1. create()  — amount input form.
 *   2. store()   — creates a PENDING Deposit and sends the user to...
 *   3. checkout()        — our own confirmation page ("you're about to
 *      pay via Duitku, still want to continue?") before anything talks
 *      to Duitku, so a submit doesn't immediately leave the app.
 *   4. proceedToDuitku() — only once the user confirms on checkout():
 *      calls Duitku's createInvoice and renders Duitku's own in-page
 *      "popup" checkout widget (see resources/views/user/deposit/
 *      pay.blade.php) instead of a full-page redirect to Duitku's
 *      hosted payment page — paymentUrl is kept as a plain-link
 *      fallback on that same page for when the widget script fails to
 *      load.
 *   5. returnFromDuitku() — where Duitku sends the BROWSER back after a
 *      payment attempt. Purely informational — it does NOT credit the
 *      wallet. The server-to-server webhook (DuitkuCallbackController)
 *      is the only thing allowed to do that, since a browser redirect
 *      can't be trusted the way a signed callback can.
 *
 * The old pay() ("Simulasi Bayar" — instantly mark a deposit SUCCESS
 * and credit the wallet on the user's own say-so) has been removed now
 * that a real gateway is wired up: leaving a self-service "credit my
 * own wallet" endpoint live next to a real payment gateway would be a
 * standing way to mint free balance.
 */
class DepositController extends Controller
{
    /**
     * Top-up form. Kept focused on just topping up (current balance +
     * amount input + submit) — history moved to its own page
     * (history(), below) so this one isn't cluttered with a table.
     */
    public function create(): View
    {
        $walletBalance = Auth::user()->wallet->balance ?? 0;

        return view('user.deposit.topup', compact('walletBalance'));
    }

    /**
     * This user's own deposit history (scoped to Auth::id() only —
     * never another user's deposits). Separate page from create() so
     * the top-up form stays a simple, focused form.
     */
    public function history(): View
    {
        $deposits = Deposit::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('user.deposit.history', compact('deposits'));
    }

    /**
     * Creates the Deposit as PENDING and hands off to the checkout
     * confirmation page — no Duitku call happens yet. Separating "user
     * committed to an amount" from "user confirmed they want to pay
     * now" means an abandoned top-up still leaves a traceable PENDING
     * row instead of either silently vanishing or (the old behaviour)
     * being instantly marked SUCCESS with no real payment involved.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000',
        ]);

        $deposit = DB::transaction(function () use ($request) {
            $deposit = Deposit::create([
                'user_id' => Auth::id(),
                'idempotency_key' => Str::uuid(),
                'amount' => $request->amount,
                'currency' => 'IDR',
                'payment_provider' => 'DUITKU',
                'status' => 'PENDING',
                'metadata' => [
                    'source' => 'DUITKU_TOPUP',
                ],
            ]);

            AuditLog::create([
                'actor_type' => 'USER',
                'actor_id' => Auth::id(),
                'action' => 'CREATE_DEPOSIT_PENDING',
                'entity_type' => 'Deposit',
                'entity_id' => $deposit->id,
                'new_value' => [
                    'amount' => $deposit->amount,
                    'status' => 'PENDING',
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            TransactionStatusHistory::create([
                'entity_type' => Deposit::class,
                'entity_id' => $deposit->id,
                'old_status' => null,
                'new_status' => 'PENDING',
                'changed_by' => Auth::id(),
            ]);

            return $deposit;
        });

        return redirect()->route('deposit.checkout', $deposit);
    }

    /**
     * Confirmation step before anything reaches Duitku. Scoped to the
     * owner's own PENDING deposits — a deposit that's already
     * SUCCESS/FAILED can't be "checked out" again from here.
     */
    public function checkout(Deposit $deposit): View|RedirectResponse
    {
        abort_unless($deposit->user_id === Auth::id(), 403);

        if ($deposit->status !== 'PENDING' || $this->hasExpiredWindow($deposit)) {
            return redirect()
                ->route('deposit.history')
                ->with('error', 'Deposit ini sudah tidak menunggu pembayaran.');
        }

        $checkoutTimeoutMinutes = (int) config('services.duitku.checkout_timeout_minutes', 10);

        // Keterangan biaya QRIS hanya tampil kalau superadmin mengaktifkan
        // "dibebankan ke customer" (Pengaturan Duitku). Biaya dipotong dari
        // saldo SETELAH pembayaran QRIS berhasil (DuitkuCallbackController).
        $qrisFeePercent = DuitkuSetting::current()->activeQrisFeePercent();
        $qrisFee = DuitkuSetting::qrisFeeFor((int) round((float) $deposit->amount), $qrisFeePercent);

        return view('user.deposit.checkout', compact('deposit', 'checkoutTimeoutMinutes', 'qrisFee', 'qrisFeePercent'));
    }

    /**
     * Closes out a PENDING deposit without ever reaching Duitku —
     * either the user pressed "Batalkan" on checkout(), or that page's
     * own countdown timer ran out and auto-submitted the same form.
     *
     * Hanya boleh SEBELUM invoice Duitku dibuat (provider_transaction_id
     * masih kosong). Setelah itu user mungkin sudah membayar -- misalnya
     * timer di tab lama ikut submit padahal pembayaran berjalan di tab
     * lain -- jadi deposit dibiarkan menunggu callback / habis waktu
     * sendiri (ProcessDepositExpiry). Update-nya bersyarat (atomic), jadi
     * tidak bisa menimpa status SUCCESS dari callback yang datang
     * bersamaan, dan double-submit cukup jadi no-op.
     */
    public function cancelCheckout(Deposit $deposit): RedirectResponse
    {
        abort_unless($deposit->user_id === Auth::id(), 403);

        $reason = 'Dibatalkan sebelum lanjut ke Duitku (waktu konfirmasi habis atau dibatalkan manual).';

        $cancelled = DB::transaction(function () use ($deposit, $reason) {
            $claimed = Deposit::whereKey($deposit->id)
                ->where('status', 'PENDING')
                ->whereNull('provider_transaction_id')
                ->update(['status' => 'FAILED', 'failure_reason' => $reason]);

            if ($claimed === 0) {
                return false;
            }

            TransactionStatusHistory::create([
                'entity_type' => Deposit::class,
                'entity_id' => $deposit->id,
                'old_status' => 'PENDING',
                'new_status' => 'FAILED',
                'changed_by' => Auth::id(),
            ]);

            AuditLog::create([
                'actor_type' => 'USER',
                'actor_id' => Auth::id(),
                'action' => 'CANCEL_DEPOSIT_CHECKOUT',
                'entity_type' => 'Deposit',
                'entity_id' => $deposit->id,
                'old_value' => ['status' => 'PENDING'],
                'new_value' => ['status' => 'FAILED'],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return true;
        });

        if ($cancelled) {
            return redirect()
                ->route('deposit.topup')
                ->with('error', 'Deposit dibatalkan. Tidak ada saldo yang terpotong, dan Anda bisa membuat deposit baru kapan saja.');
        }

        $history = redirect()->route('deposit.history');

        return match ($deposit->refresh()->status) {
            'PENDING' => $history->with('success', 'Deposit ini sudah diteruskan ke Duitku, jadi tidak kami batalkan. Kalau Anda sudah membayar, saldo akan masuk otomatis. Kalau belum, tagihannya akan berakhir sendiri saat waktunya habis.'),
            'SUCCESS' => $history->with('success', 'Pembayaran deposit ini sudah kami terima dan saldo Anda sudah bertambah.'),
            default => $history->with('error', 'Deposit ini sudah tidak menunggu pembayaran.'),
        };
    }

    /**
     * The user confirmed on checkout() — now (and only now) do we
     * actually call Duitku and show them the payment widget.
     */
    public function proceedToDuitku(Request $request, Deposit $deposit): View|RedirectResponse
    {
        abort_unless($deposit->user_id === Auth::id(), 403);

        if ($deposit->status !== 'PENDING' || $this->hasExpiredWindow($deposit)) {
            return redirect()
                ->route('deposit.history')
                ->with('error', 'Deposit ini sudah tidak menunggu pembayaran.');
        }

        $duitku = DuitkuService::make();

        // Simpan tarif QRIS yang BERLAKU saat customer melihat keterangannya,
        // supaya perubahan setting setelah ini tidak mengubah potongan untuk
        // deposit ini. Biaya baru dipotong di callback kalau ternyata QRIS.
        $duitkuSetting = DuitkuSetting::current();

        $deposit = DB::transaction(function () use ($deposit, $duitkuSetting) {
            $locked = Deposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'fee_amount' => 0,
                'payment_amount' => (int) round((float) $locked->amount),
                'payment_method' => null,
                'metadata' => array_merge((array) $locked->metadata, [
                    'qris_fee_percent' => $duitkuSetting->activeQrisFeePercent(),
                    'qris_payment_code' => $duitkuSetting->qris_payment_code,
                ]),
            ]);

            return $locked->fresh();
        });

        try {
            $result = $duitku->createInvoice($deposit);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('deposit.checkout', $deposit)
                ->with('error', 'Gagal menghubungi Duitku. Silakan coba lagi. (' . $e->getMessage() . ')');
        }

        // The widget only needs `reference` to open — paymentUrl is
        // just a fallback link on the same page, so its absence alone
        // isn't a failure.
        if ($result['statusCode'] !== '00' || ! $result['reference']) {
            PaymentTransaction::create([
                'reference_type' => Deposit::class,
                'reference_id' => $deposit->id,
                'provider' => 'DUITKU',
                'provider_transaction_id' => $result['reference'],
                'amount' => $deposit->chargedAmount(),
                'currency' => 'IDR',
                'status' => 'FAILED',
                'request_payload' => $result['request_payload'],
                'response_payload' => $result['raw'],
                'failure_reason' => $result['statusMessage'] ?? 'Duitku tidak mengembalikan reference pembayaran.',
            ]);

            return redirect()
                ->route('deposit.checkout', $deposit)
                ->with('error', $result['statusMessage'] ?? 'Duitku menolak permintaan pembayaran ini. Silakan coba lagi.');
        }

        DB::transaction(function () use ($deposit, $result) {
            PaymentTransaction::create([
                'reference_type' => Deposit::class,
                'reference_id' => $deposit->id,
                'provider' => 'DUITKU',
                'provider_transaction_id' => $result['reference'],
                'amount' => $deposit->chargedAmount(),
                'currency' => 'IDR',
                'status' => 'PENDING',
                'request_payload' => $result['request_payload'],
                'response_payload' => $result['raw'],
            ]);

            // Same window Duitku itself was just told to enforce (see
            // DuitkuService::createInvoice's 'expiryPeriod') — this is
            // what App\Console\Commands\ProcessDepositExpiry checks
            // every minute to send the "segera selesaikan pembayaran"
            // reminder and, once passed, flip this deposit to EXPIRED.
            $deposit->update([
                'provider_transaction_id' => $result['reference'],
                'expires_at' => now()->addMinutes((int) config('services.duitku.expiry_minutes', 60)),
            ]);
        });

        return view('user.deposit.pay', [
            'deposit' => $deposit,
            'reference' => $result['reference'],
            'paymentUrl' => $result['paymentUrl'],
            'widgetScriptUrl' => $duitku->widgetScriptUrl(),
        ]);
    }

    /**
     * Where Duitku sends the BROWSER back after a payment attempt
     * (success, failed, or the user just closing the tab — Duitku
     * doesn't distinguish here). Informational only: the wallet is
     * credited exclusively by DuitkuCallbackController's server-to-
     * server webhook, which may not have arrived yet by the time the
     * browser lands here, so this never assumes the payment succeeded
     * just because the user made it back to this URL.
     */
    public function returnFromDuitku(Deposit $deposit): RedirectResponse
    {
        abort_unless($deposit->user_id === Auth::id(), 403);

        $deposit->refresh();

        return match ($deposit->status) {
            'SUCCESS' => redirect()
                ->route('deposit.history')
                ->with('success', 'Terima kasih, pembayaran Anda sudah kami terima dan saldo wallet sudah bertambah.'),
            'FAILED' => redirect()
                ->route('deposit.history')
                ->with('error', 'Pembayaran ini belum berhasil atau sudah dibatalkan. Kalau ternyata dana Anda sudah terpotong, saldo tetap akan masuk otomatis begitu Duitku mengonfirmasinya.'),
            'EXPIRED' => redirect()
                ->route('deposit.history')
                ->with('error', 'Waktu pembayaran sudah habis. Silakan buat deposit baru. Kalau Anda sempat membayar sebelum waktunya habis, saldo tetap akan masuk otomatis.'),
            default => redirect()
                ->route('deposit.history')
                ->with('success', 'Pembayaran Anda sedang dikonfirmasi oleh Duitku. Saldo akan bertambah otomatis, biasanya hanya dalam beberapa menit.'),
        };
    }

    /**
     * Defense-in-depth on top of App\Console\Commands\ProcessDepositExpiry:
     * that command only runs once a minute, so there's a short window
     * where a deposit's expires_at has already passed but its status
     * hasn't been flipped to EXPIRED yet. checkout()/proceedToDuitku()
     * both treat that window the same as an already-EXPIRED deposit —
     * "pembayaran yang sudah expired tidak dapat diteruskan kembali ke
     * Duitku" — rather than trusting the status column alone to always
     * be perfectly up to date.
     */
    private function hasExpiredWindow(Deposit $deposit): bool
    {
        return $deposit->expires_at !== null && $deposit->expires_at->isPast();
    }
}
