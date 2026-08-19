<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * "Pembayaran Anda sudah kedaluwarsa" — sent once per deposit, right
 * after App\Console\Commands\ProcessDepositExpiry flips a PENDING
 * deposit whose `expires_at` has passed to EXPIRED. This deposit can
 * never be resumed back to Duitku past this point (see
 * DepositController::checkout()'s status guard) — the only path
 * forward offered here, per the requirement, is starting a brand new
 * deposit from scratch.
 */
class DepositExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(protected Deposit $deposit)
    {
        // Terpisah dari antrian pengiriman WA (broadcast/jadwal/auto-reply/
        // AI bot) — lihat docblock VerifyEmailNotification untuk alasan
        // lengkapnya: email tidak boleh ikut mengantre di belakang broadcast
        // besar. Worker produksi didengarkan lewat
        // `queue:work --queue=emails,default` supaya antrian ini diprioritaskan.
        $this->onQueue('emails');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = 'Rp ' . number_format((float) $this->deposit->amount, 0, ',', '.');
        $topupUrl = route('deposit.topup');

        return (new MailMessage)
            ->subject('Pembayaran Deposit Anda Sudah Kedaluwarsa')
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line("Waktu pembayaran untuk deposit {$amount} (ref: {$this->deposit->reference_number}) sudah habis, dan pembayaran ini tidak dapat dilanjutkan lagi.")
            ->line('Belum ada saldo yang terpotong dari akun Anda. Jika masih ingin melakukan top up, silakan buat deposit baru dari awal.')
            ->action('Buat Deposit Baru', $topupUrl)
            ->line('Jika Anda merasa sudah membayar sebelumnya, segera hubungi tim support kami dan sertakan nomor referensi di atas.')
            ->salutation('Terima kasih, Tim Konexa');
    }
}
