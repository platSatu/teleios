<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A company's reusable WhatsApp message template ("WA Template" under
 * Chat > Pengaturan > Pesan). Picked from App\Models\WaMessageSchedule
 * via `use_template` + `wa_message_template_id` — deliberately a live
 * reference rather than copying `template` into the schedule's own
 * `message` column at save time, so editing a template here also
 * updates every future occurrence of any recurring schedule that still
 * points at it (see App\Jobs\SendScheduledWaMessage).
 *
 * `status` (active|inactive) and `review_status` (draft|in_review|
 * approved|rejected) are independent axes — see the migration that
 * added the builder columns (category/language/header/footer/buttons/
 * review_status) for the full reasoning. usable() below is the one
 * query every "can this actually be sent" caller should go through
 * rather than re-deriving the same two-column check inline.
 */
class WaMessageTemplate extends Model
{
    protected $table = 'wa_message_templates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'wa_category_template_id',
        'name',
        'language',
        'header',
        'template',
        'footer',
        'buttons',
        'variables_example',
        'recipients',
        'content_type',
        'link',
        'attachment_path',
        'attachment_type',
        'attachment_original_name',
        'attachment_size',
        'status',
        'review_status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'buttons' => 'array',
        'variables_example' => 'array',
        'recipients' => 'array',
        'attachment_size' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /**
     * text | text_link | text_link_file — hardcoded content shape for
     * the builder form, same idea as WaMessageSchedule's category_schedule
     * for manual (non-template) messages. Each tier is a superset of the
     * previous one: text_link adds `link` on top of text's judul+isi
     * pesan, text_link_file additionally adds the attachment upload.
     */
    public const CONTENT_TYPES = ['text', 'text_link', 'text_link_file'];

    /**
     * Matches WhatsApp's own {{1}}/{{name}} placeholder syntax —
     * alphanumeric + underscore only, no spaces or punctuation, so a
     * stray "{{" typed as literal text in a message never gets
     * misread as a variable. Used by detectedVariables() below and by
     * the live preview's client-side mirror of this same regex.
     */
    public const VARIABLE_PATTERN = '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/';

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $template) {
            if (empty($template->id)) {
                $template->id = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WaCategoryTemplate::class, 'wa_category_template_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(WaMessageSchedule::class, 'wa_message_template_id');
    }

    /**
     * "phone:6281234567890" style keys for every recipient saved on this
     * template — same format/derivation as
     * WaMessageSchedule::recipientKeys(), kept identical on purpose so
     * whichever side ends up owning "who does this actually send to"
     * for a given schedule can reuse the same key shape either way.
     */
    public function recipientKeys(): array
    {
        return collect($this->recipients ?? [])
            ->map(fn (array $r) => ($r['type'] ?? '').':'.($r['value'] ?? ''))
            ->filter(fn (string $key) => $key !== ':')
            ->values()
            ->all();
    }

    /**
     * Every distinct {{variable}} name used across header + body +
     * footer, in first-appearance order — re-derived from the text
     * itself every time rather than stored, so it can never drift out
     * of sync with what the message actually contains (only the
     * example *values* for each name are persisted, in
     * `variables_example`).
     *
     * @return array<int, string>
     */
    public function detectedVariables(): array
    {
        $haystack = implode(' ', array_filter([$this->header, $this->template, $this->footer]));

        preg_match_all(self::VARIABLE_PATTERN, $haystack, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Selectable for actually sending: company has it switched on AND
     * a superadmin has approved its content. An in_review/rejected/
     * draft template — or one whose category isn't itself approved —
     * must never show up as a send option.
     */
    public function scopeUsable($query)
    {
        return $query
            ->where('status', 'active')
            ->where('review_status', 'approved')
            // Templates created before the category builder existed have
            // no wa_category_template_id at all — those stay usable on
            // their own two columns above. A template that DOES have a
            // category additionally needs that category itself to still
            // be active+approved.
            ->where(function ($q) {
                $q->whereNull('wa_category_template_id')
                    ->orWhereHas('category', fn ($qq) => $qq->usable());
            });
    }
}
