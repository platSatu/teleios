<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 23 September 2026 redesign, poin 2 & 4:
 *
 * - `tagihan_pelanggan.kirim_link_otomatis` -- ceklist "kirim link ke
 *   pelanggan" di form App\Http\Controllers\Tagihan\TagihanPelangganController.
 *   Kalau aktif, setiap kali Tagihan baru dibuat di category yang
 *   pelanggan ini berlangganan, link bayar otomatis dikirim lewat
 *   WhatsApp dari device yang sedang terhubung di branch pelanggan itu
 *   (lihat App\Jobs\SendTagihanLinkWaMessage &
 *   App\Http\Controllers\Tagihan\TagihanController::store()). Default
 *   false -- fitur ini opt-in per pelanggan, bukan otomatis nyala buat
 *   semua orang.
 *
 * - `tagihan.wa_template_message` -- template pesan WA yang dipakai job
 *   di atas, diisi admin di form "Buat Tagihan" (bukan di category,
 *   sesuai poin 4 permintaan user), boleh kosong (fallback ke pesan
 *   default di App\Models\Tagihan::renderWaTemplate()). Placeholder yang
 *   didukung: {nama}, {nominal}, {link}, {jatuh_tempo} -- lihat
 *   App\Models\Tagihan::renderWaTemplate() untuk daftar lengkap &
 *   docblock nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_pelanggan', function (Blueprint $table) {
            $table->boolean('kirim_link_otomatis')->default(false)->after('status');
        });

        Schema::table('tagihan', function (Blueprint $table) {
            $table->text('wa_template_message')->nullable()->after('pakai_denda');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_pelanggan', function (Blueprint $table) {
            $table->dropColumn('kirim_link_otomatis');
        });

        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropColumn('wa_template_message');
        });
    }
};
