<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu sesi chat pengunjung website di sebuah ChatWidget. */
class ChatWidgetConversation extends Model
{
    use HasUuids;

    public const STATUS_AI = 'ai';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_AGENT = 'agent';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_LABELS = [
        self::STATUS_AI => 'Dijawab AI',
        self::STATUS_WAITING => 'Butuh CS',
        self::STATUS_AGENT => 'Ditangani CS',
        self::STATUS_CLOSED => 'Selesai',
    ];

    protected $fillable = [
        'chat_widget_id',
        'visitor_token_hash',
        'visitor_name',
        'visitor_phone',
        'page_url',
        'status',
        'assigned_to',
        'ip_address',
        'last_message_at',
    ];

    protected $hidden = ['visitor_token_hash'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(ChatWidget::class, 'chat_widget_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatWidgetMessage::class)->orderBy('id');
    }

    public function displayName(): string
    {
        return $this->visitor_name ?: 'Pengunjung '.strtoupper(substr($this->id, 0, 4));
    }
}
