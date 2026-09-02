<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Pengaturan post-submit 1-ke-1 per App\Models\FormHeader -- lihat
 * 2026_09_12_090400_create_form_settings_table.php's docblock. Gating
 * WA blast (`notify_wa_enabled` cuma benar-benar jalan kalau company
 * subscribe kategori "Whatsapp Blast") ada di App\Http\Controllers\
 * Form\PublicFormController, BUKAN di sini -- model ini murni data.
 */
class FormSetting extends Model
{
    protected $table = 'form_settings';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'form_category_id',
        'form_header_id',
        'device_id',
        'notify_wa_enabled',
        'wa_message_template_id',
        'additional_info',
        'status',
    ];

    protected $casts = [
        'notify_wa_enabled' => 'boolean',
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

    public function waMessageTemplate()
    {
        return $this->belongsTo(WaMessageTemplate::class);
    }
}
