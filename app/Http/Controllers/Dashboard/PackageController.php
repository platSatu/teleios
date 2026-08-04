<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CategoryApplication;
use App\Models\Package;
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
    public function index(Request $request): View
    {
        $categories = CategoryApplication::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $search = $request->string('search')->value() ?: null;
        $categoryId = $request->string('category')->value() ?: null;

        $packages = Package::query()
            ->with('categoryApplication')
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
}
