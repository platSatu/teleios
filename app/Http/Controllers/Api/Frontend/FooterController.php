<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebFooter;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only catalog endpoint the fe-konexa frontend polls to
 * render its footer link blocks. Gated by VerifyFrontendApiKey (shared
 * X-API-KEY secret) — see routes/api.php's `frontend.api-key` group.
 * Same trust model and shape as the other /api/frontend/* endpoints
 * alongside it.
 *
 * Only `status = active` rows are exposed, ordered by sort_order so the
 * frontend renders blocks in the order set in the superadmin panel.
 * background_image_url is appended since it isn't in $appends by
 * default.
 */
class FooterController extends Controller
{
    public function index(): JsonResponse
    {
        $footers = WebFooter::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get([
                'id',
                'background_image',
                'background_color',
                'column_width',
                'name',
                'group_name',
                'link',
                'target_blank',
                'sort_order',
            ])
            ->append('background_image_url');

        return response()->json(['data' => $footers]);
    }
}
