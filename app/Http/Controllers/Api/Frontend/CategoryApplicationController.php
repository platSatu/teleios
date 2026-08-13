<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CategoryApplication;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only catalog endpoint the fe-konexa frontend polls to
 * render its landing/product pages (see fe-konexa's
 * App\Services\TeleiosApiService::getCategoryApplications()). Gated by
 * VerifyFrontendApiKey (shared X-API-KEY secret) instead of
 * auth:sanctum — see routes/api.php's `frontend.api-key` group.
 *
 * Only `status = active` rows are exposed, and only the fields a public
 * landing page actually needs (no user_id / internal metadata).
 */
class CategoryApplicationController extends Controller
{
    public function index(): JsonResponse
    {
        $categoryApplications = CategoryApplication::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json(['data' => $categoryApplications]);
    }
}
