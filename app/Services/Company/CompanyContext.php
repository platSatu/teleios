<?php

namespace App\Services\Company;

use App\Models\ApplicationMenu;
use App\Models\BranchOffice;
use App\Models\BranchOfficeUnit;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\CompanyRoleMenu;
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

    private bool $activeBranchResolved = false;

    private ?BranchOffice $activeBranch = null;

    /**
     * Branch yang sedang "dibuka" user ini -- menentukan menu & paket yang
     * berlaku di dashboard (paket berlaku per branch). Member yang terkunci
     * ke satu branch selalu branch-nya sendiri; owner/member tingkat
     * company memakai pilihan di pemilih branch header. Lihat
     * App\Services\Company\ActiveBranchSelection. Null kalau company
     * belum punya branch sama sekali.
     */
    public function activeBranch(): ?BranchOffice
    {
        if (! $this->activeBranchResolved) {
            $this->activeBranch = app(ActiveBranchSelection::class)->resolve($this);
            $this->activeBranchResolved = true;
        }

        return $this->activeBranch;
    }

    /** @var array<int, string>|null */
    private ?array $grantedMenuIds = null;

    /**
     * Satu-satunya aturan akses menu per role -- dipakai middleware
     * 'menu.access' (URL), sidebar (menu.blade.php) dan tab Profile,
     * jadi yang tersembunyi di menu pasti juga ditolak lewat URL.
     *
     * Owner selalu boleh. Untuk member, route dicocokkan ke menu Application
     * Menu yang paling spesifik: route_name menu tanpa segmen terakhir
     * menjadi awalan (mis. "tagihan.index" mencakup "tagihan.*",
     * "tagihan.category.index" mencakup "tagihan.category.*" -- dan yang
     * lebih panjang menang). Boleh hanya kalau menu itu diberikan ke
     * role-nya. Selain itu DITOLAK (fail-closed): tanpa role, menu belum
     * terdaftar, atau menu nonaktif.
     */
    public function canAccessRoute(?string $routeName): bool
    {
        if ($this->isOwner) {
            return true;
        }

        if (! $routeName || ! $this->role) {
            return false;
        }

        $matched = self::menuIdsFor($routeName);

        if ($matched === []) {
            return false;
        }

        $this->grantedMenuIds ??= CompanyRoleMenu::where('company_role_id', $this->role->id)
            ->where('status', 'active')
            ->pluck('application_menu_id')
            ->all();

        return array_intersect($matched, $this->grantedMenuIds) !== [];
    }

    /**
     * Id menu dengan awalan terpanjang yang cocok dengan route ini (bisa
     * lebih dari satu kalau dua menu berbagi awalan yang sama).
     *
     * @return array<int, string>
     */
    private static function menuIdsFor(string $routeName): array
    {
        $prefixes = once(fn () => ApplicationMenu::where('status', 'active')
            ->whereNotNull('route_name')
            ->pluck('route_name', 'id')
            ->map(fn (string $name) => (str_contains($name, '.') ? substr($name, 0, strrpos($name, '.')) : $name).'.')
            ->all());

        $subject = $routeName.'.';
        $best = 0;
        $ids = [];

        foreach ($prefixes as $id => $prefix) {
            if (! str_starts_with($subject, $prefix) || strlen($prefix) < $best) {
                continue;
            }

            if (strlen($prefix) > $best) {
                $best = strlen($prefix);
                $ids = [];
            }

            $ids[] = (string) $id;
        }

        return $ids;
    }
}
