<?php

namespace App\Http\Controllers\User\Profile;

use App\Exports\CompanyUserImportTemplateExport;
use App\Exports\CompanyUsersExport;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Profile\Concerns\ScopesActivePackage;
use App\Imports\CompanyUsersImport;
use App\Models\BranchOffice;
use App\Models\BranchOfficeUnit;
use App\Models\CategoryApplication;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\CompanyToUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * CRUD for the "Setting Users" tab on dashboard/user/profile — who
 * belongs to the logged-in user's company, under which CompanyRole, and
 * which CategoryApplication(s) they're allowed to access. Always scoped
 * to Company::user_id === auth id, same rule as CompanyRoleController;
 * see that class for the full rationale.
 *
 * Unlike the original version of this controller (which invited an
 * ALREADY-registered user by email), adding a member here creates a
 * brand new App\Models\User row in the same request — company owners
 * are onboarding their own employees, who don't have an account yet, so
 * name/email/password go straight into `users` while
 * company_role_id/category_application_id go into `company_to_users`.
 * See store().
 *
 * A member can optionally be placed under one branch office and one
 * unit/divisi (branch_office_id/branch_office_unit_id on
 * company_to_users) — both nullable, validated via
 * validateBranchOfficeAndUnit() below so a branch office/unit can only
 * be picked from the caller's own company (and a unit only from within
 * its selected branch office).
 *
 * A member can be granted more than one CategoryApplication at once
 * (e.g. "Chat" AND "WhatsApp") — modeled as one company_to_users ROW
 * PER category rather than a list column, so store()/update() loop over
 * the submitted category_application_id[] and write one row per entry.
 * All of a member's rows always share the same company_role_id/status;
 * only category_application_id differs between them. Because of that,
 * the "member" identity used by edit/show/delete below is the target
 * user_id, not a single company_to_users row id.
 *
 * The owner's own membership row(s) (created alongside the company —
 * see ProfileController::updateCompany()) can't be edited or removed
 * through here, so an owner can never accidentally lock themselves out
 * of their own company.
 *
 * Every action here also requires the company owner to currently have at
 * least one active package (App\Http\Controllers\User\Profile\Concerns\
 * ScopesActivePackage) — the "Setting Users" tab is hidden in the view
 * when that's not the case, and category_application_id is validated
 * against ONLY the categories covered by an active package rather than
 * every category_applications row that exists, so this can't be
 * bypassed by posting to the route directly.
 *
 * Create/Edit are full pages (resources/views/user/profile/company-users/
 * create.blade.php + edit.blade.php), not modals on the profile page —
 * a Bootstrap modal isn't a great fit once the form has file upload
 * (n/a here) or just gets long, and a dedicated URL means validation
 * errors land on a page that only has ONE form on it, so the old
 * per-member `newMember`/`editMember{id}` error-bag dance (needed to
 * pick the right modal back open on the shared profile page) isn't
 * needed anymore — plain default-bag errors are enough.
 *
 * Bulk creation (import()) and CompanyUsersExport (export()) reuse the
 * exact same scoping/validation rules as store()/update() — see
 * App\Imports\CompanyUsersImport's docblock for the full security
 * rationale on that path specifically.
 */
class CompanyUserController extends Controller
{
    use ResolvesCompanyContext;

    use ScopesActivePackage;

