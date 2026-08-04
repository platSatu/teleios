<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Services\Company\CompanyContext;
use App\Services\Company\CompanyContextResolver;
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
    protected function companyContext(Request $request): CompanyContext
    {
        return app(CompanyContextResolver::class)->resolveOrFail($request);
    }

    protected function ownedCompanyOrFail(Request $request): Company
    {
        return $this->companyContext($request)->company;
    }
}
