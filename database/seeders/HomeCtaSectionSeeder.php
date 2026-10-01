<?php

namespace Database\Seeders;

use App\Models\WebHomeSection;
use App\Models\WebSetting;
use Illuminate\Database\Seeder;

/**
 * Section CTA (tipe "banner") di paling bawah beranda fe-konexa, tepat di
 * atas footer -- diatur di Superadmin > Web > Susunan Beranda.
 *
 * Kata di judul yang diapit *bintang* tampil disorot di fe-konexa
 * (frontend/partials/sections/cta.blade.php). Tombol 1 ke halaman daftar
 * aplikasi ini (route register, ikut APP_URL), tombol 2 ke WhatsApp dari
 * Web > Pengaturan (kalau nomor belum diisi: halaman Kontak fe-konexa).
 *
 * Aman dijalankan ulang: hanya dibuat kalau beranda belum punya section
 * banner, tidak menimpa yang sudah diedit.
 * Jalankan khusus: php artisan db:seed --class=HomeCtaSectionSeeder
 */
class HomeCtaSectionSeeder extends Seeder
{
    public function run(): void
    {
        if (WebHomeSection::ofPage(null)->where('type', 'banner')->exists()) {
            return;
        }

        $phone = preg_replace('/\D/', '', (string) WebSetting::current()->handphone);
        $phone = str_starts_with($phone, '0') ? '62'.substr($phone, 1) : $phone;

        WebHomeSection::create([
            'web_page_id' => null,
            'type' => 'banner',
            'title' => 'Saatnya urus usaha *lebih ringan*',
            'subtitle' => 'Registrasi, WhatsApp, absensi, jadwal, sampai pembayaran, cukup dari satu aplikasi. Mulai gratis hari ini, tanpa ribet.',
            'background_type' => 'none',
            'text_align' => 'center',
            'cta_text' => 'Coba Gratis Sekarang',
            'cta_link' => route('register'),
            'cta2_text' => 'Konsultasi via WhatsApp',
            'cta2_link' => $phone !== '' ? 'https://wa.me/'.$phone : '/kontak',
            'sort_order' => (int) WebHomeSection::ofPage(null)->max('sort_order') + 1,
            'status' => 'active',
        ]);
    }
}
