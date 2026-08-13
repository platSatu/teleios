<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Melengkapi App\Models\WebHeader:
     *
     * - thumbnail_background_images: thumbnail/preview KHUSUS untuk
     *   slide bertipe Gambar (background_type = image) — simetris
     *   dengan thumbnail_images yang sudah ada untuk slide bertipe Video
     *   (dipakai sebagai poster). Dipakai fe-konexa sebagai placeholder
     *   blur-up ringan yang tampil duluan sebelum background_images
     *   (resolusi penuh) selesai dimuat — "turunan" gambar, sama seperti
     *   thumbnail_images adalah "turunan" video. Nullable karena cuma
     *   relevan kalau background_type = image, dan tetap opsional di
     *   situ juga (bukan wajib).
     *
     * - color_headline, color_description: warna teks (hex, mis.
     *   "#ffffff") untuk headline & deskripsi PER SLIDE, supaya
     *   superadmin bisa menyesuaikan kontras teks terhadap warna
     *   background gambar/video yang dipilih untuk slide itu. Nullable
     *   — kosong berarti frontend (fe-konexa) pakai warna default
     *   temanya sendiri.
     *
     * videos & background_images sudah nullable sejak migration
     * pembuatan tabel (create_web_headers_table) — sengaja begitu
     * karena mutual exclusivity background_type (video XOR gambar)
     * berarti salah satu dari keduanya memang legitimately kosong,
     * jadi tidak ada perubahan nullability yang diperlukan di sana.
     */
    public function up(): void
    {
        Schema::table('web_headers', function (Blueprint $table) {
            $table->string('thumbnail_background_images')->nullable()->after('background_images');
            $table->string('color_headline', 20)->nullable()->after('descriptions');
            $table->string('color_description', 20)->nullable()->after('color_headline');
        });
    }

    public function down(): void
    {
        Schema::table('web_headers', function (Blueprint $table) {
            $table->dropColumn(['thumbnail_background_images', 'color_headline', 'color_description']);
        });
    }
};
