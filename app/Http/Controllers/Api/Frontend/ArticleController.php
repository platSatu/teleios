<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebArticle;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only catalog endpoint the fe-konexa frontend polls to
 * render its article listing page (see fe-konexa's
 * App\Services\TeleiosApiService::getArticles() and
 * App\Http\Controllers\FrontendController::articles()). Gated by
 * VerifyFrontendApiKey (shared X-API-KEY secret) — see routes/api.php's
 * `frontend.api-key` group. Same trust model and shape as the
 * category-applications and packages endpoints alongside it.
 *
 * Only `status = active` rows are exposed. `images_url` is appended —
 * WebArticle::getImagesUrlAttribute() isn't included in toJson() by
 * default since it isn't in $appends — so fe-konexa can drop it
 * straight into an <img src> without knowing about public/web/images
 * or WebImageUploader on its side at all.
 */
class ArticleController extends Controller
{
    public function index(): JsonResponse
    {
        $articles = WebArticle::query()
            ->where('status', 'active')
            ->with(['category:id,name'])
            ->orderByDesc('date_publish')
            ->get(['id', 'web_category_article_id', 'title', 'slug', 'description', 'images', 'date_publish'])
            ->append('images_url');

        return response()->json(['data' => $articles]);
    }
}
