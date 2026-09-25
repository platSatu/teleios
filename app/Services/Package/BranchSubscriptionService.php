<?php

namespace App\Services\Package;

use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\Package;
use App\Models\Voucher;
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
            ->with('package:id,name')
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
     * RuntimeException kalau branch masih aktif dengan paket LAIN.
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
}
