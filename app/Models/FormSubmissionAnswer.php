<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu jawaban (untuk satu App\Models\FormContent) dalam satu
 * App\Models\FormSubmission -- lihat
 * 2026_09_12_090600_create_form_submission_answers_table.php's
 * docblock, termasuk kenapa `value` dipakai untuk multiple_choice juga
 * (JSON-encoded, di-decode lewat decodedValue() di bawah).
 */
class FormSubmissionAnswer extends Model
{
    protected $table = 'form_submission_answers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'form_submission_id',
        'form_content_id',
        'value',
        'file_path',
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

    public function formSubmission()
    {
        return $this->belongsTo(FormSubmission::class);
    }

    public function formContent()
    {
        return $this->belongsTo(FormContent::class);
    }

    /**
     * `value` untuk type=multiple_choice disimpan JSON-encoded (lihat
     * docblock migration-nya) -- helper ini men-decode-nya balik jadi
     * array untuk dipakai di view, no-op (return string aslinya) untuk
     * tipe jawaban lain.
     */
    public function decodedValue(): array|string|null
    {
        if (! $this->value) {
            return $this->value;
        }

        $decoded = json_decode($this->value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : $this->value;
    }
}
