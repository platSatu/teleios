<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\CategoryApplication;
use App\Models\Company;
use App\Models\Package;
use App\Services\Package\BranchSubscriptionService;
use App\Services\PackageLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * User-facing package catalog (product-style grid) shown from the
 * "Packages" link in the header menu (resources/views/layouts/partials/
 * header.blade.php). This is intentionally separate from
 * Superadmin\PackageController — that one is the CRUD admin table view
 * gated by the 'superadmin' middleware; this one is a read-only, public
 * to any authenticated user, browsing/filtering view of active packages
 * only, so it doesn't go through CrudAdmin (no ownership bypass or audit
 * logging needed for a plain listing).
 */
class PackageController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request, BranchSubscriptionService $branchSubscriptions): View
    {
        $categories = CategoryApplication::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $search = $request->string('search')->value() ?: null;
        $categoryId = $request->string('category')->value() ?: null;

        $packages = Package::query()
            ->with([
                'categoryApplication',
                'categoryApplications',
                // Buat daftar spesifikasi berikon di tiap kartu package
                // (lihat resources/views/dashboard/package/index.blade.php)
                // -- desain kartunya disamakan dengan pricing card
                // fe-konexa, yang eager-load 'limits.limitMetric' persis
                // sama (lihat App\Http\Controllers\Api\Frontend\
                // PackageController). Diurutkan naik biar limit terkecil
                // (biasanya yang paling "dasar") tampil duluan.
                'limits' => fn ($query) => $query->orderBy('max_value'),
                'limits.limitMetric',
            ])
            ->where('status', 'active')
            ->when($categoryId, fn ($query) => $query->where(fn ($q) => $q
                ->where('category_application_id', $categoryId)
                ->orWhereHas('categoryApplications', fn ($c) => $c->whereKey($categoryId))))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->get();

        // Status paket tiap branch -- hanya untuk owner (satu-satunya yang
        // boleh membeli paket, lihat PackageCheckoutController).
        $ownedCompany = Company::where('user_id', $request->user()->id)->first();
        $branchStatuses = $ownedCompany ? $branchSubscriptions->branchesWithActiveVoucher($ownedCompany) : collect();

        return view('dashboard.package.index', [
            'packageGroups' => $this->groupPackages($packages),
            'categories' => $categories,
            'search' => $search,
            'categoryId' => $categoryId,
            'branchStatuses' => $branchStatuses,
            'selectedBranchId' => $request->query('branch_office_id'),
        ]);
    }

    /**
     * Kelompokkan paket per kombinasi layanan (satu baris per kelompok,
     * mis. "Paket Lengkap", "Paket Whatsapp Blast"). Kolom urut Trial ->
     * durasi terpendek -> terpanjang; kelompok dengan layanan terbanyak
     * paling atas. 'savings' = % lebih hemat per bulan dibanding durasi
     * termahal (per bulan) di kelompok yang sama. Sama dengan tampilan
     * fe-konexa (FrontendController::groupPackages()).
     *
     * @param  Collection<int, Package>  $packages
     * @return Collection<int, array{label: string, services: array<int, string>, packages: Collection<int, Package>, savings: array<string, int>}>
     */
    private function groupPackages(Collection $packages): Collection
    {
        $servicesOf = fn (Package $package) => $package->categoryApplications->pluck('name')
            ->whenEmpty(fn ($names) => $names->push($package->categoryApplication?->name))
            ->filter()->unique()->sort()->values()->all();

        $maxServices = $packages->max(fn (Package $package) => count($servicesOf($package))) ?? 0;

        return $packages
            ->groupBy(fn (Package $package) => implode('|', $servicesOf($package)))
            ->map(function ($items) use ($servicesOf, $maxServices) {
                $services = $servicesOf($items->first());
                $paid = $items->reject(fn (Package $package) => $package->is_trial || (float) $package->price <= 0);
                $highestMonthly = $paid->max(fn (Package $package) => $package->monthlyPrice()) ?: 0;

                return [
                    'label' => count($services) > 1 && count($services) === $maxServices
                        ? 'Paket Lengkap'
                        : 'Paket '.(implode(' + ', $services) ?: 'Lainnya'),
                    'services' => $services,
                    'packages' => $items
                        ->sortBy(fn (Package $package) => sprintf('%d-%06d', $package->is_trial ? 0 : 1, $package->duration))
                        ->values(),
                    'savings' => $paid->mapWithKeys(fn (Package $package) => [
                        $package->id => $highestMonthly > 0 ? (int) round((1 - $package->monthlyPrice() / $highestMonthly) * 100) : 0,
                    ])->all(),
                ];
            })
            ->sortByDesc(fn (array $group) => count($group['services']))
            ->values();
    }

    /**
     * "Sudah dibeli berapa, sudah terpakai berapa, sisa berapa" report for
     * whichever package the acting company currently has active — see
     * App\Services\PackageLimitService::usageReport(). Live counts for
     * 'stock' metrics ('contact_count'/'device_count') are supplied here
     * rather than inside the service itself, since only the controller
     * layer knows how to reach WaPhoneBook / the Go device backend.
     */
    public function usage(Request $request, PackageLimitService $packageLimits): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;
        $branch = $context->activeBranch();

        // Pemakaian ditampilkan untuk branch yang sedang dibuka -- paket,
        // kuota, dan limit berlaku per branch.
        $liveCountResolvers = [
            'contact_count' => fn () => \App\Models\WaPhoneBook::where('company_id', $company->id)
                ->where('branch_office_id', $branch?->id)
                ->count(),
        ];

        $jwt = session('golang_jwt_token');

        if ($jwt) {
            $liveCountResolvers['device_count'] = function () use ($jwt, $branch) {
                try {
                    return collect(app(\App\Services\Chat\ConnectDeviceService::class)->listDevices($jwt))
                        ->where('branch_office_id', $branch?->id)
                        ->count();
                } catch (\Throwable $e) {
                    return null;
                }
            };
        }

        $rows = $branch ? $packageLimits->usageReport($company, $branch, $liveCountResolvers) : [];
        $activePackage = $branch ? $packageLimits->activePackage($company, $branch) : null;

        return view('dashboard.package.usage', [
            'rows' => $rows,
            'activePackage' => $activePackage,
            'branch' => $branch,
        ]);
    }
}
