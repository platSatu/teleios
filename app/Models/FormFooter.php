<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Blok penutup App\Models\FormHeader -- lihat
 * 2026_09_12_090300_create_form_footers_table.php's docblock untuk
 * kenapa tidak ada form_content_id di sini walau disebutkan di spek
 * awal.
 */
class FormFooter extends Model
{
    protected $table = 'form_footers';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'form_category_id',
        'form_header_id',
        'name',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function formHeader()
    {
        return $this->belongsTo(FormHeader::class);
    }

    public function formCategory()
    {
        return $this->belongsTo(FormCategory::class);
    }
}
