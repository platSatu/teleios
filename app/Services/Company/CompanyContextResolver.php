<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\CompanyToUser;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves App\Services\Company\CompanyContext for the logged-in user —
 * the single place that answers "which company, which role, which
 * branch/unit" instead of every controller assuming the user IS the
 * company owner (the old `ownedCompanyOrFail()` pattern, duplicated
 * identically across 13 controllers, which 404s for anyone who isn't
 * literally Company.user_id).
 *
 * Two paths:
 * - Owner: `Company::where('user_id', $user->id)` — unchanged from
 *   before, still how an owner's own company is found.
 * - Member: `CompanyToUser::where('user_id', $user->id)` — NEW. A user
 *   invited via User\Profile\CompanyUserController has no Company of
 *   their own; this is the only way their access resolves at all.
 *
 * A user is assumed to have at most one meaningfully "active" company
 * context at a time for these purposes — if they own a company, that
 * wins (an owner acting as a guest member elsewhere isn't a scenario
 * this app's UI exposes yet). If they don't own one, their first active
 * CompanyToUser row is used. A member's role/branch/unit is the same
 * across every CompanyToUser row they have for one company (one row per
 * CategoryApplication, but role/branch/unit are duplicated identically
 * across them by CompanyUserController::store()), so picking "the
 * first" row is safe — they're never in conflict.
 */
class CompanyContextResolver
{
    public function resolve(User $user, ?string $companyId = null): ?CompanyContext
    {
        $ownedCompany = $companyId
            ? Company::where('user_id', $user->id)->where('id', $companyId)->first()
            : Company::where('user_id', $user->id)->first();

        if ($ownedCompany) {
            return new CompanyContext(
                company: $ownedCompany,
                isOwner: true,
            );
        }

        $membershipQuery = CompanyToUser::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['company', 'role', 'branchOffice', 'branchOfficeUnit']);

        if ($companyId) {
            $membershipQuery->where('company_id', $companyId);
        }

        $membership = $membershipQuery->first();

        if (! $membership || ! $membership->company) {
            return null;
        }

        return new CompanyContext(
            company: $membership->company,
            isOwner: false,
            role: $membership->role,
            branchOffice: $membership->branchOffice,
            branchOfficeUnit: $membership->branchOfficeUnit,
            membership: $membership,
        );
    }

    /**
     * Same as resolve(), but aborts with 403 instead of returning null —
     * the drop-in replacement for every controller's old
     * `ownedCompanyOrFail(Request $request): Company` method. Callers
     * that used to do `$company = $this->ownedCompanyOrFail($request)`
     * now do `$context = $resolver->resolveOrFail($request)` and read
     * `$context->company` (plus `$context->role`/`branchOffice` where
     * relevant).
     */
    public function resolveOrFail(Request $request, ?string $companyId = null): CompanyContext
    {
        $context = $this->resolve($request->user(), $companyId);

        abort_if($context === null, 403, 'Anda tidak terdaftar pada company manapun.');

        return $context;
    }
}
