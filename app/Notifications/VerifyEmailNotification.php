<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Custom email-activation link (App\Http\Controllers\Auth\AuthController::
 * verifyEmail()/resendVerification()) — deliberately NOT Laravel's stock
 * Illuminate\Auth\Notifications\VerifyEmail, since that one signs a URL
 * with no server-side record of it, so there's nothing to look up to
 * tell an EXPIRED link apart from a WRONG one, and nothing to reuse for
 * a "resend" that isn't just "generate a brand new signed URL and hope
 * the user throws away the old email". Here the token/expiry are columns
 * on `users`, so both cases are explicit, checkable, and this same
 * notification is reused for both the initial email and every resend.
 *
 * implements ShouldQueue so registration/resend requests return
 * immediately — the actual SMTP call happens on the queue worker, not
 * inline in the request (see `php artisan queue:work`).
 *
 * Naik di antrian bernama 'emails' (bukan antrian default) — supaya
 * verifikasi email/reset password/notifikasi lain di App\Notifications
 * tidak pernah ikut mengantre di belakang broadcast WA berjumlah besar
 * yang diproses worker yang sama (satu proses queue:work saja di
 * produksi). Produksi menjalankan
 * `queue:work --queue=emails,default` supaya antrian 'emails' selalu
 * dicek lebih dulu sebelum 'default' (job WA) — lihat konfigurasi
 * supervisor `konexa-queue-worker`.
 */
class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $token,
        protected int $expiresInMinutes,
    ) {
        $this->onQueue('emails');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('verification.verify', ['token' => $this->token]);

        return (new MailMessage)
            ->subject('Verifikasi Alamat Email Anda')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Terima kasih telah mendaftar. Silakan klik tombol di bawah untuk mengaktifkan akun Anda.')
            ->action('Verifikasi Email', $url)
            ->line("Link ini akan kedaluwarsa dalam {$this->expiresInMinutes} menit sejak email ini dikirim.")
            ->line('Jika link sudah kedaluwarsa, cukup klik link tersebut lagi — kami akan otomatis mengirimkan link baru.')
            ->line('Jika Anda tidak merasa mendaftar di aplikasi ini, abaikan email ini.');
    }
}
