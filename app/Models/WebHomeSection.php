<?php

namespace App\Models;

use App\Helpers\WebFileUploader;
use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu section di beranda fe-konexa (Superadmin > Web > Susunan Beranda),
 * atau di halaman landing (web_page_id diisi, Superadmin > Web > Halaman).
 *
 * Section BAWAAN (builtin) hanya menunjuk data dari menunya sendiri --
 * Headers, Pengaturan Web (running text), Packages, Fitur, FAQ -- dan
 * masing-masing cuma boleh ada satu. Section TAMBAHAN diisi langsung di
 * sini (item di WebHomeSectionItem) dan boleh dibuat berkali-kali.
 *
 * "Bingkai" (frame) = judul, subjudul, background, tombol CTA. Hero &
 * running text tidak punya bingkai (sudah diatur di menunya sendiri);
 * section bawaan lain punya bingkai tanpa background video.
 */
class WebHomeSection extends Model
{
    use HasUuids;

    protected $table = 'web_home_sections';

    /**
     * label, builtin (maks. 1), frame (punya bingkai), video (boleh
     * background video), items (punya item), content (teks + media),
     * articles (sumber artikel otomatis).
     *
     * @var array<string, array<string, mixed>>
     */
    public const TYPES = [
        'hero' => ['label' => 'Header / Hero', 'builtin' => true, 'frame' => false, 'source' => 'Web → Headers'],
        'running_text' => ['label' => 'Running Text', 'builtin' => true, 'frame' => false, 'source' => 'Web → Pengaturan Web'],
        'packages' => ['label' => 'Paket Layanan', 'builtin' => true, 'frame' => true, 'source' => 'Packages'],
        'features' => ['label' => 'Fitur Unggulan', 'builtin' => true, 'frame' => true, 'source' => 'Web → Fitur'],
        'faq' => ['label' => 'FAQ', 'builtin' => true, 'frame' => true, 'source' => 'Web → FAQ'],
        'articles' => ['label' => 'Artikel', 'frame' => true, 'video' => true, 'articles' => true, 'source' => 'Web → Artikel'],
        'icon_grid' => ['label' => 'Grid Ikon', 'frame' => true, 'video' => true, 'items' => true],
        'cards' => ['label' => 'Kartu', 'frame' => true, 'video' => true, 'items' => true],
        'logos' => ['label' => 'Logo', 'frame' => true, 'video' => true, 'items' => true],
        'stats' => ['label' => 'Statistik', 'frame' => true, 'video' => true, 'items' => true],
        'testimonials' => ['label' => 'Testimoni', 'frame' => true, 'video' => true, 'items' => true],
        'text_media' => ['label' => 'Teks + Media', 'frame' => true, 'video' => true, 'content' => true],
        'banner' => ['label' => 'Banner / CTA', 'frame' => true, 'video' => true],
    ];

    /**
     * Kolom item yang dipakai tiap tipe: field => [label, wajib?].
     *
     * @var array<string, array<string, array{0: string, 1: bool}>>
     */
    public const ITEM_FIELDS = [
        'icon_grid' => ['icon' => ['Ikon', false], 'image' => ['Gambar ikon (opsional, pengganti ikon)', false], 'title' => ['Judul', true], 'description' => ['Deskripsi', false]],
        'cards' => ['image' => ['Gambar', false], 'title' => ['Judul', true], 'description' => ['Deskripsi', false], 'value' => ['Harga / label (opsional)', false], 'link_text' => ['Teks tombol', false], 'link_url' => ['Link tombol', false]],
        'logos' => ['image' => ['Logo', true], 'title' => ['Nama', false], 'link_url' => ['Link (opsional)', false]],
        'stats' => ['icon' => ['Ikon (opsional)', false], 'value' => ['Angka, mis. 1.000+', true], 'title' => ['Keterangan', true]],
        'testimonials' => ['image' => ['Foto', false], 'title' => ['Nama', true], 'value' => ['Jabatan / perusahaan', false], 'description' => ['Kutipan', true]],
    ];

    protected $fillable = [
        'web_page_id',
        'type',
        'title',
        'subtitle',
        'background_type',
        'background_color',
        'background_image',
        'background_video',
        'text_align',
        'cta_text',
        'cta_link',
        'cta2_text',
        'cta2_link',
        'content',
        'media_image',
        'media_position',
        'item_limit',
        'web_category_article_id',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'item_limit' => 'integer',
        'sort_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WebHomeSectionItem::class)->orderBy('sort_order')->orderBy('created_at');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebPage::class, 'web_page_id');
    }

    /**
     * Section milik beranda (null) atau milik satu halaman landing.
     */
    public function scopeOfPage($query, ?string $pageId)
    {
        return $pageId ? $query->where('web_page_id', $pageId) : $query->whereNull('web_page_id');
    }

    /**
     * Tipe yang boleh dipasang di halaman landing: semua kecuali Hero &
     * Running Text (milik beranda; halaman punya header sendiri).
     */
    public static function allowedOnPage(string $type): bool
    {
        return isset(self::TYPES[$type]) && ! in_array($type, ['hero', 'running_text'], true);
    }

    /**
     * Hapus file upload section ini & item-nya.
     */
    public function deleteFiles(): void
    {
        WebImageUploader::delete($this->background_image);
        WebImageUploader::delete($this->media_image);
        WebFileUploader::delete($this->background_video);
        $this->items()->pluck('image')->each(fn (?string $path) => WebImageUploader::delete($path));
    }

    public function articleCategory(): BelongsTo
    {
        return $this->belongsTo(WebCategoryArticle::class, 'web_category_article_id');
    }

    public function typeConfig(string $key, mixed $default = false): mixed
    {
        return self::TYPES[$this->type][$key] ?? $default;
    }

    public function label(): string
    {
        return $this->typeConfig('label', $this->type);
    }

    public function isBuiltin(): bool
    {
        return (bool) $this->typeConfig('builtin');
    }

    public function hasFrame(): bool
    {
        return (bool) $this->typeConfig('frame');
    }

    public function allowsVideo(): bool
    {
        return (bool) $this->typeConfig('video');
    }

    public function hasItems(): bool
    {
        return (bool) $this->typeConfig('items');
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function itemFields(): array
    {
        return self::ITEM_FIELDS[$this->type] ?? [];
    }

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->background_image);
    }

    public function getBackgroundVideoUrlAttribute(): ?string
    {
        return WebFileUploader::url($this->background_video);
    }

    public function getMediaImageUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->media_image);
    }
}
