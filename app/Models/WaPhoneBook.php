<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A company's phone book entry (Chat > Buku Telepon) — see the
 * migration's docblock for how this differs from App\Models\WaContact
 * and for the `is_blacklisted` design. Always belongs to exactly one
 * App\Models\WaCategoryPhoneBook "Kelompok".
 */
class WaPhoneBook extends Model
{
    protected $table = 'wa_phone_book';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'wa_category_phone_book_id',
        'created_by',
        'name',
        'phone',
        'email',
        'status',
        'is_blacklisted',
        'blacklist_reason',
        'blacklisted_at',
    ];

    protected $casts = [
        'is_blacklisted' => 'boolean',
        'blacklisted_at' => 'datetime',
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

    public function category()
    {
        return $this->belongsTo(WaCategoryPhoneBook::class, 'wa_category_phone_book_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Digits only, no leading '+', e.g. "6281234567890" — same
     * normalization rule as WaContact::normalizePhone(), so a number
     * entered/imported in any common format still de-dupes correctly
     * against the unique(company_id, phone) constraint.
     */
    public static function normalizePhone(string $raw): string
    {
        return preg_replace('/\D/', '', $raw) ?? '';
    }
}
