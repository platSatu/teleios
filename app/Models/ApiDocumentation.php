<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One documented endpoint (title, HTTP method, path, description, an
 * example request + response) shown on the public WhatsApp API
 * documentation site — GET /dokumentasi, no login required, see
 * PublicDocumentationController. Superadmin-managed —
 * Superadmin\ApiDocumentationController.
 */
class ApiDocumentation extends Model
{
    protected $table = 'api_documentations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'category_documentation_id',
        'title',
        'slug',
        'method',
        'endpoint',
        'description',
        'request_example',
        'response_example',
        'sort_order',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $article) {
            if (empty($article->id)) {
                $article->id = (string) Str::uuid();
            }
        });
    }

    public function categoryDocumentation()
    {
        return $this->belongsTo(CategoryDocumentation::class);
    }
}
