<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A keyword-triggered auto reply: when an incoming message matches
 * `keyword` (per match_type), `reply_message` is sent back. See
 * App\Http\Controllers\Api\WaIncomingMessageWebhookController for what
 * actually evaluates the match (triggered by a webhook from the Go
 * backend) and App\Jobs\SendAutoReplyMessage for what sends it.
 *
 * `is_default` (at most one active per device_id, enforced by
 * MessageAutoReplyController) marks the rule sent when NO keyword
 * matches — this is what lets a company build a numbered menu ("1.
 * Jadwal, 2. Pembayaran, 3. Daftar User") purely out of ordinary rules:
 * the default rule's reply_message IS the menu text, and "1"/"2"/"3"
 * are just regular exact-match keyword rules underneath it.
 *
 * `reply_message` can also embed {{tag}} placeholders (see
 * App\Services\Chat\AutoReplyTagResolver::availableTags()) resolved
 * against this company's live data at send time — e.g. {{jadwal_aktif}}
 * becomes a real, current list of this company's active Pesan Terjadwal
 * rows, instead of every company having to hand-type and keep that list
 * up to date themselves.
 */
class WaMessageAutoReply extends Model
{
    protected $table = 'wa_message_auto_replies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'device_id',
        'keyword',
        'match_type',
        'is_default',
        'reply_message',
        'status',
        'last_triggered_at',
        'trigger_count',
        'last_error',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
    ];

    /**
     * True if `body` should trigger this rule, per its match_type. A
     * default (`is_default`) rule has no keyword of its own — it's never
     * matched this way, only used as the webhook's fallback when nothing
     * else matches — so this returns false rather than mis-evaluating a
     * null keyword.
     */
    public function matches(string $body): bool
    {
        if ($this->keyword === null || $this->keyword === '') {
            return false;
        }

        return match ($this->match_type) {
            'exact' => mb_strtolower(trim($body)) === mb_strtolower(trim($this->keyword)),
            default => mb_stripos($body, $this->keyword) !== false, // 'contains'
        };
    }

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
}
