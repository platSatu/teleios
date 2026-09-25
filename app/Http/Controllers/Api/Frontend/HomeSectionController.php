<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebArticle;
use App\Models\WebHomeSection;
use App\Models\WebHomeSectionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Susunan beranda untuk fe-konexa (App\Services\TeleiosApiService::
 * getHomeSections()). Hanya section berstatus active, urut sort_order.
 * Section bawaan (hero, packages, features, faq, running_text) hanya
 * membawa bingkainya -- datanya tetap diambil fe-konexa dari endpoint
 * masing-masing. Section item yang belum punya item dilewati supaya
 * tidak tampil kosong. Gated VerifyFrontendApiKey (routes/api.php).
 */
class HomeSectionController extends Controller
{
    public function index(): JsonResponse
    {
        $sections = WebHomeSection::query()
            ->where('status', 'active')
            ->with('items')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->reject(fn (WebHomeSection $section) => ! isset(WebHomeSection::TYPES[$section->type])
                || ($section->hasItems() && $section->items->isEmpty()))
            ->map(fn (WebHomeSection $section) => $this->present($section))
            ->values();

        return response()->json(['data' => $sections]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WebHomeSection $section): array
    {
        return [
            'id' => $section->id,
            'type' => $section->type,
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'text_align' => $section->text_align,
            'background' => [
                'type' => $section->background_type,
                'color' => $section->background_color,
                'image_url' => $section->background_image_url,
                'video_url' => $section->allowsVideo() ? $section->background_video_url : null,
            ],
            'buttons' => collect([[$section->cta_text, $section->cta_link], [$section->cta2_text, $section->cta2_link]])
                ->filter(fn (array $button) => filled($button[0]) && filled($button[1]))
                ->map(fn (array $button) => ['text' => $button[0], 'link' => $button[1]])
                ->values(),
            'content' => $section->typeConfig('content') ? $section->content : null,
            'media_image_url' => $section->typeConfig('content') ? $section->media_image_url : null,
            'media_position' => $section->media_position,
            'items' => $section->items->map(fn (WebHomeSectionItem $item) => [
                'icon' => $item->icon,
                'image_url' => $item->image_url,
                'title' => $item->title,
                'description' => $item->description,
                'value' => $item->value,
                'link_text' => $item->link_text,
                'link_url' => $item->link_url,
            ])->values(),
            'articles' => $section->typeConfig('articles') ? $this->articles($section) : [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function articles(WebHomeSection $section): array
    {
        return WebArticle::query()
            ->where('status', 'active')
            ->when($section->web_category_article_id, fn ($query, $categoryId) => $query->where('web_category_article_id', $categoryId))
            ->with('category:id,name')
            ->orderByDesc('date_publish')
            ->orderByDesc('created_at')
            ->limit($section->item_limit ?: 3)
            ->get(['id', 'web_category_article_id', 'title', 'slug', 'description', 'images', 'date_publish'])
            ->map(fn (WebArticle $article) => [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => Str::limit(trim(strip_tags((string) $article->description)), 300),
                'images_url' => $article->images_url,
                'date_publish' => $article->date_publish,
                'category' => $article->category?->name,
            ])
            ->all();
    }
}
