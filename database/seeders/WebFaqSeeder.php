<?php

namespace Database\Seeders;

use App\Models\WebFaq;
use Illuminate\Database\Seeder;

/**
 * 10 pertanyaan umum (general FAQ) untuk halaman publik — ditampilkan di
 * fe-konexa lewat GET /api/frontend/faqs (see
 * App\Http\Controllers\Api\Frontend\FaqController). Idempotent:
 * updateOrCreate on `name` so re-running this seeder edits existing rows
 * instead of duplicating them.
 */
class WebFaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'name' => 'Apa itu Konexa?',
                'descriptions' => 'Konexa adalah platform WhatsApp Business yang membantu bisnis mengelola chat, broadcast, dan CRM pelanggan dalam satu dashboard, sehingga tim bisa membalas lebih cepat dan pelanggan tetap terorganisir.',
            ],
            [
                'name' => 'Bagaimana cara mendaftar dan mulai menggunakan Konexa?',
                'descriptions' => 'Klik tombol Daftar di halaman utama, isi data akun Anda, lalu pilih paket yang sesuai kebutuhan. Setelah pembayaran dikonfirmasi, Anda bisa langsung menghubungkan nomor WhatsApp dan mulai menggunakan semua fitur.',
            ],
            [
                'name' => 'Apakah tersedia versi trial atau uji coba gratis?',
                'descriptions' => 'Tersedia. Anda bisa mencoba fitur-fitur utama secara gratis dalam periode terbatas sebelum memutuskan untuk berlangganan paket berbayar. Silakan cek halaman Paket Layanan untuk detail terbaru.',
            ],
            [
                'name' => 'Metode pembayaran apa saja yang didukung?',
                'descriptions' => 'Kami mendukung berbagai metode pembayaran seperti transfer bank, virtual account, e-wallet, dan kartu kredit/debit melalui payment gateway yang aman dan terpercaya.',
            ],
            [
                'name' => 'Bagaimana cara upgrade atau downgrade paket langganan?',
                'descriptions' => 'Anda bisa mengubah paket kapan saja melalui menu Paket Saya di dashboard. Perubahan akan disesuaikan dengan sisa masa aktif paket Anda saat ini.',
            ],
            [
                'name' => 'Apakah data pelanggan dan percakapan saya aman?',
                'descriptions' => 'Keamanan data adalah prioritas kami. Seluruh data disimpan dengan enkripsi dan akses dibatasi hanya untuk akun Anda, sehingga informasi bisnis dan pelanggan tetap terlindungi.',
            ],
            [
                'name' => 'Berapa banyak nomor WhatsApp atau perangkat yang bisa digunakan?',
                'descriptions' => 'Jumlah nomor WhatsApp yang bisa dihubungkan tergantung pada paket yang Anda pilih. Detail kuota masing-masing paket dapat dilihat di halaman Paket Layanan.',
            ],
            [
                'name' => 'Bagaimana jika saya mengalami kendala teknis?',
                'descriptions' => 'Tim support kami siap membantu melalui live chat, email, maupun WhatsApp resmi Konexa. Anda juga bisa mengecek halaman Artikel untuk panduan penggunaan.',
            ],
            [
                'name' => 'Apakah saya bisa membatalkan langganan kapan saja?',
                'descriptions' => 'Bisa. Anda dapat berhenti berlangganan kapan saja melalui dashboard tanpa dikenakan biaya tersembunyi. Paket yang sudah aktif akan tetap bisa digunakan hingga masa berlakunya habis.',
            ],
            [
                'name' => 'Apakah ada kebijakan pengembalian dana (refund)?',
                'descriptions' => 'Kebijakan refund mengikuti syarat dan ketentuan yang berlaku, tergantung jenis paket dan waktu pengajuan. Silakan hubungi tim support kami untuk bantuan lebih lanjut mengenai proses refund.',
            ],
        ];

        foreach ($faqs as $faq) {
            WebFaq::updateOrCreate(
                ['name' => $faq['name']],
                [
                    'descriptions' => $faq['descriptions'],
                    'status' => 'active',
                ]
            );
        }
    }
}
