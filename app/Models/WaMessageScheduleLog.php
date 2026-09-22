<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One (recipient, calendar day, step) send attempt for a WaMessageSchedule
 * — see the migration that creates this table for why it replaced the
 * old sent_at/attempts/last_error columns on WaMessageSchedule itself.
 * `step_order` is 0 for 'once'/'recurring' schedules (single content, no
 * steps) and matches a WaMessageScheduleStep::sequence_order for 'drip'
 * schedules, which can send the same recipient several different
 * messages on several different days. Written by App\Console\Commands\
 * DispatchDueWaMessageSchedules (creates the 'pending' row the moment a
 * recipient/step becomes due, so it's never picked up twice) and
 * App\Jobs\SendScheduledWaMessage (flips it to 'sent'/'failed').
 */
class WaMessageScheduleLog extends Model
{
    protected $table = 'wa_message_schedule_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'wa_message_schedule_id',
        'recipient_key',
        'step_order',
        'send_date',
        'status',
        'message_id',
        'error',
        'sent_at',
        'attempts',
        'delivered_count',
        'read_count',
        'recipient_total',
    ];

    /**
     * Same "never move backwards" ladder as g_backend's own
     * messageStatusRank() (wa-inbox-service.go) — kept in sync
     * deliberately, since a delivery/read receipt forwarded by
     * WaMessageStatusWebhookController must never downgrade a row that
     * already progressed further. 'pending'/'failed' aren't part of this
     * ladder: 'pending' is the unsent starting state (rank 0, same as an
     * unrecognized value), and 'failed' is a send-attempt outcome set
     * only by SendScheduledWaMessage itself, never touched by a receipt.
     */
    public const STATUS_RANK = [
        'sent' => 1,
        'delivered' => 2,
        'read' => 3,
    ];

    /**
     * A recipient App\Jobs\SendScheduledWaMessage deliberately never
     * attempted to send to — currently only ever set when the recipient
     * has opted out of broadcasts (see App\Services\Chat\
     * BroadcastOptOutService). Distinct from 'failed': a failure implies
     * something went wrong that a retry or a fix might resolve, while
     * 'skipped' is a final, intentional "we are not allowed to message
     * this number" outcome — reported separately on the history page
     * rather than being lumped in with 'failed'.
     */
    public const STATUS_SKIPPED = 'skipped';

    protected $casts = [
        'step_order' => 'integer',
        'send_date' => 'date',
        'sent_at' => 'datetime',
        'attempts' => 'integer',
        'delivered_count' => 'integer',
        'read_count' => 'integer',
        'recipient_total' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $log) {
            if (empty($log->id)) {
                $log->id = (string) Str::uuid();
            }
        });
    }

    public function schedule()
    {
        return $this->belongsTo(WaMessageSchedule::class, 'wa_message_schedule_id');
    }

    /**
     * "6281234567890" from "phone:6281234567890" — the type/value split
     * used both when resolving a send target (SendScheduledWaMessage)
     * and when rendering a human-readable label on the history page.
     */
    public function recipientType(): string
    {
        return Str::before($this->recipient_key, ':');
    }

    public function recipientValue(): string
    {
        return Str::after($this->recipient_key, ':');
    }

    /**
     * "3/8 dibaca" for a group recipient whose size g_backend has
     * managed to fetch (recipient_total > 1) — null when there's nothing
     * more precise to show than the bare read_count (a 'phone'/'user'
     * recipient, where the denominator is always trivially 1 anyway, or
     * a group whose member count isn't known yet), so the history view
     * falls back to showing just the count on its own.
     */
    public function readFraction(): ?string
    {
        if ($this->recipient_total && $this->recipient_total > 1) {
            return "{$this->read_count}/{$this->recipient_total}";
        }

        return null;
    }

    public function deliveredFraction(): ?string
    {
        if ($this->recipient_total && $this->recipient_total > 1) {
            return "{$this->delivered_count}/{$this->recipient_total}";
        }

        return null;
    }
}
