<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Concerns\VerifiesTransactionPin;
use App\Http\Controllers\Controller;
use App\Services\Wallet\BankAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use RuntimeException;

/**
 * "Rekening Pencairan" milik user yang login, 2 langkah:
 *   1. check(): Bank + Nomor + PIN -> dicek ke bank, hasilnya disimpan di server.
 *   2. store(): user mencentang konfirmasi nama dari bank -> rekening disimpan.
 * Semua aturan ada di App\Services\Wallet\BankAccountService.
 */
class BankAccountController extends Controller
{
    use VerifiesTransactionPin;

    /** Cek ke bank dibatasi supaya fitur ini tidak dipakai untuk mencari nama pemilik rekening orang lain. */
    private const MAX_CHECKS_PER_HOUR = 5;

    public function __construct(private readonly BankAccountService $accounts)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $check = $this->accounts->pendingCheck($user);

        return view('wallet.bank-account.index', [
            'current' => $this->accounts->current($user),
            'submission' => $this->accounts->latestSubmission($user),
            'check' => $check,
            'blockedReason' => $check ? null : $this->accounts->blockedReason($user),
            'banks' => $check ? [] : $this->accounts->banks(),
            'verifiedName' => $user->verified_bank_name,
            'settings' => BankAccountService::settings(),
        ]);
    }

    public function check(Request $request): RedirectResponse
    {
        $request->merge(['account_number' => BankAccountService::normalizeNumber((string) $request->input('account_number'))]);

        $validated = $request->validate([
            'bank_code' => ['required', 'string', 'max:10'],
            'account_number' => ['required', 'digits_between:5,20'],
        ], [
            'account_number.required' => 'Masukkan nomor rekening.',
            'account_number.digits_between' => 'Nomor rekening tidak valid. Tulis angka saja, tanpa spasi atau titik.',
        ]);

        if ($failed = $this->failedTransactionPin($request)) {
            return $failed;
        }

        $user = $request->user();
        $back = back()->withInput($request->except('pin'));
        $key = 'bank-account-inquiry:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_CHECKS_PER_HOUR)) {
            return $back->with('error', 'Terlalu sering mengecek rekening. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.');
        }

        RateLimiter::hit($key, 3600);

        try {
            $check = $this->accounts->inquire($user, $validated['bank_code'], $validated['account_number']);
        } catch (RuntimeException $e) {
            return $back->with('error', $e->getMessage());
        }

        $this->accounts->rememberCheck($user, $check);

        return redirect()->route('wallet.bank-account.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Centang dulu pernyataan bahwa ini rekening milik Anda sendiri.',
        ]);

        $user = $request->user();
        $check = $this->accounts->pendingCheck($user);
        $this->accounts->forgetCheck($user);

        if (! $check) {
            return redirect()->route('wallet.bank-account.index')
                ->with('error', 'Waktu konfirmasi sudah habis. Silakan cek rekening sekali lagi.');
        }

        try {
            $account = $this->accounts->register($user, $check);
        } catch (RuntimeException $e) {
            return redirect()->route('wallet.bank-account.index')->with('error', $e->getMessage());
        }

        $name = $account->account_name;
        $activeAt = $account->active_at?->translatedFormat('d M Y, H:i');

        return redirect()->route('wallet.bank-account.index')->with('success', match ($account->state()) {
            'review' => "Rekening ini atas nama {$name}, berbeda dengan rekening Anda sebelumnya ({$user->verified_bank_name}). Kami akan memeriksanya terlebih dahulu, dan Anda akan diberi kabar lewat email.",
            'active' => "Rekening atas nama {$name} berhasil disimpan dan sudah bisa dipakai tarik saldo.",
            default => "Rekening atas nama {$name} berhasil disimpan. Bisa dipakai tarik saldo mulai {$activeAt}.",
        });
    }

    public function cancelCheck(Request $request): RedirectResponse
    {
        $this->accounts->forgetCheck($request->user());

        return redirect()->route('wallet.bank-account.index');
    }
}
