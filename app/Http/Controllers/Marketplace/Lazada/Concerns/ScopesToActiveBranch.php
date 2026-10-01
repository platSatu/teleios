<?php

namespace App\Http\Controllers\Marketplace\Lazada\Concerns;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Models\BranchOffice;
use App\Models\MarketplaceShop;
use Illuminate\Http\Request;

/**
 * Menu Lazada selalu bekerja pada BRANCH YANG SEDANG DIBUKA (pemilih branch
 * di header) -- sama dengan paket yang berlaku per branch, lihat
 * App\Http\Middleware\EnsureActivePackage. Member yang terkunci ke satu
 * branch otomatis hanya melihat branch-nya sendiri.
 */
trait ScopesToActiveBranch
{
    use ResolvesCompanyContext;

    protected function activeBranchOrFail(Request $request): BranchOffice
    {
        $branch = $this->companyContext($request)->activeBranch();

        abort_unless($branch, 403, 'Pilih branch terlebih dahulu.');

        return $branch;
    }

    protected function lazadaShopsQuery(BranchOffice $branch)
    {
        return MarketplaceShop::where('company_id', $branch->company_id)
            ->where('branch_office_id', $branch->id)
            ->where('provider', MarketplaceShop::PROVIDER_LAZADA);
    }

    protected function findLazadaShopOrFail(Request $request, string $id): MarketplaceShop
    {
        return $this->lazadaShopsQuery($this->activeBranchOrFail($request))->findOrFail($id);
    }
}
