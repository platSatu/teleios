<?php

namespace App\Http\Controllers\User\Profile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Profile\Concerns\ScopesActivePackage;
use App\Models\ApplicationMenu;
use App\Models\CategoryApplication;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\CompanyRoleMenu;
use App\Models\CompanyToUser;
use App\Services\Company\CompanyContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Single page at dashboard/user/profile — replaces the old standalone
 * /profile route (App\Http\Controllers\ProfileController) and the old
 * dashboard/setting/user/company CRUD (User\Settings\CompanyController).
 * One blade (resources/views/user/profile/index.blade.php), seven tabs,
 * in the order the underlying data has to be created:
 *
 *   1. "Profile" tab: name + avatar (this controller's update()), plus
 *      password (existing Auth\PasswordController) and PIN (existing
 *      User\Settings\PinController) forms, both still submitted to their
 *      own routes from inside this page.
 *   2. "Company" tab: one company per user, created lazily the first
 *      time this form is submitted (updateCompany()). `slug` is derived
 *      from `name` here, not stored on request input, and re-derived
 *      every save so renaming the company keeps the slug in sync.
 *      Creating a company also seeds a default "Owner" CompanyRole and
 *      links the creator to it via CompanyToUser, in the same transaction.
 *   3. "Branch Office" tab: BranchOffice CRUD scoped to the company —
 *      see User\Profile\BranchOfficeController. Requires a company to
 *      exist first.
 *   4. "Unit/Divisi" tab: BranchOfficeUnit CRUD, scoped to a branch
 *      office — see User\Profile\BranchOfficeUnitController. Requires a
 *      branch office to exist first (company -> branch office -> unit).
 *   5. "Roles" tab: CompanyRole CRUD, see User\Profile\CompanyRoleController.
 *   6. "Applications" tab: which App\Models\ApplicationMenu entries (the
 *      superadmin-managed catalog, grouped by Category Application) this
 *      company has switched on — CompanyRoleMenu CRUD, see User\Profile\
 *      CompanyRoleMenuController. Superadmin has an unscoped mirror of
 *      this same CRUD for when a company reports a missing/wrong menu.
 *   7. "Setting Users" tab: CompanyToUser CRUD — adding a member creates
 *      a brand new login for them (name/email/password) alongside the
 *      membership row(s), one row per CategoryApplication they're
 *      granted, optionally placed under a branch office/unit. See
 *      User\Profile\CompanyUserController.
 *
 * Every read/write is scoped through $request->user() — never a
 * route-supplied id — so there's no way to view or edit another user's
 * profile or company by mistake. Branch Office/Unit/Roles/Users tabs are
 * further scoped to the company owned by the logged-in user
 * (Company::user_id === auth id) — invited members don't get management
 * access through this page.
 *
 * Branch Office/Unit-Divisi/Setting Users/Roles/Applications all
 * additionally require the user to currently have at least one active
 * package (see ScopesActivePackage) — a company that hasn't bought
 * anything can't set up branch offices/units, invite teammates, define
 * roles, or switch on app menus for a package it doesn't have. The view
 * hides those five tabs entirely when that's the case; the CRUD
 * controllers behind them (BranchOfficeController, BranchOfficeUnitController,
 * CompanyUserController, CompanyRoleController, CompanyRoleMenuController)
 * enforce the same rule server-side on create so it can't be bypassed by
 * posting to the route directly — editing/deleting something that
 * already exists stays allowed even after a package lapses, same
 * "manage what you already have" stance as CompanyRoleController.
 */
class ProfileController extends Controller
{
    use ScopesActivePackage;

    public function index(Request $request): View
    {
        $user = $request->user();

        // Resolves via App\Services\Company\CompanyContextResolver instead
        // of the old owner-only `Company::where('user_id', $user->id)`
        // lookup — a member invited through User\Profile\
        // CompanyUserController doesn't own a Company row, so that query
        // always returned null for them and left this whole page blank.
        // Nullable here (not resolveOrFail()) since a brand new user who
        // hasn't created or joined any company yet is an expected, normal
        // state for this page, not an error.
        $context = app(CompanyContextResolver::class)->resolve($user);
        $company = $context?->company;
        $isOwner = $context?->isOwner ?? true;

        $companyRoles = $company
            ? $company->roles()->orderBy('created_at')->get()
            : collect();

        // Grouped by user_id (not a flat list): a member can be granted
        // more than one CategoryApplication, and that's modeled as one
        // company_to_users row per category (see App\Models\
        // CompanyToUser / CompanyUserController), so the view needs
        // every row belonging to the same user together to render one
        // table row + one edit/show modal per member instead of one per
        // category.
        //
        // A non-owner locked to a branch only sees members of THEIR OWN
        // branch here — "setiap branch punya user masing-masing", the
        // owner ("pusat") is the only one who sees every branch at once.
        $companyMembersQuery = $company
            ? $company->members()->with(['user', 'role', 'categoryApplication'])
            : null;

        if ($companyMembersQuery && ! $isOwner) {
            $companyMembersQuery->where('branch_office_id', $context->branchOffice?->id);
        }

        $companyMembers = $companyMembersQuery
            ? $companyMembersQuery->orderBy('created_at')->get()->groupBy('user_id')
            : collect();

        $companyRoleMenus = $company
            ? $company->roleMenus()->with(['companyRole', 'categoryApplication', 'applicationMenu'])->orderBy('created_at')->get()
            : collect();

        // Branch Office / Unit-Divisi tabs. Units are eager-loaded onto
        // each branch office both for the Unit/Divisi tab's table
        // (grouped display) and so the view/JS can build the
        // branch-office -> its units mapping for the Setting Users
        // "Tambah/Edit User" forms without another query per row.
        //
        // A non-owner only ever sees their own branch here too — this is
        // what lets the Setting Users "Tambah User" form skip the branch
        // picker entirely for them and jump straight to "pick a unit
        // within your branch" (see User\Profile\CompanyUserController).
        $branchOfficesQuery = $company
            ? $company->branchOffices()->with('units')
            : null;

        if ($branchOfficesQuery && ! $isOwner) {
            $branchOfficesQuery->where('id', $context->branchOffice?->id);
        }

        $branchOffices = $branchOfficesQuery
            ? $branchOfficesQuery->orderBy('created_at')->get()
            : collect();

        $branchOfficeUnits = $branchOffices->flatMap->units->sortBy('created_at')->values();

        $activeCategoryApplicationIds = $company
            ? $this->activeCategoryApplicationIds($company->user_id)
            : $this->activeCategoryApplicationIds($user->id);
        $hasActivePackage = $activeCategoryApplicationIds->isNotEmpty();

        // Both dropdown sources for the "Applications"/"Setting Users"
        // tabs' add forms — only active catalog entries (so a company
        // can't pick something superadmin has already deactivated), AND,
        // once gated behind an active package, narrowed down further to
        // ONLY the category applications this user actually has active
        // access to. Without this second filter a user could still grant
        // a teammate — or switch on a menu for — a category application
        // they never paid for, just because it exists somewhere on the
        // platform.
        $categoryApplications = CategoryApplication::where('status', 'active')
            ->when($hasActivePackage, fn ($q) => $q->whereIn('id', $activeCategoryApplicationIds))
            ->orderBy('name')
            ->get();

        $applicationMenus = ApplicationMenu::where('status', 'active')
            ->when($hasActivePackage, fn ($q) => $q->whereIn('category_application_id', $activeCategoryApplicationIds))
            ->orderBy('name')
            ->get();

        return view('user.profile.index', [
            'user' => $user,
            'company' => $company,
            // Company / Branch Office / Unit-Divisi / Roles / Applications
            // are owner-only management tabs — "pusat" configures the
            // company, branches/units, roles, and which menus each role
            // can see. A non-owner member only gets the Setting Users tab
            // (scoped to their own branch), or Profile if they're not
            // part of any company at all. See resources/views/user/
            // profile/index.blade.php's tab-gating.
            'isOwner' => $isOwner,
            'companyContext' => $context,
            'companyRoles' => $companyRoles,
            'companyMembers' => $companyMembers,
            'companyRoleMenus' => $companyRoleMenus,
            'branchOffices' => $branchOffices,
            'branchOfficeUnits' => $branchOfficeUnits,
            'categoryApplications' => $categoryApplications,
            'applicationMenus' => $applicationMenus,
            'hasActivePackage' => $hasActivePackage,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            // Remove the old photo first so changing avatars doesn't
            // silently pile up orphaned files on disk forever.
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }

            $validated['image'] = $request->file('image')->store('avatars', 'public');
        } else {
            // No new file uploaded — don't overwrite the existing path
            // with null just because the field was absent from this
            // submission.
            unset($validated['image']);
        }

        $user->update($validated);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Field is named "company_name" (not "name") specifically so it
        // can't collide with the Profile tab's own "name" input — both
        // tabs live in the same $errors bag, and a shared key would leak
        // a company validation error onto the profile form's field too.
        //
        // Built with Validator::make() instead of $request->validate()
        // so a failure can be redirected explicitly back to ?tab=company
        // — plain validate()'s automatic back() follows the Referer
        // header, which is wherever the page happened to be *before*
        // the user clicked the Company tab (client-side, no navigation),
        // and would silently land them back on the Profile tab with the
        // company errors invisible on the tab underneath.
        $validator = Validator::make($request->all(), [
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['name'] = $validated['company_name'];
        unset($validated['company_name']);

        $company = Company::where('user_id', $user->id)->first();

        // Re-derived from `name` on every save (create AND update) — the
        // spec asks for the slug to come from the controller rather
        // than the model, so renaming the company keeps the slug in
        // sync instead of freezing it at whatever it was on first save.
        $validated['slug'] = $this->uniqueSlug($validated['name'], $company?->id);

        if ($request->hasFile('logo')) {
            if ($company?->logo) {
                Storage::disk('public')->delete($company->logo);
            }

            $validated['logo'] = $request->file('logo')->store('company-logos', 'public');
        } else {
            unset($validated['logo']);
        }

        if ($company) {
            $company->update($validated);
        } else {
            $validated['user_id'] = $user->id;

            // First-time company creation also seeds a default "Owner"
            // role and links the creator to it — all three rows only
            // make sense together, so one failing should roll back the
            // rest rather than leaving a company with no roles/members.
            DB::transaction(function () use ($validated, $user) {
                $company = Company::create($validated);

                $ownerRole = CompanyRole::create([
                    'company_id' => $company->id,
                    'name' => 'Owner',
                    'description' => 'Pemilik company dengan akses penuh.',
                    'status' => 'active',
                ]);

                CompanyToUser::create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'company_role_id' => $ownerRole->id,
                    'status' => 'active',
                ]);
            });
        }

        return redirect()
            ->route('profile.edit', ['tab' => 'company'])
            ->with('success', 'Company berhasil disimpan.');
    }

    /**
     * Str::slug($name), re-rolled with a numeric suffix (-2, -3, ...)
     * until it doesn't collide with another company's slug. $ignoreId
     * excludes the company currently being updated so saving without
     * changing the name doesn't trip over its own slug.
     */
    private function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $suffix = 2;

        while (
            Company::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
