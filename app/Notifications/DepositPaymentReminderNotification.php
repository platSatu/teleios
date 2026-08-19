<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * "Segera selesaikan pembayaran Anda" — a single nudge sent once per
 * deposit while its Duitku invoice is still PENDING and getting close
 * to `expires_at`, from App\Console\Commands\ProcessDepositExpiry.
 * Idempotent via Deposit::reminder_sent_at, stamped in the same
 * lockForUpdate() transaction that queues this notification — never
 * re-sent for the same deposit even if the command runs again before
 * the invoice actually expires.
 */
class DepositPaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(protected Deposit $deposit)
    {
        // Lihat docblock VerifyEmailNotification — antrian 'emails' terpisah
        // dari antrian pengiriman WA supaya tidak ikut tertunda di belakang
        // broadcast besar.
        $this->onQueue('emails');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = 'Rp ' . number_format((float) $this->deposit->amount, 0, ',', '.');
        $expiresAt = $this->deposit->expires_at?->format('d M Y H:i') . ' WIB';
        $checkoutUrl = route('deposit.checkout', $this->deposit);

        return (new MailMessage)
            ->subject('Segera Selesaikan Pembayaran Anda')
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line("Deposit Anda sebesar {$amount} (ref: {$this->deposit->reference_number}) masih menunggu pembayaran.")
            ->line("Batas waktu pembayaran: {$expiresAt}. Setelah waktu ini berakhir, pembayaran tidak dapat dilanjutkan lagi.")
            ->line('Selesaikan pembayaran sekarang agar saldo wallet Anda dapat segera diperbarui.')
            ->action('Selesaikan Pembayaran', $checkoutUrl)
            ->line('Jika Anda sudah membayar, silakan abaikan email ini — saldo akan otomatis diperbarui begitu pembayaran kami terima.')
            ->salutation('Terima kasih, Tim Konexa');
    }
}
