<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only catalog endpoint the fe-konexa frontend polls to
 * render its landing/product pages (see fe-konexa's
 * App\Services\TeleiosApiService::getPackages()). Gated by
 * VerifyFrontendApiKey (shared X-API-KEY secret) instead of
 * auth:sanctum — see routes/api.php's `frontend.api-key` group.
 *
 * Only `status = active` rows are exposed, and only the fields a public
 * landing page actually needs (no user_id / internal metadata).
 * `categoryApplication` is eager-loaded and serializes to the
 * `category_application` key (Eloquent snake-cases relation keys on
 * toArray()/toJson()).
 *
 * `limits.limitMetric` is also eager-loaded (-> `limits` array, each with
 * a nested `limit_metric`) so the frontend's pricing cards can render an
 * icon-based spec list (e.g. "Broadcast Pesan: 10.000 pesan/bulan") from
 * real App\Models\PackageLimit / App\Models\LimitMetric data instead of
 * static placeholder text — see fe-konexa's
 * resources/views/frontend/partials/packages.blade.php. A package with
 * no PackageLimit rows just gets an empty `limits` array; the frontend
 * falls back to generic copy in that case.
 *
 * Ordered by price (ascending) rather than name — the frontend derives
 * its "paket unggulan/TERPOPULER" badge from position in this list
 * (middle item), matching the cheapest-to-priciest layout of the KVM-
 * hosting-style reference design it was modeled after.
 */
class PackageController extends Controller
{
    public function index(): JsonResponse
    {
        $packages = Package::query()
            ->where('status', 'active')
            ->with([
                'categoryApplication:id,name',
                'limits' => fn ($query) => $query->orderBy('max_value'),
                'limits.limitMetric:id,key,name,unit',
            ])
            ->orderBy('price')
            ->get(['id', 'category_application_id', 'name', 'description', 'duration', 'price']);

        return response()->json(['data' => $packages]);
    }
}
