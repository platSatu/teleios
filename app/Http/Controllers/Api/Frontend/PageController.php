<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebPage;
use App\Support\HomeSectionPresenter;
use Illuminate\Http\JsonResponse;

/**
 * Halaman dinamis untuk fe-konexa (/page/{slug}). Hanya status active.
 * index() = daftar link navbar/footer; show() = isi satu halaman (Dokumen:
 * teks Markdown mentah, dirender aman di fe-konexa; Landing: section dengan
 * format yang sama seperti beranda). Gated VerifyFrontendApiKey.
 */
class PageController extends Controller
{
    public function index(): JsonResponse
    {
        $pages = WebPage::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->where('show_in_navbar', true)->orWhere('show_in_footer', true))
            ->orderBy('title')
            ->get(['title', 'slug', 'show_in_navbar', 'navbar_order', 'show_in_footer', 'footer_group', 'footer_order']);

        return response()->json(['data' => $pages]);
    }

    public function show(string $slug, HomeSectionPresenter $presenter): JsonResponse
    {
        $page = WebPage::query()->where('status', 'active')->where('slug', $slug)->first();

        if (! $page) {
            return response()->json(['message' => 'Halaman tidak ditemukan.'], 404);
        }

        $sections = $page->isLanding()
            ? $presenter->presentMany($page->sections()->where('status', 'active')->with('items')->get())
            : [];

        return response()->json(['data' => [
            'title' => $page->title,
            'slug' => $page->slug,
            'type' => $page->type,
            'subtitle' => $page->subtitle,
            'hero_image_url' => $page->hero_image_url,
            'content' => $page->isLanding() ? null : $page->content,
            'meta_description' => $page->meta_description,
            'meta_image_url' => $page->meta_image_url,
            'updated_at' => $page->updated_at,
            'sections' => $sections,
        ]]);
    }
}
