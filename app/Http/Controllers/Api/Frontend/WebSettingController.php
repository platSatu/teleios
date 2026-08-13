<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebSetting;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only endpoint the fe-konexa frontend polls (once per
 * request, via a view composer — see fe-konexa's
 * App\Services\TeleiosApiService::getWebSetting()) to render favicon,
 * meta tags, Google Tag Manager/Analytics, and contact info/map in
 * layouts/frontend.blade.php. Gated by VerifyFrontendApiKey (shared
 * X-API-KEY secret) — see routes/api.php's `frontend.api-key` group.
 *
 * Singleton, like App\Models\AiModerationSetting — WebSetting::current()
 * always returns (creating if missing) the one row this table ever
 * needs, so this never 404s even before anyone has filled it in.
 * favicon_url/logo_url/meta_images_url are appended since none of
 * WebSetting's accessors are in $appends by default.
 */
class WebSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $webSetting = WebSetting::current();
        $webSetting->append(['favicon_url', 'logo_url', 'meta_images_url']);

        return response()->json(['data' => $webSetting]);
    }
}
