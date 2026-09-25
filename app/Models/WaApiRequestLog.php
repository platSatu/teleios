<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu request pihak ketiga ke WA API yang lolos
 * otentikasi. Lihat migration create_wa_api_request_logs_table untuk
 * alasan tabel ini ada, dan App\Services\Chat\WaApiUsageService untuk
 * cara pencatatan & ringkasannya.
 */
class WaApiRequestLog extends Model
{
    use HasUuids;

    protected $table = 'wa_api_request_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_SENT = 'sent';
    public const STATUS_BLOCKED_PACKAGE = 'blocked_package';
    public const STATUS_BLOCKED_QUOTA = 'blocked_quota';
    public const STATUS_FAILED = 'failed';

    public const ENDPOINT_SEND_MESSAGE = 'send-message';

    protected $fillable = [
        'company_id',
        'wa_api_key_id',
        'device_id',
        'endpoint',
        'recipient',
        'message_length',
        'status',
        'http_status',
        'wa_message_id',
        'error',
        'ip_address',
    ];

    protected $casts = [
        'message_length' => 'integer',
        'http_status' => 'integer',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_SENT => 'Terkirim',
            self::STATUS_BLOCKED_PACKAGE => 'Ditolak (paket tidak aktif)',
            self::STATUS_BLOCKED_QUOTA => 'Ditolak (kuota habis)',
            self::STATUS_FAILED => 'Gagal kirim',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(WaApiKey::class, 'wa_api_key_id');
    }
}
