<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\CompanyToUser;
use App\Models\User;
use App\Services\Company\CompanyContext;
use App\Services\Company\CompanyContextResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Drop-in replacement for the old, duplicated-13-times private method:
 *
 *     private function ownedCompanyOrFail(Request $request): Company
 *     {
 *         return Company::where('user_id', $request->user()->id)->firstOrFail();
 *     }
 *
 * That only ever worked for the literal company owner — anyone invited
 * via User\Profile\CompanyUserController (an Admin/Staff member) got a
 * 404 on every controller using it, since they don't own a Company row
 * themselves. This trait keeps the same `ownedCompanyOrFail()` method
 * name/signature so every existing call site keeps working unchanged,
 * but resolves through App\Services\Company\CompanyContextResolver
 * instead — which also checks App\Models\CompanyToUser, so members
 * resolve to their company too.
 *
 * Use `companyContext($request)` directly (instead of
 * `ownedCompanyOrFail()`) wherever a controller also needs to know the
 * caller's role/branch/unit — e.g. User\Profile\CompanyUserController
 * locking branch_office_id to the acting member's own branch.
 */
trait ResolvesCompanyContext
{
    /**
     * Passes session('active_company_id') through to the resolver so
     * every tab past "Company" (Branch Office, Unit/Divisi, Roles,
     * Applications, Setting Users) stays scoped to whichever company the
     * owner picked via a row action on the Company tab — see
     * User\Profile\ProfileController::index()'s docblock. Falls back to
     * CompanyContextResolver's own default ("first owned company") when
     * nothing's been picked yet, same as before a user ever had more
     * than one company to choose from.
     */
    protected function companyContext(Request $request): CompanyContext
    {
        return app(CompanyContextResolver::class)->resolveOrFail($request, session('active_company_id'));
    }

    protected function ownedCompanyOrFail(Request $request): Company
    {
        return $this->companyContext($request)->company;
    }

    /**
     * Every real (non-invited-but-pending) user who could plausibly be
     * assigned a contact or a chat: the company owner, plus every member
     * with a CompanyToUser row — deduplicated, since a member can have
     * several rows (one per CategoryApplication granted, see
     * App\Models\CompanyToUser's docblock). Used by the Kontak page and
     * the Inbox detail panel's assignee dropdown.
     *
     * $branchOfficeId narrows to members locked to that one branch (plus
     * the owner, who is never branch-locked) — pass the acting user's own
     * CompanyContext->branchOffice?->id to keep the dropdown to "people
     * who can actually see this contact", or leave null for every member
     * company-wide.
     *
     * @return Collection<int, User>
     */
    protected function companyTeamMembers(Company $company, ?string $branchOfficeId = null): Collection
    {
        $memberQuery = CompanyToUser::where('company_id', $company->id)
            ->where('status', 'active');

        if ($branchOfficeId) {
            $memberQuery->where(function ($q) use ($branchOfficeId) {
                $q->where('branch_office_id', $branchOfficeId)
                    ->orWhereNull('branch_office_id');
            });
        }

        $memberUserIds = $memberQuery->pluck('user_id');

        $userIds = $memberUserIds->push($company->user_id)->unique()->values();

        return User::whereIn('id', $userIds)->orderBy('name')->get();
    }
}
