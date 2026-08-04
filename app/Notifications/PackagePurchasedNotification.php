<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * "Terima kasih sudah memilih layanan kami" — dikirim tepat sekali per
 * pembelian package, dari Dashboard\PackageCheckoutController::store()
 * setelah (dan hanya setelah) transaksi DB-nya benar-benar commit
 * (Subscription + PaymentTransaction + Voucher aktivasi semuanya sudah
 * tersimpan). Ini email konfirmasi PEMBELIAN, bukan email aktivasi —
 * voucher yang di-generate di sini masih 'pending' sampai user
 * redeem sendiri lewat Dashboard\VoucherRedeemController, jadi isi email
 * ini murni ucapan terima kasih + ringkasan transaksi, tidak menyebut
 * masa aktif (itu belum ditentukan sampai redeem).
 *
 * implements ShouldQueue dengan alasan yang sama seperti
 * DepositReceivedNotification: supaya redirect ke halaman invoice setelah
 * checkout tetap responsif, panggilan SMTP-nya terjadi di queue worker
 * (`php artisan queue:work`), bukan inline di request checkout.
 */
class PackagePurchasedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        protected Subscription $subscription,
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
        $packageName = $this->subscription->metadata['package_name']
            ?? $this->subscription->package?->name
            ?? 'package';

        $amount = 'Rp ' . number_format((float) $this->subscription->amount, 0, ',', '.');
        $purchasedAt = $this->subscription->created_at?->format('d M Y H:i') . ' WIB';
        $kodeVoucher = $this->subscription->voucher?->kode_voucher;

        $mail = (new MailMessage)
            ->subject('Terima Kasih Atas Pembelian Package Anda')
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Terima kasih sudah memilih layanan kami! Pembelian package Anda telah berhasil diproses.')
            ->line("Package: {$packageName}")
            ->line("Total pembayaran: {$amount}")
            ->line("Waktu pembelian: {$purchasedAt}");

        if ($kodeVoucher) {
            $mail->line("Kode aktivasi: {$kodeVoucher}")
                ->line('Silakan redeem kode aktivasi ini di halaman "Redeem Voucher" agar package Anda mulai aktif.')
                ->action('Redeem Voucher', route('dashboard.voucher-redeem.index'));
        }

        return $mail->line('Jika Anda tidak melakukan pembelian ini, segera hubungi tim support kami.');
    }
}
