<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WebContactMessage;
use App\Models\WebSetting;
use App\Notifications\ContactMessageReceivedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Terima pesan form Kontak dari fe-konexa (server-to-server, X-API-KEY).
 * Lapis keamanan di sisi ini (fe-konexa punya honeypot, jeda waktu isi
 * form, CSRF, dan throttle sendiri):
 *   - validasi ketat + topik whitelist,
 *   - tolak pesan berisi banyak link (pola spam),
 *   - batas 5 pesan / 10 menit per IP pengunjung,
 *   - pesan kembar (email + isi sama dalam 10 menit) tidak dikirim ulang.
 * Disimpan dulu, lalu email ke Pengaturan Web lewat antrean 'emails'.
 */
class ContactMessageController extends Controller
{
    private const MAX_PER_IP = 5;

    private const WINDOW_SECONDS = 600;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9][0-9\s\-]{7,19}$/'],
            'topic' => ['required', Rule::in(WebContactMessage::TOPICS)],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'ip_address' => ['nullable', 'ip'],
            'user_agent' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->looksLikeSpam($data)) {
            return response()->json(['message' => 'Pesan tidak dapat dikirim. Kurangi jumlah link di pesan Anda.'], 422);
        }

        $limiterKey = 'contact-message:'.($data['ip_address'] ?? $request->ip());

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_PER_IP)) {
            return response()->json(['message' => 'Terlalu banyak pesan. Silakan coba lagi beberapa menit lagi.'], 429);
        }

        RateLimiter::hit($limiterKey, self::WINDOW_SECONDS);

        $duplicate = WebContactMessage::where('email', $data['email'])
            ->where('message', $data['message'])
            ->where('created_at', '>=', now()->subSeconds(self::WINDOW_SECONDS))
            ->exists();

        if ($duplicate) {
            return response()->json(['data' => ['status' => 'received']], 201);
        }

        $contactMessage = WebContactMessage::create($data + ['status' => 'new']);

        $this->notify($contactMessage);

        return response()->json(['data' => ['status' => 'received']], 201);
    }

    /**
     * Link lebih dari 2, atau link di kolom nama = pola spam umum.
     */
    private function looksLikeSpam(array $data): bool
    {
        $links = preg_match_all('#(https?://|www\.)#i', $data['message']);

        return $links > 2 || preg_match('#(https?://|www\.)#i', $data['name']) === 1;
    }

    private function notify(WebContactMessage $contactMessage): void
    {
        $recipient = WebSetting::current()->email;

        if (! $recipient) {
            Log::warning('Pesan kontak masuk tapi email di Pengaturan Web kosong; hanya tersimpan di Pesan Masuk.', ['id' => $contactMessage->id]);

            return;
        }

        try {
            Notification::route('mail', $recipient)->notify(new ContactMessageReceivedNotification($contactMessage));
            $contactMessage->forceFill(['notified_at' => now()])->save();
        } catch (Throwable $e) {
            Log::error('Gagal mengantrekan email pesan kontak.', ['id' => $contactMessage->id, 'message' => $e->getMessage()]);
        }
    }
}
