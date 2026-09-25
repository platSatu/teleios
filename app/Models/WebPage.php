<?php

namespace App\Models;

use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Halaman dinamis website (fe-konexa /page/{slug}). Lihat migration
 * create_web_pages_table untuk arti tiap kolom & tipe.
 */
class WebPage extends Model
{
    use HasUuids;

    protected $table = 'web_pages';

    public const TYPES = [
        'document' => 'Dokumen (teks panjang + daftar isi)',
        'landing' => 'Landing (disusun dari section)',
    ];

    protected $fillable = [
        'title',
        'slug',
        'type',
        'subtitle',
        'hero_image',
        'content',
        'meta_description',
        'meta_image',
        'show_in_navbar',
        'navbar_order',
        'show_in_footer',
        'footer_group',
        'footer_order',
        'status',
    ];

    protected $casts = [
        'show_in_navbar' => 'boolean',
        'show_in_footer' => 'boolean',
        'navbar_order' => 'integer',
        'footer_order' => 'integer',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(WebHomeSection::class)->orderBy('sort_order')->orderBy('created_at');
    }

    public function isLanding(): bool
    {
        return $this->type === 'landing';
    }

    public function getHeroImageUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->hero_image);
    }

    public function getMetaImageUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->meta_image ?: $this->hero_image);
    }

    /**
     * Hapus semua file upload halaman ini beserta section & item-nya
     * (baris DB section/item ikut terhapus lewat cascade).
     */
    public function deleteFiles(): void
    {
        WebImageUploader::delete($this->hero_image);
        WebImageUploader::delete($this->meta_image);
        $this->sections()->with('items')->get()->each(fn (WebHomeSection $section) => $section->deleteFiles());
    }
}
