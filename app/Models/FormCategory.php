<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Level paling atas dari fitur Form -- lihat
 * 2026_09_12_090000_create_form_categories_table.php's docblock.
 * Selalu milik SATU branch (branch_office_id wajib diisi, beda dari
 * App\Models\JadwalMataPelajaran yang nullable).
 */
class FormCategory extends Model
{
    protected $table = 'form_categories';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'branch_office_id',
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function headers()
    {
        return $this->hasMany(FormHeader::class)->orderBy('created_at');
    }
}
