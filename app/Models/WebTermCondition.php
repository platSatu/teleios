<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One "Syarat dan Ketentuan" (Terms & Conditions) entry — same flat shape
 * as App\Models\WebFaq (title in `name`, body in `descriptions`).
 * Superadmin-managed via App\Http\Controllers\Superadmin\Web\
 * TermConditionController. Only one row is expected to have
 * `status === 'active'` at a time — that's the version shown in the
 * register-page popup and stamped onto a newly-registered
 * App\Models\User via `users.terms_id`.
 */
class WebTermCondition extends Model
{
    protected $table = 'web_term_conditions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'descriptions',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The single row currently shown to new registrants — most recently
     * updated 'active' row, so editing an existing entry (rather than
     * creating a new one) is enough to make it "the" current version.
     */
    public static function current(): ?self
    {
        return static::where('status', 'active')->latest('updated_at')->first();
    }
}
