<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Concerns\VerifiesTransactionPin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Setting;
use App\Models\User;
use App\Services\Wallet\BankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Superadmin: verifikasi rekening yang namanya berbeda, riwayat rekening
 * per user, buka nomor lengkap (tercatat), reset nama terverifikasi, dan
 * pengaturan masa tunggu/jeda ganti. Aturan di BankAccountService.
 */
class BankAccountController extends Controller
{
    use VerifiesTransactionPin;

    public function __construct(private readonly BankAccountService $accounts)
    {
    }

    public function index(Request $request): View
    {
        $pending = BankAccount::with('user:id,name,email,verified_bank_name')
            ->where('status', BankAccount::STATUS_PENDING_REVIEW)
            ->oldest()
            ->get();

        $search = trim((string) $request->query('search'));
        $recent = BankAccount::with('user:id,name,email')
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.bank-account.index', [
            'pending' => $pending,
            'recent' => $recent,
            'shared' => $this->accounts->sharedCounts($pending->concat($recent->items())),
            'settings' => BankAccountService::settings(),
        ]);
    }

    public function history(string $userId): View
    {
        $user = User::findOrFail($userId);
        $accounts = $user->bankAccounts()->with('reviewer:id,name')->latest()->get();

        return view('superadmin.bank-account.history', [
            'user' => $user,
            'accounts' => $accounts,
            'shared' => $this->accounts->sharedCounts($accounts),
            'resets' => AuditLog::with('user:id,name')
                ->where('entity_type', User::class)
                ->where('entity_id', $user->id)
                ->where('action', 'bank_account.reset_name')
                ->latest('created_at')
                ->get(),
        ]);
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        if ($failed = $this->failedTransactionPin($request)) {
            return $failed;
        }

        try {
            $account = $this->accounts->approve($id, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Rekening {$account->masked()} a.n. {$account->account_name} disetujui dan langsung aktif.");
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [
            'reason.required' => 'Tulis alasan penolakan, alasan ini akan dikirim ke user.',
        ]);

        try {
            $this->accounts->reject($id, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rekening ditolak dan user sudah diberi kabar lewat email.');
    }

    public function reveal(string $id): JsonResponse
    {
        return response()->json(['number' => $this->accounts->reveal(BankAccount::findOrFail($id))])
            ->header('Cache-Control', 'no-store');
    }

    public function resetName(Request $request, string $userId): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->accounts->resetVerifiedName(User::findOrFail($userId), $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Nama terverifikasi direset. Rekening berikutnya dari user ini akan menjadi patokan nama baru.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_hold_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'bank_account_change_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $before = BankAccountService::settings();

        foreach ($validated as $key => $value) {
            Setting::set($key, (string) $value);
        }

        AuditLog::record('bank_account_setting.update', Setting::class, 'bank_account_setting', $before, $validated);

        return back()->with('success', 'Pengaturan rekening berhasil disimpan.');
    }
}
