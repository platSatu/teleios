<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

    /**
     * Detail satu artikel (halaman /artikel/{slug} di fe-konexa) + 3
     * artikel terkait dari kategori yang sama. Setiap pemanggilan
     * menambah count_read (lewat query builder supaya updated_at artikel
     * tidak ikut berubah).
     */
    public function show(string $slug): JsonResponse
    {
        $article = WebArticle::query()
            ->where('status', 'active')
            ->where('slug', $slug)
            ->with(['category:id,name', 'metaTags'])
            ->first();

        if (! $article) {
            return response()->json(['message' => 'Artikel tidak ditemukan.'], 404);
        }

        DB::table('web_articles')->where('id', $article->id)->increment('count_read');

        $related = WebArticle::query()
            ->where('status', 'active')
            ->where('web_category_article_id', $article->web_category_article_id)
            ->whereKeyNot($article->id)
            ->orderByDesc('date_publish')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'description', 'images', 'date_publish'])
            ->append('images_url');

        return response()->json(['data' => [
            'title' => $article->title,
            'slug' => $article->slug,
            'description' => $article->description,
            'images_url' => $article->images_url,
            'date_publish' => $article->date_publish,
            'category' => $article->category?->name,
            'meta_description' => $article->effective_meta_description,
            'meta_keywords' => $article->meta_keywords,
            'meta_images_url' => $article->meta_images_url,
            'tags' => $article->metaTags->pluck('name')->values(),
            'related' => $related,
        ]]);
    }
}