    /**
     * Reached either from the "Tambah User" entry on the Setting Users
     * tab (no query string), or from the Applications tab's "Add User"
     * row action on a specific company_role_menus row — which passes
     * ?role=<company_role_id>&category=<category_application_id> so the
     * Role/Branch/Unit/Category fields below arrive pre-selected instead
     * of blank. Both are still plain <select>/checkbox inputs, not
     * locked/readonly — a new hire can always be placed under a
     * different role or an extra category before submitting; this is a
     * convenience default, not an enforced constraint (store() re-checks
     * everything server-side regardless).
     */
    public function create(Request $request): RedirectResponse|View
    {
        $context = $this->companyContext($request);
        $company = $context->company;
        $activeCategoryApplicationIds = $this->activeCategoryApplicationIds($company->user_id);

        if ($activeCategoryApplicationIds->isEmpty()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Anda belum memiliki package aktif. Beli package terlebih dahulu sebelum menambah user.');
        }

        // A non-owner (branch-locked) member only ever sees THEIR OWN
        // branch here — no picker, just straight to "pick a unit within
        // it" (see the view). The owner ("pusat") still sees every branch
        // and can place a new user under any of them.
        $branchOfficesQuery = $company->branchOffices()->with('units');

        if (! $context->isOwner) {
            $branchOfficesQuery->where('id', $context->branchOffice?->id);
        }

        $prefillRole = CompanyRole::where('company_id', $company->id)
            ->where('id', $request->query('role'))
            ->with('branchOfficeUnit')
            ->first();

        $prefillCategoryId = CategoryApplication::whereIn('id', $activeCategoryApplicationIds)
            ->where('id', $request->query('category'))
            ->value('id');

        return view('user.profile.company-users.create', [
            'company' => $company,
            'companyRoles' => $company->roles()->orderBy('name')->get(),
            'branchOffices' => $branchOfficesQuery->orderBy('name')->get(),
            'categoryApplications' => CategoryApplication::whereIn('id', $activeCategoryApplicationIds)->orderBy('name')->get(),
            'lockedBranchOffice' => $context->isOwner ? null : $context->branchOffice,
            'prefillRoleId' => $prefillRole?->id,
            'prefillBranchOfficeId' => $prefillRole?->branchOfficeUnit?->branch_office_id,
            'prefillUnitId' => $prefillRole?->branch_office_unit_id,
            'prefillCategoryId' => $prefillCategoryId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;
        $activeCategoryApplicationIds = $this->activeCategoryApplicationIds($company->user_id);

        if ($activeCategoryApplicationIds->isEmpty()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Anda belum memiliki package aktif. Beli package terlebih dahulu sebelum menambah user.');
        }

        // A branch-locked member can't assign a new user to any branch
        // but their own — merged into the request BEFORE validation so
        // nothing downstream (validateBranchOfficeAndUnit, the create
        // transaction) has to separately guard against a non-owner
        // smuggling a different branch_office_id into the POST body.
        // The owner ("pusat") is unrestricted and keeps whatever branch
        // the form actually submitted.
        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Optional — a member can be created without WhatsApp yet
            // and have it added later via edit(). Bare national number
            // only (no leading 0, no '62' country code), same shape/
            // regex as Auth\AuthController::register() — normalized to
            // the '62'-prefixed digit-only form every other phone number
            // in this app expects (see App\Jobs\Concerns\
            // NormalizesWhatsAppJid) right after validation, below.
            // WhatsApp-based notifications to this member (e.g. wallet/
            // deposit alerts) silently no-op for anyone without one, so
            // this is the field that actually makes that automation work
            // for a user created here.
            'handphone' => ['nullable', 'regex:/^[1-9][0-9]{9,13}$/'],
            'company_role_id' => ['required', 'uuid'],
            // Both optional — a member doesn't have to be placed under a
            // branch office/unit, e.g. a company that hasn't set up
            // branch offices yet. branch_office_unit_id only makes
            // sense alongside branch_office_id; enforced below.
            'branch_office_id' => ['nullable', 'uuid'],
            'branch_office_unit_id' => ['nullable', 'uuid'],
            'status' => ['required', 'in:active,inactive'],
            'category_application_id' => ['required', 'array', 'min:1'],
            'category_application_id.*' => ['uuid', Rule::in($activeCategoryApplicationIds->all())],
        ], [
            'handphone.regex' => 'Nomor WhatsApp harus 10-14 digit angka, tanpa awalan 0 atau kode negara 62 (contoh: 81286800080).',
        ]);

        $validator->after(function ($validator) use ($request, $company) {
            $role = CompanyRole::where('id', $request->input('company_role_id'))
                ->where('company_id', $company->id)
                ->first();

            if (! $role) {
                $validator->errors()->add('company_role_id', 'Role tidak valid.');
            }

            $this->validateBranchOfficeAndUnit($validator, $request, $company);
            $this->validateUniqueHandphone($validator, $request);
        });

        if ($validator->fails()) {
            return redirect()
                ->route('profile.company-users.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Same normalization as Auth\AuthController::register() — see
        // the field's validation comment above.
        $normalizedHandphone = filled($validated['handphone'] ?? null) ? '62'.$validated['handphone'] : null;

        DB::transaction(function () use ($validated, $company, $normalizedHandphone) {
            // password hashes automatically — User::casts() has
            // 'password' => 'hashed', so this plain create() call hashes
            // it on the way in (same pattern as Superadmin\UserController).
            $newUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'handphone' => $normalizedHandphone,
                'status' => 'active',
                'user_type' => 'USER',
            ]);

            // Owner-created accounts skip email verification: there's no
            // signup flow for this employee to click a link from, and
            // the owner adding them already vouches for the email being
            // real. Direct property assignment (not ->update()) because
            // email_verified_at isn't in User::$fillable.
            $newUser->email_verified_at = now();
            $newUser->save();

            foreach (array_unique($validated['category_application_id']) as $categoryId) {
                CompanyToUser::create([
                    'user_id' => $newUser->id,
                    'company_id' => $company->id,
                    'company_role_id' => $validated['company_role_id'],
                    'branch_office_id' => $validated['branch_office_id'] ?? null,
                    'branch_office_unit_id' => $validated['branch_office_unit_id'] ?? null,
                    'category_application_id' => $categoryId,
                    'status' => $validated['status'],
                ]);
            }
        });

        return redirect()
            ->route('profile.edit', ['tab' => 'users'])
            ->with('success', 'User baru berhasil dibuat dan ditambahkan ke company.');
    }

