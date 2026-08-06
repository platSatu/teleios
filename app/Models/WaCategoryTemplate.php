<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A company-defined category for organizing its WA Templates (e.g.
 * "Promo", "Reminder") — free-form, not locked to Meta's fixed
 * Marketing/Utility/Authentication set. See the create_wa_category_templates_table
 * migration for the two independent status axes (`status` = company's
 * own on/off toggle, `review_status` = superadmin approval gate).
 */
class WaCategoryTemplate extends Model
{
    protected $table = 'wa_category_templates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'status',
        'review_status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WaMessageTemplate::class, 'wa_category_template_id');
    }

    /**
     * Selectable on the template form: company has it switched on AND
     * a superadmin has approved it. Both conditions, not just one — an
     * approved-but-disabled category shouldn't reappear in the picker,
     * and a pending one obviously can't be used yet either.
     */
    public function scopeUsable($query)
    {
        return $query->where('status', 'active')->where('review_status', 'approved');
    }
}
