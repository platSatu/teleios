<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu kali submit dari App\Models\FormHeader publik -- lihat
 * 2026_09_12_090500_create_form_submissions_table.php's docblock untuk
 * kenapa tabel ini ditambahkan di luar spek awal (tempat menampung
 * hasil isian, tanpa ini form builder tidak ada gunanya).
 */
class FormSubmission extends Model
{
    protected $table = 'form_submissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'form_category_id',
        'form_header_id',
        'ip_address',
        'user_agent',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
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
}
