<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Satu widget Live Chat (6 Oktober 2026) milik company/cabang. Dipasang
 * di website klien lewat public/widget.js + public_key; hanya bisa
 * di-embed di allowed_domains (CSP frame-ancestors, lihat
 * Chat\Widget\ChatWidgetPublicController::frame()).
 *
 * Fitur ini sengaja terpisah (semua file berawalan ChatWidget*, tabel
 * chat_widget*). Cara membuangnya bersih ada di README fitur:
 * docs/live-chat-widget.md.
 */
class ChatWidget extends Model
{
    use HasUuids;

    public const DEFAULT_SETTINGS = [
        'color' => '#2563eb',
        'position' => 'right',
        'title' => 'Tanya kami',
        'greeting' => 'Halo! Ada yang bisa kami bantu?',
        'require_contact' => false,
    ];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'name',
        'public_key',
        'allowed_domains',
        'settings',
        'ai_enabled',
        'status',
        'last_seen_at',
        'last_seen_domain',
    ];

    protected $casts = [
        'allowed_domains' => 'array',
        'settings' => 'array',
        'ai_enabled' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $widget) {
            $widget->public_key ??= self::newPublicKey();
        });
    }

    public static function newPublicKey(): string
    {
        return 'wgt_'.Str::random(40);
    }

    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Nilai CSP frame-ancestors: domain terdaftar + semua subdomainnya (port apa pun). */
    public function frameAncestors(): string
    {
        return collect($this->allowed_domains ?? [])
            ->flatMap(fn (string $domain) => ["https://{$domain}:*", "https://*.{$domain}:*", "http://{$domain}:*", "http://*.{$domain}:*"])
            ->implode(' ') ?: "'none'";
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatWidgetConversation::class);
    }
}
