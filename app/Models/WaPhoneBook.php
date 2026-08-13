<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * A company's phone book entry (Chat > Buku Telepon) — see the
 * migration's docblock for how this differs from App\Models\WaContact
 * and for the `is_blacklisted` design. Always belongs to exactly one
 * App\Models\WaCategoryPhoneBook "Kelompok".
 *
 * wa_customer_id links this to the CRM Roadmap Fase 0 customer identity
 * (App\Models\WaCustomer) — see that model's docblock. Set by
 * App\Services\Crm\CustomerIdentityService, never directly.
 */
class WaPhoneBook extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wa_phone_book';

    protected $fillable = [
        'wa_customer_id',
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

    public function customer()
    {
        return $this->belongsTo(WaCustomer::class, 'wa_customer_id');
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
     * Digits only, no leading '+', e.g. "6281234567890" — delegates to
     * App\Support\PhoneNumber, the same shared rule WaContact::
     * normalizePhone() and WaCustomer::normalizePhone() use, so a number
     * entered/imported in any common format still de-dupes correctly
     * against the unique(company_id, phone) constraint.
     */
    public static function normalizePhone(string $raw): string
    {
        return PhoneNumber::normalize($raw);
    }
}
