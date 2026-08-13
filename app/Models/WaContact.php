<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * A company's CRM contact — see the migration's docblock
 * (database/migrations/2026_08_05_200000_create_wa_contacts_table.php)
 * for why this is keyed by phone number rather than chat_jid. Auto-
 * created/refreshed by App\Http\Controllers\Chat\InboxController::contact()
 * whenever a chat is opened; managed in bulk from the Kontak page
 * (App\Http\Controllers\Chat\ContactController).
 *
 * wa_customer_id links this to the CRM Roadmap Fase 0 customer identity
 * (App\Models\WaCustomer) — see that model's docblock. Set by
 * App\Services\Crm\CustomerIdentityService, never directly.
 */
class WaContact extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wa_contacts';

    protected $fillable = [
        'wa_customer_id',
        'company_id',
        'branch_office_id',
        'phone',
        'name',
        'assigned_to',
        'source',
        'created_by',
        'last_contacted_at',
    ];

    protected $casts = [
        'last_contacted_at' => 'datetime',
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

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Normalizes a raw phone/JID-user-part string down to digits only,
     * no leading '+' — the shape stored in `phone` and what every lookup
     * here keys on. Safe to call on an already-clean value. Delegates to
     * App\Support\PhoneNumber, the shared rule App\Models\WaCustomer and
     * App\Models\WaPhoneBook also normalize by.
     */
    public static function normalizePhone(string $raw): string
    {
        return PhoneNumber::normalize($raw);
    }
}
