<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * CRM Roadmap Fase 4 — a company-scoped tag (e.g. "VIP", "Reseller",
 * "Churn Risk") that can be attached to any number of
 * App\Models\WaCustomer identities. Managed inline from the Customer
 * 360 page and the "Segmentasi" catalog panel — see
 * App\Http\Controllers\Crm\CustomerTagController.
 */
class WaCustomerTag extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wa_customer_tags';

    protected $fillable = [
        'company_id',
        'name',
        'color',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(WaCustomer::class, 'wa_customer_tag_customer');
    }
}
