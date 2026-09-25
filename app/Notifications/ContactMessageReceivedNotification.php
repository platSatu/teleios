<?php

namespace App\Notifications;

use App\Models\WebContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Email notifikasi pesan baru dari form Kontak ke email Pengaturan Web.
 * Reply-To = email pengirim, jadi "Balas" di inbox langsung ke pengunjung.
 * Isi pesan ditampilkan sebagai teks (tanda [ ] dinetralkan supaya tidak
 * jadi link markdown).
 */
class ContactMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(protected WebContactMessage $contactMessage)
    {
        $this->onQueue('emails');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->contactMessage;
        $plain = fn (string $text) => str_replace(['[', ']'], ['(', ')'], $text);

        $mail = (new MailMessage)
            ->subject('Pesan baru dari website: '.$message->topic.' – '.$message->name)
            ->replyTo($message->email, $message->name)
            ->greeting('Pesan baru dari form Kontak')
            ->line('Nama: '.$plain($message->name))
            ->line('Email: '.$message->email)
            ->line('No. HP: '.$plain($message->phone))
            ->line('Topik: '.$message->topic)
            ->line('Pesan:');

        foreach (preg_split('/\R/', $message->message) as $line) {
            if (trim($line) !== '') {
                $mail->line($plain($line));
            }
        }

        return $mail
            ->action('Lihat di Pesan Masuk', route('web.contact-messages.show', $message->id))
            ->line('Klik "Balas" di email ini untuk membalas langsung ke pengirim.');
    }
}
