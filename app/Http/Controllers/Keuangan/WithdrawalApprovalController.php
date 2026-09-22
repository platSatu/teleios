<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\WalletWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use RuntimeException;

/**
 * Antrean persetujuan "Tarik Saldo" -- satu halaman menampilkan SEMUA
 * permintaan pending_approval yang scope-nya boleh dilihat caller (lihat
 * WalletWithdrawalService::canApprove()), baik yang asalnya dari Wallet
 * pribadi pengajar/reseller (App\Http\Controllers\Wallet\
 * WalletWithdrawalController) maupun Wallet Branch (App\Http\
 * Controllers\Keuangan\BranchWithdrawalController) -- satu antrean,
 * bukan dua halaman terpisah.
 *
 * approve() WAJIB PIN transaksi (users.pin, sama persis pola & rate
 * limit dengan Dashboard\WalletTransferController) -- dari sini
 * langsung memicu panggilan Duitku Disbursement yang beneran mengirim
 * uang keluar, jadi diberi pengaman yang sama dengan Transfer Saldo
 * antar-user yang sudah ada, bukan cuma klik tombol biasa.
 */
class WithdrawalApprovalController extends Controller
{
    use ResolvesCompanyContext;

    private const MAX_PIN_ATTEMPTS = 5;

    private const PIN_LOCKOUT_SECONDS = 900;

    public function __construct(protected WalletWithdrawalService $service)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $isSuperadmin = $user->user_type === 'SUPERADMIN';

        $context = $isSuperadmin ? null : $this->companyContext($request);

        $query = WalletWithdrawal::query()->with(['wallet.user', 'branchOffice', 'requestedBy']);

        if ($context) {
            $query->where('company_id', $context->company->id);

            if ($context->isLockedToBranch()) {
                $query->where('branch_office_id', $context->branchOffice?->id);
            }
        }
        // $context null (superadmin) -- sengaja TIDAK difilter sama
        // sekali, termasuk permintaan tanpa company_id (reseller lepas)
        // yang tidak punya admin company manapun buat approve.

        $pending = (clone $query)->where('status', WalletWithdrawal::STATUS_PENDING_APPROVAL)->latest()->get();

        $riwayat = (clone $query)
            ->whereIn('status', [
                WalletWithdrawal::STATUS_SUCCESS,
                WalletWithdrawal::STATUS_FAILED,
                WalletWithdrawal::STATUS_REJECTED,
                WalletWithdrawal::STATUS_CANCELLED,
            ])
            ->latest()
            ->limit(30)
            ->get();

        return view('keuangan.withdrawal.approval', compact('pending', 'riwayat'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();

        if (is_null($user->pin)) {
            return redirect()
                ->route('user-settings.pin.edit')
                ->with('error', 'Buat PIN transaksi terlebih dahulu sebelum menyetujui tarik saldo.');
        }

        $validated = $request->validate(['pin' => ['required', 'digits:6']]);

        $withdrawal = WalletWithdrawal::findOrFail($id);

        if (! $this->authorize($request, $withdrawal)) {
            abort(403);
        }

        $rateLimitKey = 'withdrawal-approve-pin:'.$user->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_PIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()->with('error', "Terlalu banyak percobaan PIN salah. Coba lagi dalam {$seconds} detik.");
        }

        if (! Hash::check($validated['pin'], $user->pin)) {
            RateLimiter::hit($rateLimitKey, self::PIN_LOCKOUT_SECONDS);

            return back()->with('error', 'PIN salah.');
        }

        RateLimiter::clear($rateLimitKey);

        try {
            $result = $this->service->approveAndProcess($withdrawal, $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result->status === WalletWithdrawal::STATUS_SUCCESS) {
            return back()->with('success', 'Tarik saldo disetujui — dana sudah dikirim ke rekening tujuan.');
        }

        return back()->with(
            'error',
            'Disetujui, tapi transfer ke Duitku gagal: '.$result->failure_reason.' — saldo TIDAK terpotong. Coba lagi nanti, atau hubungi superadmin kalau berulang.'
        );
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $withdrawal = WalletWithdrawal::findOrFail($id);

        if (! $this->authorize($request, $withdrawal)) {
            abort(403);
        }

        try {
            $this->service->reject($withdrawal, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Permintaan tarik saldo ditolak.');
    }

    private function authorize(Request $request, WalletWithdrawal $withdrawal): bool
    {
        $user = $request->user();
        $context = $user->user_type === 'SUPERADMIN' ? null : $this->companyContext($request);

        return $this->service->canApprove($withdrawal, $user, $context);
    }
}
