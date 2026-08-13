<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebHeader;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only catalog endpoint the fe-konexa frontend polls to
 * render its homepage hero/header slider. Gated by VerifyFrontendApiKey
 * (shared X-API-KEY secret) — see routes/api.php's `frontend.api-key`
 * group. Same trust model and shape as the other /api/frontend/*
 * endpoints alongside it.
 *
 * Only `status = active` rows are exposed, ordered by sort_order so the
 * frontend renders slides in the order set in the superadmin panel.
 * *_url accessors are appended since they aren't in $appends by
 * default — background_type tells the frontend which of videos_url /
 * background_images_url to actually render for a given slide.
 */
class HeaderController extends Controller
{
    public function index(): JsonResponse
    {
        $headers = WebHeader::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get([
                'id',
                'background_type',
                'videos',
                'background_images',
                'thumbnail_images',
                'thumbnail_background_images',
                'text',
                'descriptions',
                'color_headline',
                'color_description',
                'button_action',
                'button_text',
                'button_link',
                'sort_order',
            ])
            ->append(['videos_url', 'background_images_url', 'thumbnail_images_url', 'thumbnail_background_images_url']);

        return response()->json(['data' => $headers]);
    }
}
