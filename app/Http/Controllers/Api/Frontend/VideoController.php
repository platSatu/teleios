<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebVideo;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only catalog endpoint the fe-konexa frontend polls to
 * render its video page (see fe-konexa's
 * App\Services\TeleiosApiService::getVideos() and
 * frontend/partials/video.blade.php). Gated by VerifyFrontendApiKey
 * (shared X-API-KEY secret) — see routes/api.php's `frontend.api-key`
 * group. Same trust model and shape as the other /api/frontend/*
 * endpoints alongside it.
 *
 * Only `status = active` rows are exposed. `thumbnail_url`,
 * `videos_url` and `youtube_embed_url` are appended — none of
 * WebVideo's accessors are in $appends by default — so fe-konexa can
 * render either an uploaded file or a normalized YouTube embed URL
 * without knowing WebVideo's link-parsing logic on its side at all.
 * `category` is eager-loaded (serializes to `category` since it's
 * already a single word — no snake_case change needed).
 */
class VideoController extends Controller
{
    public function index(): JsonResponse
    {
        $videos = WebVideo::query()
            ->where('status', 'active')
            ->with(['category:id,name'])
            ->orderByDesc('date_publish')
            ->get([
                'id', 'web_category_video_id', 'title', 'slug', 'thumbnail',
                'description', 'videos', 'link_youtube', 'date_publish',
            ])
            ->append(['thumbnail_url', 'videos_url', 'youtube_embed_url']);

        return response()->json(['data' => $videos]);
    }
}
