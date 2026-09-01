<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perluasan App\Models\JadwalReminderSetting (satu baris per company,
 * sudah ada sejak create_jadwal_reminder_settings_table.php) untuk
 * notifikasi WA balasan Approve/Reject App\Models\
 * JadwalKelasRescheduleRequest -- lihat App\Http\Controllers\Jadwal\
 * JadwalRescheduleRequestController::sendRescheduleNotifications().
 * Ditumpangkan ke tabel yang sama (bukan tabel baru) sesuai keputusan
 * diskusi: satu halaman "Pengaturan Pengingat" Jadwal untuk pengingat +
 * notifikasi reschedule sekaligus, bukan dua halaman terpisah.
 *
 * Tiga checkbox independen (BUKAN satu kolom enum seperti
 * `remind_target`) -- beda dari pengingat, di sini ketiganya bisa aktif
 * BERSAMAAN sekaligus (mis. Pengajar DAN Admin sama-sama mau tahu ada
 * jadwal yang berubah, terlepas dari requester-nya tetap dikabari juga
 * atau tidak):
 * - `reschedule_notify_requester` default TRUE -- sebelum kolom ini
 *   ada, requester (orang tua/murid yang minta reschedule lewat
 *   chatbot) SELALU dikabari tanpa syarat apa pun (lihat versi lama
 *   notifyRequester()). Default true di sini supaya company yang sudah
 *   berjalan tidak tiba-tiba berhenti dapat balasan WA cuma karena
 *   migration ini dijalankan -- perilaku lama tetap jalan sampai admin
 *   sengaja mematikannya.
 * - `reschedule_notify_pengajar`/`reschedule_notify_admin` default
 *   FALSE -- keduanya penerima BARU yang sebelumnya tidak pernah
 *   dikabari sama sekali, jadi opt-in (admin yang harus sengaja
 *   menyalakan), bukan otomatis aktif buat semua company.
 *
 * Dua kolom template terpisah (bukan satu, beda dari
 * `wa_message_template_id` milik pengingat) -- isi pesan Approve dan
 * Reject berbeda total (Reject wajib menyertakan alasan penolakan),
 * jadi butuh dua slot template independen, bukan satu template dengan
 * logic if/else di dalam teksnya (lihat hasil diskusi).
 */
return new class extends Migration
{
    /**
     * Nama constraint FK di-set manual pendek ("jrs_..._fk") -- nama
     * default yang di-generate Laravel dari table+kolom
     * ("jadwal_reminder_settings_wa_message_template_id_reschedule_
     * approved_foreign") lebih dari 64 karakter, kena SQLSTATE[42000]
     * error 1059 "Identifier name ... is too long" di MySQL (baru
     * ketahuan pas jalan di production, karena nama tabel+kolomnya
     * sama-sama panjang).
     *
     * up() juga dibuat idempoten (Schema::hasColumn() dulu sebelum
     * nambah tiap kolom) -- migration yang gagal di tengah jalan
     * (seperti kejadian di atas) sudah kadung nambah sebagian kolom
     * ke DB sebelum error (DDL MySQL tidak transactional, auto-commit
     * per statement), padahal baris migration itu sendiri TIDAK
     * tercatat selesai di tabel `migrations` -- jadi retry lewat
     * `php artisan migrate` bakal coba jalankan up() ini dari awal
     * lagi. Tanpa guard ini, retry itu akan gagal lagi dengan error
     * "Duplicate column name" untuk kolom yang sudah kadung ada.
     */
    public function up(): void
    {
        Schema::table('jadwal_reminder_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('jadwal_reminder_settings', 'reschedule_notify_pengajar')) {
                $table->boolean('reschedule_notify_pengajar')->default(false)->after('remind_target');
            }

            if (! Schema::hasColumn('jadwal_reminder_settings', 'reschedule_notify_requester')) {
                $table->boolean('reschedule_notify_requester')->default(true)->after('reschedule_notify_pengajar');
            }

            if (! Schema::hasColumn('jadwal_reminder_settings', 'reschedule_notify_admin')) {
                $table->boolean('reschedule_notify_admin')->default(false)->after('reschedule_notify_requester');
            }

            if (! Schema::hasColumn('jadwal_reminder_settings', 'wa_message_template_id_reschedule_approved')) {
                $table->foreignUuid('wa_message_template_id_reschedule_approved')
                    ->nullable()
                    ->after('reschedule_notify_admin')
                    ->constrained(table: 'wa_message_templates', indexName: 'jrs_wa_tpl_reschedule_approved_fk')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('jadwal_reminder_settings', 'wa_message_template_id_reschedule_rejected')) {
                $table->foreignUuid('wa_message_template_id_reschedule_rejected')
                    ->nullable()
                    ->after('wa_message_template_id_reschedule_approved')
                    ->constrained(table: 'wa_message_templates', indexName: 'jrs_wa_tpl_reschedule_rejected_fk')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_reminder_settings', function (Blueprint $table) {
            $table->dropForeign('jrs_wa_tpl_reschedule_rejected_fk');
            $table->dropColumn('wa_message_template_id_reschedule_rejected');

            $table->dropForeign('jrs_wa_tpl_reschedule_approved_fk');
            $table->dropColumn('wa_message_template_id_reschedule_approved');

            $table->dropColumn([
                'reschedule_notify_pengajar',
                'reschedule_notify_requester',
                'reschedule_notify_admin',
            ]);
        });
    }
};
