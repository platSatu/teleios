<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\CategoryApplication;
use App\Models\Package;
use App\Services\PackageLimitService;
use Illuminate\Http\Request;
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

    public function index(Request $request): View
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
            ->when($categoryId, fn ($query) => $query->where('category_application_id', $categoryId))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(9)
            ->withQueryString();

        return view('dashboard.package.index', [
            'packages' => $packages,
            'categories' => $categories,
            'search' => $search,
            'categoryId' => $categoryId,
        ]);
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
        $company = $this->companyContext($request)->company;

        $liveCountResolvers = [
            'contact_count' => fn () => \App\Models\WaPhoneBook::where('company_id', $company->id)->count(),
        ];

        $jwt = session('golang_jwt_token');

        if ($jwt) {
            $liveCountResolvers['device_count'] = function () use ($jwt) {
                try {
                    return count(app(\App\Services\Chat\ConnectDeviceService::class)->listDevices($jwt));
                } catch (\Throwable $e) {
                    return null;
                }
            };
        }

        $rows = $packageLimits->usageReport($company, null, $liveCountResolvers);
        $activePackage = $packageLimits->activePackage($company);

        return view('dashboard.package.usage', [
            'rows' => $rows,
            'activePackage' => $activePackage,
        ]);
    }
}
