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
 */
class PackageController extends Controller
{
    public function index(): JsonResponse
    {
        $packages = Package::query()
            ->where('status', 'active')
            ->with(['categoryApplication:id,name'])
            ->orderBy('name')
            ->get(['id', 'category_application_id', 'name', 'description', 'duration', 'price']);

        return response()->json(['data' => $packages]);
    }
}
