<?php

namespace App\Services\Package;

use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\Package;
use App\Models\PackageTrialClaim;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
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
 *   - Satu branch boleh berlangganan beberapa layanan (Chat, Form,
 *     Jadwal, Tagihan) lewat paket terpisah -- aturannya 1 paket aktif
 *     PER LAYANAN. Paket bentrok kalau layanannya beririsan
 *     (Package::categoryIds()).
 *   - Tidak ada upgrade/downgrade per layanan: selama layanan X aktif
 *     dengan paket P, layanan itu hanya boleh diperpanjang dengan paket P
 *     yang sama. Ganti ke paket lain baru bisa setelah masa aktifnya habis.
 *   - Pengecualian: paket TRIAL (packages.is_trial) boleh langsung
 *     diganti paket lain; trial yang layanannya beririsan diakhiri saat
 *     paket baru di-redeem (endActiveTrials).
 *   - Trial hanya sekali per nomor HP owner (claimTrial).
 */
class BranchSubscriptionService
{
    /**
     * Branch milik company beserta SEMUA voucher yang sedang aktif di
     * branch itu (satu per paket, bisa kosong).
     *
     * @return Collection<int, array{branch: BranchOffice, vouchers: Collection<int, Voucher>}>
     */
    public function branchesWithActiveVouchers(Company $company): Collection
    {
        $branches = $company->branchOffices()->orderBy('created_at')->orderBy('id')->get();

        $vouchers = $this->activeVouchersQuery($company)
            ->whereIn('branch_office_id', $branches->pluck('id'))
            ->get()
            ->filter(fn (Voucher $voucher) => $voucher->package)
            ->groupBy('branch_office_id');

        return $branches->map(fn (BranchOffice $branch) => [
            'branch' => $branch,
            'vouchers' => $vouchers->get($branch->id, collect())->unique('package_id')->values(),
        ]);
    }

    /**
     * Voucher aktif (dari $activeVouchers) yang menghalangi $package:
     * paket LAIN, bukan trial, dan layanannya beririsan. Null = boleh.
     * Satu sumber untuk dropdown checkout dan pengecekan server.
     *
     * @param  Collection<int, Voucher>  $activeVouchers
     */
    public function conflictFor(Collection $activeVouchers, Package $package): ?Voucher
    {
        $wanted = $package->categoryIds();

        return $activeVouchers->first(fn (Voucher $voucher) => $voucher->package
            && ! $voucher->package->is_trial
            && $voucher->package_id !== $package->id
            && array_intersect($voucher->package->categoryIds(), $wanted) !== []);
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
     * RuntimeException kalau layanan paket ini masih aktif dengan paket
     * LAIN di branch ini (lihat conflictFor). $ignoreVoucherId dipakai saat
     * redeem (voucher yang sedang di-redeem sendiri tidak dihitung).
     */
    public function assertCanActivate(Company $company, BranchOffice $branch, Package $package, ?string $ignoreVoucherId = null): void
    {
        $active = $this->activeVouchersQuery($company)
            ->where('branch_office_id', $branch->id)
            ->when($ignoreVoucherId, fn ($q) => $q->whereKeyNot($ignoreVoucherId))
            ->get();

        if ($conflict = $this->conflictFor($active, $package)) {
            throw new RuntimeException(sprintf(
                'Layanan %s di branch %s masih aktif dengan paket %s sampai %s. Selama masih aktif, layanan itu hanya bisa diperpanjang dengan paket yang sama. Layanan lain tetap bisa Anda tambahkan.',
                $conflict->package->categoryNames(),
                $branch->name,
                $conflict->package->name,
                $conflict->valid_until->format('d M Y H:i')
            ));
        }
    }

    /**
     * Akhiri trial yang masih aktif di branch ini begitu paket lain
     * dengan layanan yang BERIRISAN diaktifkan, supaya satu layanan tidak
     * punya dua paket aktif sekaligus. Trial layanan lain tetap jalan.
     * Dipanggil di dalam transaksi redeem (baris branch sudah dikunci).
     */
    public function endActiveTrials(BranchOffice $branch, Voucher $activated): void
    {
        $activatedCategories = $activated->package->categoryIds();

        $trialIds = Voucher::query()
            ->currentlyActive()
            ->where('branch_office_id', $branch->id)
            ->where('package_id', '!=', $activated->package_id)
            ->whereKeyNot($activated->id)
            ->whereHas('package', fn ($q) => $q->where('is_trial', true))
            ->with(['package.categoryApplications'])
            ->get()
            ->filter(fn (Voucher $trial) => array_intersect($trial->package->categoryIds(), $activatedCategories) !== [])
            ->modelKeys();

        if ($trialIds !== []) {
            Voucher::whereKey($trialIds)->update(['status' => 'inactive', 'valid_until' => now()]);
        }
    }

    /** Voucher aktif company beserta paket & layanannya, masa aktif terpanjang dulu. */
    private function activeVouchersQuery(Company $company): Builder
    {
        return Voucher::query()
            ->currentlyActive()
            ->where('company_id', $company->id)
            ->with(['package.categoryApplications', 'package.categoryApplication'])
            ->orderByDesc('valid_until');
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
