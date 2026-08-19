<?php

namespace App\Notifications;

use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * "Masa aktif package Anda akan segera berakhir" reminder, dikirim oleh
 * App\Console\Commands\SendPackageExpiryReminders (jadwal harian — lihat
 * bootstrap/app.php withSchedule()) di 4 titik sebelum
 * $voucher->valid_until: H-7, H-3, H-1, dan H (hari itu juga). Command
 * itu sendiri yang menjaga supaya tiap titik hanya terkirim SEKALI per
 * voucher (kolom reminder_{7,3,1,0}d_sent_at di tabel vouchers) dan
 * berhenti sama sekali begitu voucher ini sudah "digantikan" oleh voucher
 * lain yang di-redeem lebih baru untuk package yang sama (perpanjangan) —
 * lihat docblock command tersebut untuk detail pengecekannya. Notifikasi
 * ini sendiri tidak perlu tahu soal itu, dia hanya bertugas mengirim satu
 * email untuk satu (voucher, daysLeft) yang sudah diputuskan valid oleh
 * command.
 *
 * implements ShouldQueue supaya command yang memproses ratusan/ribuan
 * voucher tiap hari tidak nge-block pada panggilan SMTP satu-satu —
 * setiap notify() hanya melempar satu job ke antrian 'emails' (lihat
 * onQueue() di constructor), terpisah dari antrian pengiriman WA supaya
 * ratusan reminder ini tidak ikut menunda broadcast yang sedang jalan,
 * dan sebaliknya.
 */
class PackageExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  int  $daysLeft  0, 1, 3, atau 7 — sisa hari sampai
     *                         $voucher->valid_until, dipakai untuk
     *                         menyesuaikan urgensi subject/isi email.
     */
    public function __construct(
        protected Voucher $voucher,
        protected int $daysLeft,
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
        $packageName = $this->voucher->package?->name ?? 'Anda';
        $validUntil = $this->voucher->valid_until?->format('d M Y H:i') . ' WIB';

        $urgency = match (true) {
            $this->daysLeft <= 0 => 'HARI INI',
            $this->daysLeft === 1 => 'BESOK',
            default => "dalam {$this->daysLeft} hari",
        };

        $subject = $this->daysLeft <= 0
            ? "Package {$packageName} Anda Berakhir Hari Ini"
            : "Package {$packageName} Anda Akan Berakhir {$urgency}";

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line("Masa aktif package \"{$packageName}\" Anda akan berakhir {$urgency}, tepatnya pada {$validUntil}.")
            ->line('Agar layanan Anda tidak terganggu, silakan perpanjang package Anda sebelum masa aktifnya habis.')
            ->action('Perpanjang Package', route('dashboard.package.index'))
            ->line('Abaikan email ini jika Anda sudah melakukan perpanjangan.');
    }
}
