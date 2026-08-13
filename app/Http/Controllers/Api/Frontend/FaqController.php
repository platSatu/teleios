<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebFaq;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only catalog endpoint the fe-konexa frontend polls to
 * render its FAQ accordion (see fe-konexa's
 * App\Services\TeleiosApiService::getFaqs() and
 * frontend/partials/faq.blade.php). Gated by VerifyFrontendApiKey
 * (shared X-API-KEY secret) — see routes/api.php's `frontend.api-key`
 * group. Same trust model and shape as the other /api/frontend/*
 * endpoints alongside it.
 *
 * Only `status = active` rows are exposed. `name` is the question,
 * `descriptions` is the answer (see App\Models\WebFaq) — kept as-is
 * rather than renamed, so the field names match the model 1:1.
 */
class FaqController extends Controller
{
    public function index(): JsonResponse
    {
        $faqs = WebFaq::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'descriptions']);

        return response()->json(['data' => $faqs]);
    }
}
