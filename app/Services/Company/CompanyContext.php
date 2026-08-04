<?php

namespace App\Services\Company;

use App\Models\BranchOffice;
use App\Models\BranchOfficeUnit;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\CompanyToUser;

/**
 * "Which company is this logged-in user acting under, and how far does
 * their access reach" — the answer App\Services\Company\
 * CompanyContextResolver produces for every request. Replaces the old
 * assumption (baked into 13 different controllers' ownedCompanyOrFail())
 * that the logged-in user always IS the company owner.
 *
 * Two shapes:
 * - Owner: isOwner=true, role/branchOffice/branchOfficeUnit are all
 *   null — an owner isn't scoped to any one branch, they're "pusat" and
 *   see everything across the whole company.
 * - Member: isOwner=false, role/branchOffice/branchOfficeUnit come from
 *   their App\Models\CompanyToUser row (branch/unit may still be null
 *   if the owner never assigned one when inviting them).
 */
final class CompanyContext
{
    public function __construct(
        public readonly Company $company,
        public readonly bool $isOwner,
        public readonly ?CompanyRole $role = null,
        public readonly ?BranchOffice $branchOffice = null,
        public readonly ?BranchOfficeUnit $branchOfficeUnit = null,
        public readonly ?CompanyToUser $membership = null,
    ) {
    }

    /**
     * Owner ("pusat") sees every branch. A member locked to a specific
     * branch does not — see CompanyUserController, which forces
     * branch_office_id to this value (instead of offering a picker) when
     * a non-owner member assigns a new user.
     */
    public function seesAllBranches(): bool
    {
        return $this->isOwner || $this->branchOffice === null;
    }

    public function isLockedToBranch(): bool
    {
        return ! $this->seesAllBranches();
    }
}
