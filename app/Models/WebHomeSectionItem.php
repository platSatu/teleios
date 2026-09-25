<?php

namespace App\Models;

use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu item di section tambahan beranda (ikon, kartu, logo, statistik,
 * testimoni). Kolom yang dipakai tergantung tipe section -- lihat
 * WebHomeSection::ITEM_FIELDS.
 */
class WebHomeSectionItem extends Model
{
    use HasUuids;

    protected $table = 'web_home_section_items';

    protected $fillable = [
        'web_home_section_id',
        'icon',
        'image',
        'title',
        'description',
        'value',
        'link_text',
        'link_url',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(WebHomeSection::class, 'web_home_section_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->image);
    }
}
