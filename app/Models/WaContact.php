<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A company's CRM contact — see the migration's docblock
 * (database/migrations/2026_08_05_200000_create_wa_contacts_table.php)
 * for why this is keyed by phone number rather than chat_jid. Auto-
 * created/refreshed by App\Http\Controllers\Chat\InboxController::contact()
 * whenever a chat is opened; managed in bulk from the Kontak page
 * (App\Http\Controllers\Chat\ContactController).
 */
class WaContact extends Model
{
    protected $table = 'wa_contacts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $contact) {
            if (empty($contact->id)) {
                $contact->id = (string) Str::uuid();
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
     * here keys on. Safe to call on an already-clean value.
     */
    public static function normalizePhone(string $raw): string
    {
        return preg_replace('/\D/', '', $raw) ?? '';
    }
}
