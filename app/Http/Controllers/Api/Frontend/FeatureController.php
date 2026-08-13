<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebFeature;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only catalog endpoint the fe-konexa frontend polls to
 * render its features section (see fe-konexa's
 * App\Services\TeleiosApiService::getFeatures() and
 * frontend/partials/features.blade.php). Gated by VerifyFrontendApiKey
 * (shared X-API-KEY secret) — see routes/api.php's `frontend.api-key`
 * group. Same trust model and shape as the other /api/frontend/*
 * endpoints alongside it.
 *
 * Only `status = active` rows are exposed. `images_url` is appended —
 * WebFeature::getImagesUrlAttribute() isn't included in toJson() by
 * default since it isn't in $appends.
 */
class FeatureController extends Controller
{
    public function index(): JsonResponse
    {
        $features = WebFeature::query()
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get(['id', 'name', 'description', 'images'])
            ->append('images_url');

        return response()->json(['data' => $features]);
    }
}
