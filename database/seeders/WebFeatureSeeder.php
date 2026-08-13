<?php

namespace Database\Seeders;

use App\Models\WebFeature;
use Illuminate\Database\Seeder;

/**
 * 8 fitur unggulan Konexa untuk halaman publik (fe-konexa, section
 * "Layanan"/features) — ditampilkan lewat GET /api/frontend/features
 * (lihat App\Http\Controllers\Api\Frontend\FeatureController). Dipilih
 * dari modul-modul paling "canggih"/pembeda di aplikasi ini: chatbot AI
 * (WaChatbotFlow), broadcast anti-banned (WaMessageSchedule + throttle
 * settings), CRM/sales pipeline (WaDeal), moderasi AI (AiModerationSetting),
 * multi-device/multi-cabang (WaApiKey, BranchOffice), CSAT (WaCsatSurvey),
 * segmentasi & otomasi pelanggan (WaCustomerSegment, WaCustomerAutomationRule),
 * dan buku telepon terpusat (WaPhoneBook).
 *
 * Idempotent: updateOrCreate on `name`, sama seperti WebFaqSeeder — aman
 * dijalankan ulang, tidak menduplikasi baris. `images` SENGAJA dibiarkan
 * null di sini — tinggal upload manual lewat Superadmin > Web > (menu
 * Fitur, kalau sudah ada CRUD-nya) atau langsung lewat database, sesuai
 * permintaan "tinggal saya edit upload image-nya saja".
 */
class WebFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            [
                'name' => 'Chatbot AI Otomatis 24/7',
                'description' => 'Bangun alur percakapan otomatis tanpa coding lewat Flow Builder — chatbot AI merespons pelanggan kapan saja, bahkan di luar jam kerja, tanpa kehilangan calon pelanggan.',
            ],
            [
                'name' => 'Broadcast Anti-Banned',
                'description' => 'Kirim pesan massal ke ribuan pelanggan dengan teknologi throttling pintar yang menjaga nomor WhatsApp Anda tetap aman dari pemblokiran, lengkap dengan jadwal pengiriman otomatis.',
            ],
            [
                'name' => 'CRM & Sales Pipeline Terintegrasi',
                'description' => 'Kelola prospek dan deal penjualan langsung dari jendela chat — pantau tahapan closing, tugas tim, hingga nilai transaksi tanpa berpindah aplikasi.',
            ],
            [
                'name' => 'Moderasi Template dengan AI',
                'description' => 'Setiap template pesan diperiksa otomatis oleh AI sebelum digunakan, meminimalkan risiko penolakan dari WhatsApp dan menjaga kualitas komunikasi bisnis Anda.',
            ],
            [
                'name' => 'Multi-Device & Multi-Cabang',
                'description' => 'Hubungkan banyak nomor WhatsApp sekaligus dan kelola seluruh cabang bisnis Anda dalam satu dashboard terpusat, dengan hak akses yang bisa diatur per peran.',
            ],
            [
                'name' => 'Survei Kepuasan Pelanggan (CSAT) Otomatis',
                'description' => 'Kirim survei kepuasan otomatis setelah percakapan selesai, dan pantau skor kepuasan pelanggan secara real-time untuk terus meningkatkan layanan.',
            ],
            [
                'name' => 'Segmentasi & Otomasi Pelanggan',
                'description' => 'Kelompokkan pelanggan berdasarkan tag dan perilaku, lalu jalankan aturan otomatis yang mengirim follow-up tepat sasaran tanpa kerja manual berulang.',
            ],
            [
                'name' => 'Buku Telepon Terpusat',
                'description' => 'Kelola seluruh kontak pelanggan dalam satu buku telepon terorganisir per kategori, siap dipakai langsung untuk broadcast maupun pesan terjadwal.',
            ],
        ];

        foreach ($features as $feature) {
            WebFeature::updateOrCreate(
                ['name' => $feature['name']],
                [
                    'description' => $feature['description'],
                    'status' => 'active',
                ]
            );
        }
    }
}
