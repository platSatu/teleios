<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebCategoryVideo;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only catalog endpoint the fe-konexa frontend polls to
 * render its video page, grouped by category (see fe-konexa's
 * App\Services\TeleiosApiService::getCategoryVideos() and
 * frontend/partials/video.blade.php). Gated by VerifyFrontendApiKey
 * (shared X-API-KEY secret) — see routes/api.php's `frontend.api-key`
 * group. Same trust model and shape as the other /api/frontend/*
 * endpoints alongside it.
 *
 * Only `status = active` rows are exposed. `thumbnail_url` is
 * appended — WebCategoryVideo::getThumbnailUrlAttribute() isn't
 * included in toJson() by default since it isn't in $appends.
 */
class CategoryVideoController extends Controller
{
    public function index(): JsonResponse
    {
        $categoryVideos = WebCategoryVideo::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'thumbnail'])
            ->append('thumbnail_url');

        return response()->json(['data' => $categoryVideos]);
    }
}
