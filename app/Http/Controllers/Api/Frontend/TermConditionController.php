<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebTermCondition;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only endpoint the fe-konexa frontend polls to render its
 * "Syarat dan Ketentuan" page (see fe-konexa's
 * App\Services\TeleiosApiService::getTermCondition() and
 * App\Http\Controllers\FrontendController::terms()). Gated by
 * VerifyFrontendApiKey (shared X-API-KEY secret) — see routes/api.php's
 * `frontend.api-key` group.
 *
 * Unlike the other /api/frontend/* endpoints, this returns a single
 * object (or null), not a list — WebTermCondition::current() is the
 * same "most recently updated active row" lookup already used by the
 * register-page popup on this app's own side.
 */
class TermConditionController extends Controller
{
    public function show(): JsonResponse
    {
        $termCondition = WebTermCondition::current();

        return response()->json([
            'data' => $termCondition?->only(['id', 'name', 'descriptions']),
        ]);
    }
}
