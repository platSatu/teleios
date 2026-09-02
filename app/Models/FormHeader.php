<?php

namespace App\Models;

use App\Helpers\FormImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu form publik yang bisa diisi lewat app.konexa.id/{slug} -- lihat
 * 2026_09_12_090100_create_form_headers_table.php's docblock.
 */
class FormHeader extends Model
{
    protected $table = 'form_headers';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'form_category_id',
        'name',
        'slug',
        'background',
        'description',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function formCategory()
    {
        return $this->belongsTo(FormCategory::class);
    }

    public function contents()
    {
        return $this->hasMany(FormContent::class)->orderBy('position');
    }

    public function footers()
    {
        return $this->hasMany(FormFooter::class);
    }

    public function setting()
    {
        return $this->hasOne(FormSetting::class);
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Public URL of `background`, or null when none is set -- see
     * App\Helpers\FormImageUploader::url().
     */
    public function getBackgroundUrlAttribute(): ?string
    {
        return FormImageUploader::url($this->background);
    }

    /**
     * True kalau form ini boleh menerima submission publik SEKARANG --
     * dicek App\Http\Controllers\Form\PublicFormController sebelum
     * menampilkan/menerima form, bukan cuma mengandalkan `status`.
     */
    public function isOpenForSubmission(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = now();

        return $now->greaterThanOrEqualTo($this->start_date) && $now->lessThanOrEqualTo($this->end_date);
    }
}
