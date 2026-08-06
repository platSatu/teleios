<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A company's phone book group ("Kelompok") — see the migration's
 * docblock for the table/column naming rationale. Managed from Chat >
 * Buku Telepon > Kelompok (App\Http\Controllers\Chat\CategoryPhoneBookController);
 * each App\Models\WaPhoneBook entry belongs to exactly one of these.
 */
class WaCategoryPhoneBook extends Model
{
    protected $table = 'wa_category_phone_book';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'created_by',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function phoneBooks()
    {
        return $this->hasMany(WaPhoneBook::class, 'wa_category_phone_book_id');
    }
}