    /**
     * "Show" row action on the Setting Users tab — full breadcrumb
     * Company -> Branch -> Unit/Divisi -> Role -> Application(s), per
     * the same "Show" convention as every other tab (dedicated page, not
     * a modal). A member's rows all share one branch/unit/role (see this
     * class's docblock), so those come from $memberRows->first(); the
     * Application segment is plural since a member can be granted more
     * than one CategoryApplication — rendered as a list rather than a
     * single breadcrumb entry.
     */
    public function show(Request $request, string $userId): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $memberRowsQuery = CompanyToUser::where('company_id', $company->id)
            ->where('user_id', $userId)
            ->with(['user', 'role', 'categoryApplication', 'branchOffice', 'branchOfficeUnit']);

        if (! $context->isOwner) {
            $memberRowsQuery->where('branch_office_id', $context->branchOffice?->id);
        }

        $memberRows = $memberRowsQuery->get();

        if ($memberRows->isEmpty()) {
            abort(404);
        }

        $memberFirst = $memberRows->first();

        return view('user.profile.company-users.show', [
            'company' => $company,
            'branchOffice' => $memberFirst->branchOffice,
            'unit' => $memberFirst->branchOfficeUnit,
            'role' => $memberFirst->role,
            'member' => $memberFirst,
            'memberRows' => $memberRows,
        ]);
    }

    public function edit(Request $request, string $userId): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        if ($userId === $company->user_id) {
            abort(404);
        }

        $memberRowsQuery = CompanyToUser::where('company_id', $company->id)
            ->where('user_id', $userId)
            ->with(['user', 'categoryApplication']);

        // A branch-locked member can only reach members of THEIR OWN
        // branch — trying to edit someone in a different branch 404s,
        // same as if the row didn't exist at all (no leaking "this user
        // exists, just not in your branch" via a 403 instead of a 404).
        if (! $context->isOwner) {
            $memberRowsQuery->where('branch_office_id', $context->branchOffice?->id);
        }

        $memberRows = $memberRowsQuery->get();

        if ($memberRows->isEmpty()) {
            abort(404);
        }

        $activeCategoryApplicationIds = $this->activeCategoryApplicationIds($company->user_id);

        // The checkbox list shown on the edit page is the owner's active
        // package categories PLUS whatever this member is already
        // assigned — so a category that was active when the member was
        // added, but has since fallen out of the owner's package, still
        // shows up (checked) instead of silently vanishing and getting
        // dropped the moment this form is saved.
        $memberCategoryApplications = $memberRows->pluck('categoryApplication')->filter();
        $categoryApplications = CategoryApplication::whereIn('id', $activeCategoryApplicationIds)
            ->orWhereIn('id', $memberCategoryApplications->pluck('id'))
            ->orderBy('name')
            ->get();

        $branchOfficesQuery = $company->branchOffices()->with('units');

        if (! $context->isOwner) {
            $branchOfficesQuery->where('id', $context->branchOffice?->id);
        }

        return view('user.profile.company-users.edit', [
            'company' => $company,
            'companyRoles' => $company->roles()->orderBy('name')->get(),
            'branchOffices' => $branchOfficesQuery->orderBy('name')->get(),
            'categoryApplications' => $categoryApplications,
            'member' => $memberRows->first(),
            'memberCategoryIds' => $memberRows->pluck('category_application_id')->filter()->values()->all(),
            'lockedBranchOffice' => $context->isOwner ? null : $context->branchOffice,
        ]);
    }

    public function update(Request $request, string $userId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        if ($userId === $company->user_id) {
            return redirect()
                ->route('profile.edit', ['tab' => 'users'])
                ->with('error', 'Role dan status Owner tidak dapat diubah dari sini.');
        }

        $memberRowsQuery = CompanyToUser::where('company_id', $company->id)
            ->where('user_id', $userId);

        if (! $context->isOwner) {
            $memberRowsQuery->where('branch_office_id', $context->branchOffice?->id);
        }

        $memberRows = $memberRowsQuery->get();

        if ($memberRows->isEmpty()) {
            abort(404);
        }

        // A branch-locked member can't move this user to a different
        // branch either — same merge-before-validation approach as
        // store().
        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        // Same restriction as store(): a member's categories can only be
        // moved WITHIN the owner's currently active package(s) (or stay
        // on a category they already had — see edit() above), never
        // widened to a category application the owner never paid for.
        $activeCategoryApplicationIds = $this->activeCategoryApplicationIds($company->user_id)
            ->merge($memberRows->pluck('category_application_id')->filter())
            ->unique()
            ->values();

        $validator = Validator::make($request->all(), [
            'company_role_id' => ['required', 'uuid'],
            // Editable here (unlike name/email, which stay locked once
            // created — see edit()'s view) since this is exactly the
            // field that wasn't collected at all before this change, so
            // every member created earlier needs a way to have it added
            // after the fact for WhatsApp-based automation to work for
            // them. Same shape/normalization as store() — see that
            // method's comment.
            'handphone' => ['nullable', 'regex:/^[1-9][0-9]{9,13}$/'],
            'branch_office_id' => ['nullable', 'uuid'],
            'branch_office_unit_id' => ['nullable', 'uuid'],
            'status' => ['required', 'in:active,inactive'],
            'category_application_id' => ['required', 'array', 'min:1'],
            'category_application_id.*' => ['uuid', Rule::in($activeCategoryApplicationIds->all())],
        ], [
            'handphone.regex' => 'Nomor WhatsApp harus 10-14 digit angka, tanpa awalan 0 atau kode negara 62 (contoh: 81286800080).',
        ]);

        $validator->after(function ($validator) use ($request, $company, $userId) {
            $role = CompanyRole::where('id', $request->input('company_role_id'))
                ->where('company_id', $company->id)
                ->first();

            if (! $role) {
                $validator->errors()->add('company_role_id', 'Role tidak valid.');
            }

            $this->validateBranchOfficeAndUnit($validator, $request, $company);
            $this->validateUniqueHandphone($validator, $request, $userId);
        });

        if ($validator->fails()) {
            return redirect()
                ->route('profile.company-users.edit', $userId)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $newCategoryIds = array_unique($validated['category_application_id']);

        // Same normalization as store() — see that method's comment.
        // An empty submission CLEARS the number (explicit null), rather
        // than leaving whatever was there before untouched, so an admin
        // can actually correct/remove a wrong number from this form.
        $normalizedHandphone = filled($validated['handphone'] ?? null) ? '62'.$validated['handphone'] : null;

        User::where('id', $userId)->update(['handphone' => $normalizedHandphone]);

        DB::transaction(function () use ($company, $userId, $validated, $newCategoryIds) {
            // Update (or re-create) a row for every category still/newly
            // selected, then drop whichever of the member's existing
            // rows fell off the selection — a plain ->update() on the
            // existing rows can't handle the category list shrinking or
            // growing, so this reconciles the set instead.
            foreach ($newCategoryIds as $categoryId) {
                CompanyToUser::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'user_id' => $userId,
                        'category_application_id' => $categoryId,
                    ],
                    [
                        'company_role_id' => $validated['company_role_id'],
                        'branch_office_id' => $validated['branch_office_id'] ?? null,
                        'branch_office_unit_id' => $validated['branch_office_unit_id'] ?? null,
                        'status' => $validated['status'],
                    ]
                );
            }

            CompanyToUser::where('company_id', $company->id)
                ->where('user_id', $userId)
                ->whereNotIn('category_application_id', $newCategoryIds)
                ->delete();
        });

        return redirect()
            ->route('profile.edit', ['tab' => 'users'])
            ->with('success', 'Data user berhasil diperbarui.');
    }

    public function destroy(Request $request, string $userId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        if ($userId === $company->user_id) {
            return redirect()
                ->route('profile.edit', ['tab' => 'users'])
                ->with('error', 'Owner tidak dapat mengeluarkan dirinya sendiri dari company.');
        }

        $deletedQuery = CompanyToUser::where('company_id', $company->id)
            ->where('user_id', $userId);

        // Same branch lock as edit()/update(): a non-owner can only remove
        // members from their own branch.
        if (! $context->isOwner) {
            $deletedQuery->where('branch_office_id', $context->branchOffice?->id);
        }

        $deleted = $deletedQuery->delete();

        if (! $deleted) {
            abort(404);
        }

        return redirect()
            ->route('profile.edit', ['tab' => 'users'])
            ->with('success', 'User berhasil dikeluarkan dari company.');
    }

    /**
     * "Export" button — every current member of this company as an
     * .xlsx download. See App\Exports\CompanyUsersExport.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $filename = 'setting-users-' . $company->slug . '-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new CompanyUsersExport($company), $filename);
    }

    /**
     * "Download Template" inside the Import modal — headers +
     * example row, plus a reference sheet of the exact role/category
     * names this specific company can currently import with. See
     * App\Exports\CompanyUserImportTemplateExport.
     */
    public function importTemplate(Request $request): BinaryFileResponse
    {
        $company = $this->ownedCompanyOrFail($request);
        $activeCategoryApplicationIds = $this->activeCategoryApplicationIds($company->user_id);

        $export = new CompanyUserImportTemplateExport(
            $company->roles()->orderBy('name')->pluck('name'),
            CategoryApplication::whereIn('id', $activeCategoryApplicationIds)->orderBy('name')->pluck('name'),
        );

        return Excel::download($export, 'template-import-setting-users.xlsx');
    }

    /**
     * "Import" button — bulk-create members from an uploaded .xlsx/.csv
     * file. See App\Imports\CompanyUsersImport's docblock for the full
     * security rationale (row cap, per-row scoping to this company's
     * roles/active categories, duplicate-email handling, generated
     * passwords, per-row transactions).
     *
     * The result (how many rows were created, and the validation
     * messages for any rows that weren't) is flashed to the session as
     * `importResult` and rendered back on the Setting Users tab — see
     * resources/views/user/profile/index.blade.php.
     */
    public function import(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);
        $activeCategoryApplicationIds = $this->activeCategoryApplicationIds($company->user_id);

        if ($activeCategoryApplicationIds->isEmpty()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Anda belum memiliki package aktif. Beli package terlebih dahulu sebelum import user.');
        }

        // File type + size validated here (2MB cap) BEFORE it's ever
        // handed to the Excel reader — mimes: restricts to spreadsheet
        // formats only, nothing that could be mistaken for a script by a
        // misconfigured server. The row-count cap lives inside
        // CompanyUsersImport itself (needs the parsed sheet to count).
        //
        // Validator::make() instead of $request->validate() — same
        // reason as ProfileController::updateCompany(): plain validate()
        // redirects back() via the Referer header, which isn't
        // guaranteed to carry ?tab=users, and would silently strand the
        // error on the Profile tab instead of Setting Users where the
        // Import modal actually lives.
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'users'])
                ->withErrors($validator);
        }

        $import = new CompanyUsersImport(
            $company,
            $company->roles()->where('status', 'active')->get(),
            CategoryApplication::whereIn('id', $activeCategoryApplicationIds)->get(),
        );

        Excel::import($import, $request->file('file'));

        if ($import->tooManyRows) {
            return redirect()
                ->route('profile.edit', ['tab' => 'users'])
                ->with('error', 'File terlalu banyak baris (maksimal ' . CompanyUsersImport::MAX_ROWS . ' user per import). Tidak ada data yang disimpan — silakan pecah file jadi beberapa bagian.');
        }

        return redirect()
            ->route('profile.edit', ['tab' => 'users'])
            ->with('importResult', [
                'created' => $import->created,
                'errors' => $import->errors,
            ]);
    }

    /**
     * Shared by store()/update(): branch_office_id (if present) must
     * belong to the caller's own company, and branch_office_unit_id (if
     * present) must belong to that same branch office — `exists` rules
     * alone would let anyone place a member under a branch office/unit
     * they don't own just by guessing its uuid. A unit without its
     * parent branch office selected is also rejected, since the
     * unit-to-company chain only holds via branch_office_id.
     */
    private function validateBranchOfficeAndUnit($validator, Request $request, Company $company): void
    {
        $branchOfficeId = $request->input('branch_office_id');
        $branchOfficeUnitId = $request->input('branch_office_unit_id');

        if (! $branchOfficeId) {
            if ($branchOfficeUnitId) {
                $validator->errors()->add('branch_office_id', 'Pilih branch office terlebih dahulu.');
            }

            return;
        }

        if ($validator->errors()->has('branch_office_id')) {
            return;
        }

        $branchOffice = BranchOffice::where('company_id', $company->id)
            ->where('id', $branchOfficeId)
            ->first();

        if (! $branchOffice) {
            $validator->errors()->add('branch_office_id', 'Branch office tidak valid.');

            return;
        }

        if ($branchOfficeUnitId && ! $validator->errors()->has('branch_office_unit_id')) {
            $validUnit = BranchOfficeUnit::where('branch_office_id', $branchOffice->id)
                ->where('id', $branchOfficeUnitId)
                ->exists();

            if (! $validUnit) {
                $validator->errors()->add('branch_office_unit_id', 'Unit/Divisi tidak valid untuk branch office ini.');
            }
        }
    }

    /**
     * Shared by store()/update(): checked against the NORMALIZED
     * ('62'-prefixed) number, not the raw form input — same reasoning
     * as Auth\AuthController::register(), since the column actually
     * stores the prefixed form. $ignoreUserId excludes the member's own
     * current row on update() so re-saving their own unchanged number
     * doesn't false-positive against itself.
     */
    private function validateUniqueHandphone($validator, Request $request, ?string $ignoreUserId = null): void
    {
        $raw = $request->input('handphone');

        if (blank($raw)) {
            return;
        }

        if ($validator->errors()->has('handphone')) {
            return;
        }

        $normalized = '62'.$raw;

        $exists = User::where('handphone', $normalized)
            ->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))
            ->exists();

        if ($exists) {
            $validator->errors()->add('handphone', 'Nomor WhatsApp ini sudah terdaftar untuk user lain.');
        }
    }

}
