<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu pesan dari form Kontak website (fe-konexa). Lihat
 * App\Http\Controllers\Api\Frontend\ContactMessageController.
 */
class WebContactMessage extends Model
{
    use HasUuids;

    protected $table = 'web_contact_messages';

    /** Pilihan topik yang sah (sama dengan dropdown di form Kontak fe-konexa). */
    public const TOPICS = ['Chatbot AI', 'Broadcast WhatsApp', 'CRM & Sales Pipeline', 'Paket & Harga', 'Tagihan Online', 'Lainnya'];

    public const STATUSES = ['new' => 'Baru', 'replied' => 'Sudah dibalas', 'archived' => 'Arsip'];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'topic',
        'message',
        'ip_address',
        'user_agent',
        'status',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Nomor dalam format wa.me (62...).
     */
    public function whatsappNumber(): string
    {
        $digits = preg_replace('/\D+/', '', $this->phone);

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}
