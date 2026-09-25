<?php

namespace App\Support;

use App\Models\WebArticle;
use App\Models\WebHomeSection;
use App\Models\WebHomeSectionItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Bentuk JSON section untuk fe-konexa -- dipakai bersama oleh API beranda
 * (Api\Frontend\HomeSectionController) dan halaman landing
 * (Api\Frontend\PageController), supaya kedua tampilan membaca format
 * yang sama.
 */
final class HomeSectionPresenter
{
    /**
     * Section aktif yang layak tampil (tipe dikenal; section item tanpa
     * item dilewati), sudah dalam bentuk array.
     *
     * @param  Collection<int, WebHomeSection>  $sections
     * @return Collection<int, array<string, mixed>>
     */
    public function presentMany(Collection $sections): Collection
    {
        return $sections
            ->reject(fn (WebHomeSection $section) => ! isset(WebHomeSection::TYPES[$section->type])
                || ($section->hasItems() && $section->items->isEmpty()))
            ->map(fn (WebHomeSection $section) => $this->present($section))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(WebHomeSection $section): array
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
