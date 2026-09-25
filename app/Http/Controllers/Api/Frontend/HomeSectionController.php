<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebHomeSection;
use App\Support\HomeSectionPresenter;
use Illuminate\Http\JsonResponse;

/**
 * Susunan beranda untuk fe-konexa (App\Services\TeleiosApiService::
 * getHomeSections()). Hanya section beranda (web_page_id NULL) yang
 * active, urut sort_order. Section bawaan (hero, packages, features, faq,
 * running_text) hanya membawa bingkainya -- datanya diambil fe-konexa
 * dari endpoint masing-masing. Format: App\Support\HomeSectionPresenter.
 * Gated VerifyFrontendApiKey (routes/api.php).
 */
class HomeSectionController extends Controller
{
    public function index(HomeSectionPresenter $presenter): JsonResponse
    {
        $sections = WebHomeSection::query()
            ->ofPage(null)
            ->where('status', 'active')
            ->with('items')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $presenter->presentMany($sections)]);
    }
}
