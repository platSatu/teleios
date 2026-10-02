<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu file backup database (.sql.gz), lihat App\Services\Backup\DatabaseBackupService. */
class DatabaseBackup extends Model
{
    use HasUuids;

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_MANUAL = 'manual';

    protected $fillable = [
        'filename',
        'size_bytes',
        'status',
        'trigger',
        'created_by',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_SUCCESS && $this->filename !== null;
    }

    /** Ukuran yang mudah dibaca, mis. "12,4 MB". */
    public function humanSize(): string
    {
        $bytes = (float) ($this->size_bytes ?? 0);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i === 0 ? 0 : 1, ',', '.').' '.$units[$i];
    }
}
