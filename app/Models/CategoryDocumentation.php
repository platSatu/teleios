<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A section in the public WhatsApp API documentation site (e.g.
 * "Autentikasi", "Kirim Pesan") — see App\Models\ApiDocumentation for the
 * articles grouped under it, and PublicDocumentationController for where
 * this is actually rendered (GET /dokumentasi, no login required).
 * Superadmin-managed — Superadmin\CategoryDocumentationController.
 */
class CategoryDocumentation extends Model
{
    protected $table = 'category_documentations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $category) {
            if (empty($category->id)) {
                $category->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function apiDocumentations()
    {
        return $this->hasMany(ApiDocumentation::class)->orderBy('sort_order');
    }
}
