<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
        'created_at',
    ];


    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
    ];


    /**
     * Generate UUID otomatis
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }

        });
    }


    /**
     * Catat satu aksi oleh user yang sedang login (atau null = sistem).
     * user_agent dipotong ke 255 karakter sesuai kolomnya.
     */
    public static function record(string $action, string $entityType, string $entityId, ?array $old = null, ?array $new = null, ?User $actor = null): self
    {
        $actor ??= auth()->user();

        return self::create([
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_value' => $old,
            'new_value' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    /**
     * Relasi actor jika actor adalah user
     */
    public function user()
    {
        return $this->belongsTo(
            User::class,
            'actor_id'
        );
    }


    /**
     * Scope untuk filter berdasarkan actor
     */
    public function scopeByActor($query, $actorId)
    {
        return $query->where('actor_id', $actorId);
    }


    /**
     * Scope berdasarkan action
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }


    /**
     * Scope berdasarkan entity
     */
    public function scopeEntity($query, $type, $id = null)
    {
        $query->where('entity_type', $type);

        if ($id) {
            $query->where('entity_id', $id);
        }

        return $query;
    }


    /**
     * Mencegah update audit log
     */
    protected static function booted()
    {
        static::updating(function () {
            throw new \Exception(
                'Audit log cannot be updated'
            );
        });


        static::deleting(function () {
            throw new \Exception(
                'Audit log cannot be deleted'
            );
        });
    }
}

