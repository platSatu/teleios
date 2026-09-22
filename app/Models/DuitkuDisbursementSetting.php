<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singleton settings row — kredensial Duitku Disbursement (userId +
 * email + secretKey), TERPISAH dari App\Models\DuitkuSetting (yang
 * dipakai App\Services\Payment\DuitkuService untuk terima pembayaran
 * lewat merchantCode + apiKey). Lihat migration's docblock untuk alasan
 * lengkap kenapa dua tabel.
 *
 * Selalu diakses lewat current(), sama pola lazy-singleton dengan
 * DuitkuSetting/AiModerationSetting.
 */
class DuitkuDisbursementSetting extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'duitku_disbursement_settings';

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_PRODUCTION = 'production';

    public const MODES = [self::MODE_SANDBOX, self::MODE_PRODUCTION];

    protected $fillable = [
        'mode',
        'sandbox_user_id',
        'sandbox_email',
        'sandbox_secret_key',
        'production_user_id',
        'production_email',
        'production_secret_key',
        'updated_by',
    ];

    protected $casts = [
        'sandbox_secret_key' => 'encrypted',
        'production_secret_key' => 'encrypted',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create(['mode' => self::MODE_SANDBOX]);
    }

    public function isSandbox(): bool
    {
        return $this->mode !== self::MODE_PRODUCTION;
    }

    public function activeUserId(): ?string
    {
        return $this->isSandbox() ? $this->sandbox_user_id : $this->production_user_id;
    }

    public function activeEmail(): ?string
    {
        return $this->isSandbox() ? $this->sandbox_email : $this->production_email;
    }

    public function activeSecretKey(): ?string
    {
        return $this->isSandbox() ? $this->sandbox_secret_key : $this->production_secret_key;
    }

    /**
     * Sama seperti DuitkuSetting::isConfigured() — hanya mode yang
     * SEDANG aktif yang wajib lengkap, pasangan mode satunya boleh
     * kosong (superadmin baru isi sandbox dulu itu wajar).
     */
    public function isConfigured(): bool
    {
        return filled($this->activeUserId()) && filled($this->activeEmail()) && filled($this->activeSecretKey());
    }
}
