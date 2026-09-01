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
 * added the builder columns (category/header/footer/buttons/
 * review_status) for the full reasoning. usable() below is the one
 * query every "can this actually be sent" caller should go through
 * rather than re-deriving the same two-column check inline.
 *
 * `language` sempat ada di sini (lihat migration
 * drop_language_from_wa_message_templates_table.php) tapi dihapus --
 * tidak pernah benar-benar dipakai di manapun (bukan buat filter,
 * bukan buat moderasi, bukan buat pemilihan template), murni field
 * dekoratif yang tidak berpengaruh ke fungsi apa pun.
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
     * The actual text that goes out over WhatsApp for this template —
     * header + body + link + buttons(-as-text) + footer, in that order,
     * blank separated. Before this existed, every send path
     * (App\Jobs\SendScheduledWaMessage) only ever forwarded `template`
     * (the body column), silently dropping header/footer/link/buttons
     * even though the builder form lets a company fill all of them in.
     *
     * `buttons` can't become real tappable WhatsApp buttons this way —
     * that needs a WhatsApp interactive-message protocol type
     * (waE2E.Message_ButtonsMessage / InteractiveMessage) that
     * g_backend's whatsmeow integration doesn't build yet (it only ever
     * sends a bare Conversation-type text message). Rendering each
     * button as a plain, readable line is the honest fallback until that
     * Go-side work exists — better than the button silently vanishing
     * with no trace at all.
     */
    public function composedMessage(): string
    {
        $parts = [];

        if (filled($this->header)) {
            $parts[] = trim($this->header);
        }

        if (filled($this->template)) {
            $parts[] = trim($this->template);
        }

        // Only text_link/text_link_file templates carry a link — a plain
        // 'text' template's `link` column is expected to be empty, but
        // filled() guards against a stray value left over from switching
        // content_type back and forth in the builder.
        if (filled($this->link)) {
            $parts[] = trim($this->link);
        }

        $buttonLines = collect($this->buttons ?? [])
            ->map(function (array $button) {
                $label = trim((string) ($button['label'] ?? ''));
                $value = trim((string) ($button['value'] ?? ''));

                if ($label === '') {
                    return null;
                }

                return match ($button['type'] ?? null) {
                    'url' => $value !== '' ? "🔗 {$label}: {$value}" : "🔗 {$label}",
                    'phone' => $value !== '' ? "📞 {$label}: {$value}" : "📞 {$label}",
                    default => "• {$label}",
                };
            })
            ->filter()
            ->values();

        if ($buttonLines->isNotEmpty()) {
            $parts[] = $buttonLines->implode("\n");
        }

        if (filled($this->footer)) {
            $parts[] = trim($this->footer);
        }

        return implode("\n\n", $parts);
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
