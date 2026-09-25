<?php

namespace App\Services\Package;

use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\Package;
use App\Models\PackageTrialClaim;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Aturan pembelian & aktivasi paket PER BRANCH (alur Company -> Branch ->
 * Paket). Dipakai bersama checkout (Dashboard\PackageCheckoutController)
 * dan redeem voucher (Dashboard\VoucherRedeemController) supaya aturannya
 * satu sumber:
 *
 *   - Paket selalu untuk satu branch milik company owner.
 *   - Tidak ada upgrade/downgrade: selama branch masih aktif dengan
 *     paket X, branch itu hanya boleh memperpanjang paket X yang sama.
 *     Ganti ke paket lain baru bisa setelah masa aktifnya habis. Ini juga
 *     yang menjamin satu branch tidak pernah punya dua paket berbeda yang
 *     aktif bersamaan.
 *   - Pengecualian: paket TRIAL (packages.is_trial) boleh langsung
 *     diganti paket lain; trial-nya diakhiri saat paket baru di-redeem
 *     (endActiveTrials).
 *   - Trial hanya sekali per nomor HP owner (claimTrial).
 */
class BranchSubscriptionService
{
    /**
     * Branch milik company beserta voucher yang sedang aktif (atau null).
     *
     * @return Collection<int, array{branch: BranchOffice, voucher: ?Voucher}>
     */
    public function branchesWithActiveVoucher(Company $company): Collection
    {
        $branches = $company->branchOffices()->orderBy('created_at')->orderBy('id')->get();

        $vouchers = Voucher::query()
            ->currentlyActive()
            ->where('company_id', $company->id)
            ->whereIn('branch_office_id', $branches->pluck('id'))
            ->with('package:id,name,is_trial')
            ->orderByDesc('valid_from')
            ->get()
            ->unique('branch_office_id')
            ->keyBy('branch_office_id');

        return $branches->map(fn (BranchOffice $branch) => [
            'branch' => $branch,
            'voucher' => $vouchers->get($branch->id),
        ]);
    }

    /**
     * Branch milik company ini, atau RuntimeException kalau bukan.
     */
    public function branchOfCompanyOrFail(Company $company, ?string $branchOfficeId): BranchOffice
    {
        $branch = $branchOfficeId
            ? $company->branchOffices()->whereKey($branchOfficeId)->first()
            : null;

        if (! $branch) {
            throw new RuntimeException('Pilih branch tujuan paket terlebih dahulu.');
        }

        return $branch;
    }

    /**
     * RuntimeException kalau branch masih aktif dengan paket LAIN
     * (paket trial yang masih aktif tidak menghalangi).
     * $ignoreVoucherId dipakai saat redeem (voucher yang sedang di-redeem
     * sendiri tidak dihitung).
     */
    public function assertCanActivate(Company $company, BranchOffice $branch, Package $package, ?string $ignoreVoucherId = null): void
    {
        $active = Voucher::query()
            ->currentlyActive()
            ->where('company_id', $company->id)
            ->where('branch_office_id', $branch->id)
            ->where('package_id', '!=', $package->id)
            ->whereHas('package', fn ($q) => $q->where('is_trial', false))
            ->when($ignoreVoucherId, fn ($q) => $q->whereKeyNot($ignoreVoucherId))
            ->with('package:id,name')
            ->orderByDesc('valid_until')
            ->first();

        if ($active) {
            throw new RuntimeException(sprintf(
                'Branch %s masih aktif dengan paket %s sampai %s. Selama masih aktif, branch ini hanya bisa memperpanjang paket yang sama.',
                $branch->name,
                $active->package?->name ?? '-',
                $active->valid_until->format('d M Y H:i')
            ));
        }
    }

    /**
     * Akhiri trial yang masih aktif di branch ini begitu paket lain
     * diaktifkan, supaya branch tidak punya dua paket aktif sekaligus.
     * Dipanggil di dalam transaksi redeem (baris branch sudah dikunci).
     */
    public function endActiveTrials(BranchOffice $branch, Voucher $activated): void
    {
        Voucher::query()
            ->currentlyActive()
            ->where('branch_office_id', $branch->id)
            ->where('package_id', '!=', $activated->package_id)
            ->whereKeyNot($activated->id)
            ->whereHas('package', fn ($q) => $q->where('is_trial', true))
            ->update(['status' => 'inactive', 'valid_until' => now()]);
    }

    /**
     * Cek awal (tanpa lock) supaya pesan error muncul sebelum proses
     * checkout. Penjaga sebenarnya adalah unique index di claimTrial().
     */
    public function assertTrialAvailable(User $user): void
    {
        $phone = $this->trialPhoneOrFail($user);

        if (PackageTrialClaim::where('phone', $phone)->exists()) {
            throw new RuntimeException('Nomor HP ini sudah pernah memakai paket trial. Trial hanya bisa diambil sekali.');
        }
    }

    /**
     * Catat pemakaian trial untuk nomor HP owner. Dipanggil di dalam
     * transaksi checkout: kalau nomor sudah pernah klaim (termasuk dua
     * request bersamaan), unique index menolak dan seluruh checkout
     * di-rollback.
     */
    public function claimTrial(User $user, Company $company, BranchOffice $branch, Package $package, Subscription $subscription): void
    {
        try {
            PackageTrialClaim::create([
                'phone' => $this->trialPhoneOrFail($user),
                'user_id' => $user->id,
                'company_id' => $company->id,
                'branch_office_id' => $branch->id,
                'package_id' => $package->id,
                'subscription_id' => $subscription->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new RuntimeException('Nomor HP ini sudah pernah memakai paket trial. Trial hanya bisa diambil sekali.');
        }
    }

    /**
     * Nomor HP owner dalam format 62xxxxxxxx (0812.. / +62 812.. / 812..
     * dianggap nomor yang sama).
     */
    private function trialPhoneOrFail(User $user): string
    {
        $digits = preg_replace('/\D+/', '', (string) $user->handphone);

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        if (strlen($digits) < 10) {
            throw new RuntimeException('Lengkapi nomor HP di profil Anda terlebih dahulu untuk mengambil paket trial.');
        }

        return $digits;
    }
}
