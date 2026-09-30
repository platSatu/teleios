<?php

namespace App\Notifications;

use App\Models\BankAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Email ke pemilik akun setiap ada perubahan rekening pencairan
 * (didaftarkan, disetujui, ditolak) -- pengaman paling murah kalau akun
 * dipakai orang lain. Dikirim dari App\Services\Wallet\BankAccountService.
 * Nomor rekening selalu tersamar.
 */
class BankAccountNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        protected BankAccount $account,
        protected string $event, // registered | approved | rejected
    ) {
        $this->onQueue('emails');
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');
        $account = $this->account->masked().' a.n. '.$this->account->account_name;
        $mail = (new MailMessage)->greeting('Halo '.$notifiable->name.',');

        return match ($this->event) {
            'approved' => $mail
                ->subject('Rekening pencairan Anda sudah disetujui')
                ->line("Rekening **{$account}** sudah kami setujui dan bisa dipakai tarik saldo mulai sekarang."),
            'rejected' => $mail
                ->subject('Rekening pencairan Anda tidak disetujui')
                ->line("Rekening **{$account}** tidak bisa kami setujui.")
                ->line('Alasan: '.$this->account->review_note)
                ->action('Daftarkan Rekening Lain', route('wallet.bank-account.index')),
            default => $mail
                ->subject('Rekening pencairan Anda baru saja diganti')
                ->line("Rekening pencairan di akun {$app} Anda baru saja didaftarkan/diganti menjadi:")
                ->line("**{$account}**")
                ->line('Waktu: '.$this->account->created_at->translatedFormat('d M Y, H:i').' WIB · IP '.$this->account->ip_address)
                ->line($this->account->name_matched
                    ? '**Ini Anda?** Tidak perlu melakukan apa-apa. Rekening aktif mulai '.$this->account->active_at->translatedFormat('d M Y, H:i').' WIB.'
                    : '**Ini Anda?** Tim kami akan memeriksa rekening ini terlebih dahulu.')
                ->line('**Bukan Anda?** Segera hubungi tim kami dan ganti password Anda. Selama masa tunggu, saldo Anda tidak bisa dikirim ke rekening tersebut.'),
        };
    }
}
