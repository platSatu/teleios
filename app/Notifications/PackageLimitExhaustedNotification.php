<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * "Kuota Anda sudah habis" — dikirim tepat sekali per kehabisan kuota
 * (lihat App\Services\PackageLimitService::notifyExhausted(), yang
 * menahan pengiriman ulang lewat kolom company_limit_usages.notified_at
 * sampai periode/subscription-nya berganti), bukan setiap kali ada aksi
 * yang diblokir — supaya owner company tidak dibanjiri email tiap
 * percobaan broadcast yang gagal karena kuota habis.
 */
class PackageLimitExhaustedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        protected string $metricName,
        protected ?string $unit,
        protected int $maxValue,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $unitLabel = $this->unit ? " {$this->unit}" : '';

        return (new MailMessage)
            ->subject("Kuota {$this->metricName} Anda Sudah Habis")
            ->greeting('Halo '.$notifiable->name.',')
            ->line("Kuota {$this->metricName} pada paket Anda sudah habis terpakai ({$this->maxValue}{$unitLabel}).")
            ->line('Selama kuota ini belum terisi ulang (lewat pembelian/upgrade paket baru), aksi terkait fitur ini akan ditolak sistem.')
            ->action('Lihat Paket', route('dashboard.package.index'))
            ->line('Jika Anda merasa ini keliru, silakan hubungi tim support kami.');
    }
}
