<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pesan di ChatWidgetConversation. id auto-increment = urutan & penanda polling. */
class ChatWidgetMessage extends Model
{
    public const UPDATED_AT = null;

    public const SENDER_VISITOR = 'visitor';

    public const SENDER_AI = 'ai';

    public const SENDER_AGENT = 'agent';

    public const SENDER_SYSTEM = 'system';

    protected $fillable = ['chat_widget_conversation_id', 'sender', 'user_id', 'body'];

    protected $casts = ['created_at' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatWidgetConversation::class, 'chat_widget_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Bentuk aman untuk dikirim ke browser (widget & inbox). */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'name' => $this->sender === self::SENDER_AGENT ? ($this->user?->name ?? 'CS') : null,
            'body' => $this->body,
            'at' => $this->created_at?->format('H:i'),
        ];
    }
}
