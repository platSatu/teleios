<?php

namespace App\Http\Controllers\User\Profile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Profile\Concerns\ScopesActivePackage;
use App\Models\ApplicationMenu;
use App\Models\CategoryApplication;
use App\Models\Company;
use App\Models\CompanyRoleMenu;
use App\Services\Company\CompanyContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
 *   2. "Company" tab: a LIST of every company the user owns (a user can
 *      own more than one) — index() loads it, the actual create/edit/
 *      show/delete forms live in User\Profile\CompanyController. Every
 *      other tab below is still scoped to exactly one company at a time
 *      ("the active company" — see index()'s `active_company_id` session
 *      handling), switched via a row action on this tab.
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

        // A user can now own MORE THAN ONE company (see User\Profile\
        // CompanyController), but every tab below "Company" is still one
        // shared form/table scoped to a single company at a time — there
        // has to be some notion of "which one" for Branch Office/Unit-
        // Divisi/Roles/Applications/Setting Users to mean anything.
        // That's `active_company_id` in the session: row actions on the
        // Company tab (Show/Edit/Add Branch/...) pass ?company={id},
        // which — after verifying it's actually one of THIS user's own
        // companies — becomes the new active company for every tab on
        // this page until switched again. Invisible/automatic for the
        // common case of a user with exactly one company (it's just
        // resolved and remembered the first time), only surfaces as a
        // real choice once there's more than one to pick from.
        if ($request->filled('company')) {
            $selected = Company::where('user_id', $user->id)
                ->where('id', $request->query('company'))
                ->first();

            if ($selected) {
                session(['active_company_id' => $selected->id]);
            }
        }

        $companies = Company::where('user_id', $user->id)
            ->withCount('branchOffices')
            ->orderBy('created_at')
            ->get();

        // Resolves via App\Services\Company\CompanyContextResolver instead
        // of the old owner-only `Company::where('user_id', $user->id)`
        // lookup — a member invited through User\Profile\
        // CompanyUserController doesn't own a Company row, so that query
        // always returned null for them and left this whole page blank.
        // Nullable here (not resolveOrFail()) since a brand new user who
        // hasn't created or joined any company yet is an expected, normal
        // state for this page, not an error. $companyId is null unless
        // this user owns a company AND has picked one — resolve() falls
        // back to "first owned company" on null, same as before this
        // change, so a single-company user never sees any of this.
        $activeCompanyId = session('active_company_id');
        $context = app(CompanyContextResolver::class)->resolve($user, $activeCompanyId);
        $company = $context?->company;
        $isOwner = $context?->isOwner ?? true;

        $companyRoles = $company
            ? $company->roles()->with('branchOfficeUnit')->orderBy('created_at')->get()
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

        // Which `profile.*` route groups (Branch Office / Unit-Divisi /
        // Roles / Applications / Setting Users tabs) a non-owner member's
        // CompanyRole is allowed into — same source of truth as the
        // 'menu.access' middleware now guarding those routes (see
        // routes/web.php) and the Chat sidebar's $allowedChatRouteNames
        // (App\Providers\AppServiceProvider). Computed once here and
        // reused for both the nav buttons and the tab-panes themselves,
        // so a non-owner can't reach a management tab's content just by
        // navigating to ?tab=roles directly when its nav button is
        // hidden — see canAccessProfileRouteGroup() below for the
        // fail-open rule (unrestricted until a superadmin actually
        // catalogs that route group).
        $allowedProfileRouteNames = null;

        if (! $isOwner && $context?->role) {
            $allowedProfileRouteNames = CompanyRoleMenu::where('company_role_id', $context->role->id)
                ->where('status', 'active')
                ->with('applicationMenu:id,route_name')
                ->get()
                ->pluck('applicationMenu.route_name')
                ->filter()
                ->values();
        } elseif (! $isOwner) {
            // Resolved to a company but with no CompanyRole at all (data
            // inconsistency, or a role that got deleted out from under an
            // existing membership) — restrictive empty set, not
            // unrestricted null, since there's no role to have been
            // granted anything.
            $allowedProfileRouteNames = collect();
        }

        $canAccessBranchOfficeTab = $this->canAccessProfileRouteGroup($isOwner, $allowedProfileRouteNames, 'profile.branch-offices');
        $canAccessUnitDivisiTab = $this->canAccessProfileRouteGroup($isOwner, $allowedProfileRouteNames, 'profile.branch-office-units');
        $canAccessRolesTab = $this->canAccessProfileRouteGroup($isOwner, $allowedProfileRouteNames, 'profile.company-roles');
        $canAccessApplicationsTab = $this->canAccessProfileRouteGroup($isOwner, $allowedProfileRouteNames, 'profile.company-role-menus');
        $canAccessUsersTab = $this->canAccessProfileRouteGroup($isOwner, $allowedProfileRouteNames, 'profile.company-users');

        return view('user.profile.index', [
            'user' => $user,
            'company' => $company,
            'companies' => $companies,
            // Company is still always owner-only (a non-owner never has
            // one to edit). Branch Office / Unit-Divisi / Roles /
            // Applications / Setting Users now go through the
            // canAccess*Tab flags above instead of a flat $isOwner check
            // — the owner is always unrestricted (see
            // canAccessProfileRouteGroup()), a non-owner only if their
            // CompanyRole has been explicitly granted that route group's
            // App\Models\ApplicationMenu entry, OR unrestricted-by-default
            // if no superadmin has catalogued that route group yet (fail
            // open, same as 'menu.access' middleware / Chat sidebar).
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
            'canAccessBranchOfficeTab' => $canAccessBranchOfficeTab,
            'canAccessUnitDivisiTab' => $canAccessUnitDivisiTab,
            'canAccessRolesTab' => $canAccessRolesTab,
            'canAccessApplicationsTab' => $canAccessApplicationsTab,
            'canAccessUsersTab' => $canAccessUsersTab,
        ]);
    }

    /**
     * Same fail-open backstop as App\Http\Middleware\EnsureMenuAccess,
     * reused here so the Profile page's tab visibility (nav buttons AND
     * tab-panes) always agrees with what that middleware would actually
     * let a non-owner member through to. The owner is always
     * unrestricted. A route group with no App\Models\ApplicationMenu
     * catalog entry at all (route_name LIKE '<group>.%') is unrestricted
     * too — nothing about it has been put behind the per-role permission
     * system yet, so existing companies aren't retroactively locked out
     * of tabs they already relied on the moment this feature ships.
     * Once a superadmin catalogs that group AND a company assigns it to
     * specific roles, it becomes a real allow-list for non-owners.
     */
    private function canAccessProfileRouteGroup(bool $isOwner, ?\Illuminate\Support\Collection $allowedRouteNames, string $group): bool
    {
        if ($isOwner) {
            return true;
        }

        $catalogued = ApplicationMenu::where('route_name', 'like', $group.'.%')->exists();

        if (! $catalogued) {
            return true;
        }

        return $allowedRouteNames !== null
            && $allowedRouteNames->contains(fn ($routeName) => str_starts_with((string) $routeName, $group.'.'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // Bare national number only (no leading 0, no '62' country
            // code) — same shape/normalization as everywhere else this
            // app collects a handphone (see Auth\AuthController::
            // register() and User\Profile\CompanyUserController).
            // Needed so the owner (and any user editing their own
            // profile) has somewhere to set/fix this — Setting Users
            // deliberately has no edit action on the owner's own row,
            // and WhatsApp-based features (e.g. the "jadwal" keyword
            // recap) silently no-op for anyone without a handphone set.
            'handphone' => ['nullable', 'regex:/^[1-9][0-9]{9,13}$/'],
        ], [
            'handphone.regex' => 'Nomor WhatsApp harus 10-14 digit angka, tanpa awalan 0 atau kode negara 62 (contoh: 81286800080).',
        ]);

        $validator->after(function ($validator) use ($request, $user) {
            $raw = $request->input('handphone');

            if (blank($raw) || $validator->errors()->has('handphone')) {
                return;
            }

            $normalized = '62'.$raw;

            $exists = \App\Models\User::where('handphone', $normalized)
                ->where('id', '!=', $user->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('handphone', 'Nomor WhatsApp ini sudah terdaftar untuk user lain.');
            }
        });

        $validated = $validator->validate();

        // Same normalization as everywhere else — see the field's
        // validation comment above. An empty submission CLEARS the
        // number (explicit null) rather than leaving whatever was
        // there before untouched, so a wrong number can actually be
        // removed from this form too.
        $validated['handphone'] = filled($validated['handphone'] ?? null) ? '62'.$validated['handphone'] : null;

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

    // Company create/edit/show/delete moved to User\Profile\
    // CompanyController — a user can own more than one company now, so
    // "the" company create-or-update toggle that used to live here
    // (updateCompany()) no longer makes sense. See that controller's
    // docblock.
}
