<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * "Terima kasih, deposit Anda sudah diterima" — dikirim tepat sekali per
 * deposit, dari App\Http\Controllers\User\Deposit\DuitkuCallbackController
 * ::handle() setelah (dan hanya setelah) Deposit benar-benar berpindah
 * dari PENDING ke SUCCESS dan wallet-nya sudah dikredit di dalam
 * transaksi DB yang sama. Tidak dipicu dari path lain — callback
 * duplikat/telat dari Duitku tidak akan pernah mengirim notifikasi ini
 * dua kali untuk deposit yang sama, karena controller itu sendiri sudah
 * menjaga idempotensi lewat lockForUpdate() + status-guard sebelum
 * notifikasi ini dikirim sama sekali.
 *
 * implements ShouldQueue supaya respons webhook ke Duitku ("OK") tetap
 * cepat — panggilan SMTP yang sebenarnya terjadi di queue worker
 * (`php artisan queue:work`), bukan inline di request webhook. Naik di
 * antrian bernama 'emails' (lihat onQueue() di constructor) — terpisah
 * dari antrian pengiriman WA, supaya email ini tidak ikut tertunda di
 * belakang broadcast besar yang sedang diproses worker yang sama.
 *
 * SerializesModels supaya $deposit disimpan sebagai referensi
 * (class + id) di payload job, bukan di-serialize utuh — begitu job
 * benar-benar dieksekusi di worker, model di-fetch ulang dari DB (data
 * terbaru, bukan snapshot basi saat notify() dipanggil).
 */
class DepositReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        protected Deposit $deposit,
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
        $amount = 'Rp ' . number_format((float) $this->deposit->amount, 0, ',', '.');
        $paidAt = $this->deposit->paid_at?->format('d M Y H:i') . ' WIB';

        return (new MailMessage)
            ->subject('Deposit Anda Sudah Diterima')
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Terima kasih! Deposit Anda sudah kami terima dan saldo wallet Anda sudah diperbarui.')
            ->line("Nomor referensi: {$this->deposit->reference_number}")
            ->line("Jumlah: {$amount}")
            ->line("Waktu: {$paidAt}")
            ->line('Jika Anda tidak melakukan deposit ini, segera hubungi tim support kami.');
    }
}
