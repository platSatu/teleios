<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris per kunjungan ke halaman publik fe-konexa. Ditulis oleh
 * App\Http\Controllers\Api\Frontend\VisitorLogController — lihat
 * migration-nya untuk kenapa ini sengaja terpisah dari HistoryUserLogin
 * dan tidak punya FK ke `users` sama sekali. Read-only dari sisi
 * superadmin (Superadmin\FrontendVisitorLogController) — tidak ada
 * create/update/delete manual, semuanya lewat endpoint API di atas.
 */
class FrontendVisitorLog extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'frontend_visitor_logs';

    protected $fillable = [
        'visitor_id',
        'ip_address',
        'user_agent',
        'browser',
        'browser_version',
        'os',
        'device_type',
        'path',
        'referrer',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];
}
