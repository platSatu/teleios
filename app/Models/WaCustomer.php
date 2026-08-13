<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM Roadmap Fase 0 — the one customer identity a phone number
 * resolves to within a company, regardless of whether that person is
 * known through the Inbox (App\Models\WaContact), the manually curated
 * Buku Telepon (App\Models\WaPhoneBook), or both. See the
 * wa_customers migration's docblock for the full design rationale and
 * why this sits ALONGSIDE those two tables instead of replacing either.
 *
 * Never created directly — always through
 * App\Services\Crm\CustomerIdentityService::resolve(), the one place
 * that owns the "find-or-create by (company_id, normalized phone)"
 * logic every caller needs to share.
 */
class WaCustomer extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wa_customers';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'phone',
        'name',
        'assigned_to',
        'created_by',
        'first_seen_at',
        'last_contacted_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_contacted_at' => 'datetime',
    ];

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
     * The Inbox-derived CRM record for this identity, if this person has
     * ever chatted in (one row at most — wa_contacts is unique on
     * (company_id, phone), same key this table resolves by).
     */
    public function contact()
    {
        return $this->hasOne(WaContact::class);
    }

    /**
     * The manually curated Buku Telepon entry for this identity, if one
     * exists (one row at most, same reasoning as contact() above).
     */
    public function phoneBookEntry()
    {
        return $this->hasOne(WaPhoneBook::class);
    }

    /**
     * CRM Roadmap Fase 2 — every follow-up task ever created for this
     * identity, newest first is left to the caller (no default order
     * here since Customer 360 and the Tugas & Follow-up page each want
     * their own).
     */
    public function tasks()
    {
        return $this->hasMany(WaCustomerTask::class);
    }

    /**
     * CRM Roadmap Fase 3 — every sales opportunity ever opened for this
     * identity. No default order for the same reason tasks() has none —
     * App\Http\Controllers\Crm\DealController and the Customer 360 panel
     * each want their own.
     */
    public function deals()
    {
        return $this->hasMany(WaDeal::class);
    }

    /**
     * CRM Roadmap Fase 4 — every App\Models\WaCustomerTag attached to
     * this identity. Attach/detach exclusively through
     * App\Http\Controllers\Crm\CustomerTagController so tag-added
     * automation rules (App\Services\Crm\CustomerAutomationService::
     * fireTagAdded()) never get bypassed by a direct attach() call.
     */
    public function tags()
    {
        return $this->belongsToMany(WaCustomerTag::class, 'wa_customer_tag_customer');
    }

    public static function normalizePhone(string $raw): string
    {
        return PhoneNumber::normalize($raw);
    }
}
