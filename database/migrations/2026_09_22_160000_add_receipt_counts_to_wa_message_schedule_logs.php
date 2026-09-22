<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Kenapa read ke Grup cuma kebaca 1" (diskusi 22 September 2026): g_backend
 * dulu menyimpan status delivered/read SATU PESAN sebagai satu nilai saja
 * (App\Models\WaMessageScheduleLog::$status), padahal WhatsApp mengirim
 * tanda-terima TERPISAH untuk setiap anggota grup yang membaca -- begitu
 * SATU anggota grup mana pun membaca, status langsung "naik" ke 'read' dan
 * berhenti di situ, jadi angka read untuk grup tidak pernah bisa lebih dari
 * 1 walau anggotanya banyak yang sudah baca.
 *
 * g_backend sekarang melacak tiap anggota grup secara terpisah (tabel baru
 * wa_message_receipts di sisi Go) dan mengirim HITUNGAN aktual lewat
 * webhook (lihat App\Http\Controllers\Api\WaMessageStatusWebhookController)
 * -- kolom `status` yang lama TETAP dipertahankan (masih dipakai badge
 * status baris ini di halaman History), tapi `delivered_count`/`read_count`
 * sekarang jadi sumber angka yang ditampilkan, dan `recipient_total` (kalau
 * diketahui -- ukuran grup dari g_backend) jadi penyebut "x dari y dibaca".
 *
 * Untuk recipient 'phone'/'user' (bukan 'group'), ini tetap berperilaku
 * sama seperti sebelumnya -- delivered_count/read_count cuma bisa 0 atau 1,
 * recipient_total selalu 1, tidak ada regresi buat kasus non-grup.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wa_message_schedule_logs', 'delivered_count')) {
            Schema::table('wa_message_schedule_logs', function ($table) {
                $table->unsignedInteger('delivered_count')->default(0)->after('status');
                $table->unsignedInteger('read_count')->default(0)->after('delivered_count');
                // Nullable -- null berarti "belum diketahui ukuran
                // penerimanya" (grup yang ukurannya belum sempat di-fetch
                // g_backend), BUKAN "0 penerima". Selalu 1 untuk recipient
                // 'phone'/'user'.
                $table->unsignedInteger('recipient_total')->nullable()->after('read_count');
            });
        }

        // Backfill baris LAMA (dikirim sebelum kolom ini ada) supaya
        // agregasi baru (SUM(delivered_count)/SUM(read_count) di
        // App\Http\Controllers\Chat\MessageScheduleController::index())
        // tetap menampilkan angka yang benar untuk history yang sudah
        // ada -- sebelum perubahan ini, delivered/read paling besar
        // memang cuma bisa 0 atau 1 per baris (baik grup maupun
        // perorangan), jadi backfill 1 di sini akurat, bukan tebakan.
        DB::table('wa_message_schedule_logs')
            ->whereIn('status', ['delivered', 'read'])
            ->where('delivered_count', 0)
            ->update(['delivered_count' => 1]);

        DB::table('wa_message_schedule_logs')
            ->where('status', 'read')
            ->where('read_count', 0)
            ->update(['read_count' => 1]);
    }

    public function down(): void
    {
        Schema::table('wa_message_schedule_logs', function ($table) {
            if (Schema::hasColumn('wa_message_schedule_logs', 'delivered_count')) {
                $table->dropColumn(['delivered_count', 'read_count', 'recipient_total']);
            }
        });
    }
};
