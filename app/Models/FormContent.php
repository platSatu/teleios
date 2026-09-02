<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu pertanyaan/field dalam satu App\Models\FormHeader -- lihat
 * 2026_09_12_090200_create_form_contents_table.php's docblock untuk
 * kenapa PDF/JPG/JPEG/PNG bukan TYPE tersendiri (itu masuk
 * `allowed_file_types` di bawah TYPE_FILE_UPLOAD).
 */
class FormContent extends Model
{
    protected $table = 'form_contents';

    protected $keyType = 'string';

    public $incrementing = false;

    public const TYPE_SINGLE_LINE = 'single_line';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_SINGLE_CHOICE = 'single_choice';

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPE_FILE_UPLOAD = 'file_upload';

    public const TYPES = [
        self::TYPE_SINGLE_LINE,
        self::TYPE_TEXTAREA,
        self::TYPE_SINGLE_CHOICE,
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_FILE_UPLOAD,
    ];

    /** Tipe yang butuh `options` diisi (daftar pilihan jawaban). */
    public const CHOICE_TYPES = [self::TYPE_SINGLE_CHOICE, self::TYPE_MULTIPLE_CHOICE];

    public const DEFAULT_ALLOWED_FILE_TYPES = 'pdf,jpg,jpeg,png';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'form_category_id',
        'form_header_id',
        'name',
        'type',
        'options',
        'allowed_file_types',
        'is_required',
        'position',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'position' => 'integer',
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

    public function answers()
    {
        return $this->hasMany(FormSubmissionAnswer::class);
    }

    public function isChoiceType(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }
}
