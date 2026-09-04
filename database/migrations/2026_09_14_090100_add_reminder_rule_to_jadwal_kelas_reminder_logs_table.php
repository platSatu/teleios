<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Update 7 September 2026 (lihat docblock lengkap migration pasangannya,
 * create_jadwal_reminder_rules_table.php) -- jadwal_kelas_reminder_logs
 * SEBELUMNYA unique per `jadwal_kelas_id` SAJA (satu Jadwal Kelas cuma
 * boleh punya SATU baris log/pengingat SEUMUR HIDUP, lihat docblock
 * migration aslinya create_jadwal_kelas_reminder_logs_table.php).
 * Sekarang satu company bisa punya BEBERAPA App\Models\JadwalReminderRule
 * (mis. "1 hari sebelumnya" DAN "6 jam sebelumnya" SEKALIGUS) -- tiap
 * rule harus bisa mengklaim/mengirim pengingatnya SENDIRI-SENDIRI untuk
 * Jadwal Kelas yang sama, jadi kuncinya berubah jadi KOMPOSIT
 * (`jadwal_kelas_id`, `jadwal_reminder_rule_id`).
 *
 * `jadwal_reminder_rule_id` NULLABLE + `nullOnDelete()` (BUKAN
 * `cascadeOnDelete()`) -- SENGAJA supaya baris log historis (termasuk
 * yang dari SEBELUM migration ini, di-backfill NULL di bawah kalau
 * company itu cuma pernah punya satu rule) tidak ikut hilang kalau
 * admin nanti hapus/ganti rule-nya lewat form pengaturan (lihat
 * JadwalReminderSettingController::syncReminderRules() -- baris rule
 * yang tidak dipertahankan admin di-DELETE, bukan di-nonaktifkan).
 * Konsekuensinya: kalau admin ganti-ganti rule SETELAH sebuah Jadwal
 * Kelas sudah pernah dikirimi pengingat untuk rule LAMA yang sekarang
 * dihapus, baris log lama itu jadi rule_id NULL (riwayat tetap ada)
 * TAPI tidak lagi mencegah pengingat baru terkirim dobel untuk rule
 * BARU dengan timing yang kebetulan sama -- edge case yang dianggap
 * dapat diterima (ganti pengaturan pengingat memang wajar me-reset
 * penjadwalan ke depan), didokumentasikan di sini supaya tidak
 * mengejutkan kalau ditemukan nanti.
 *
 * Backfill di bawah: baris log LAMA (dibuat sebelum migration ini)
 * di-set rule_id-nya ke SATU-SATUNYA rule milik company itu KALAU
 * company itu cuma pernah punya SATU rule (hasil backfill migration
 * sebelumnya) -- supaya Jadwal Kelas yang SUDAH pernah dikirimi
 * pengingat untuk rule itu TIDAK langsung dikirimi ULANG begitu
 * command jadwal:dispatch-due-reminders jalan lagi pasca-migration ini
 * (tanpa backfill, log lama rule_id-nya NULL, tidak match rule BARU
 * manapun, jadi dianggap "belum pernah dikirim untuk rule ini").
 *
 * Bug fix 4 September 2026 (laporan user lewat error pas `php artisan
 * migrate`: "SQLSTATE[HY000]: General error: 1553 Cannot drop index
 * 'jadwal_kelas_reminder_logs_jadwal_kelas_id_unique': needed in a
 * foreign key constraint").** DUA bug, keduanya diperbaiki di bawah:
 * (1) **Urutan salah** -- versi SEBELUMNYA drop unique `jadwal_kelas_id`
 * DULU baru pasang unique komposit. MySQL/InnoDB menolak drop index itu
 * karena itu satu-satunya index yang menopang FK `jadwal_kelas_id` ke
 * `jadwal_kelas` -- begitu index-nya hilang, FK jadi tidak punya index
 * pendukung sama sekali (MySQL WAJIB ada index apa pun yang kolom
 * FK-nya jadi PREFIX-nya). Fix: pasang unique komposit BARU dulu
 * (kolom pertamanya `jadwal_kelas_id`, jadi otomatis bisa menopang FK
 * yang sama begitu index lama didrop), BARU drop unique lama --
 * MySQL tidak pernah membiarkan sebuah FK kehilangan index pendukung
 * sama sekali walau cuma sesaat.
 * (2) **`try`/`catch` di sini TIDAK PERNAH benar-benar menangkap apa
 * pun** -- `$table->dropUnique()`/`$table->unique()` di dalam closure
 * `Schema::table()` cuma MENGANTRE command ke Blueprint, belum
 * benar-benar menjalankan SQL apa pun ke DB. Eksekusi SQL sungguhan
 * (dan exception yang menyertainya kalau gagal) baru terjadi Laravel
 * SETELAH closure ini selesai -- jadi `try`/`catch` yang dibungkus DI
 * DALAM closure seperti versi sebelumnya tidak pernah bisa menangkap
 * error itu (persis kejadian di atas: errornya lolos sampai ke
 * terminal user, tidak pernah ketangkap). Fix: ganti total ke pola
 * `Schema::hasIndex($table, $columnsOrName, $type)` (tersedia di
 * Laravel 12, sudah diverifikasi ada di `vendor/laravel/framework`
 * project ini) -- SAMA PERSIS prinsipnya dengan `Schema::hasColumn()`
 * yang sudah dipakai di migration lain project ini: query DULU sebelum
 * memutuskan apakah command perlu diantre sama sekali, bukan
 * menjalankan lalu berharap bisa menangkap kalau gagal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kelas_reminder_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('jadwal_kelas_reminder_logs', 'jadwal_reminder_rule_id')) {
                $table->foreignUuid('jadwal_reminder_rule_id')
                    ->nullable()
                    ->after('jadwal_kelas_id')
                    ->constrained(table: 'jadwal_reminder_rules', indexName: 'jkrl_reminder_rule_fk')
                    ->nullOnDelete();
            }
        });

        // Lihat docblock class di atas ("Bug fix 4 September 2026") --
        // urutan WAJIB unique komposit BARU dulu, baru drop unique lama,
        // supaya FK jadwal_kelas_id tidak pernah sesaat pun kehilangan
        // index pendukung (MySQL error 1553 kalau kebalik). Guard pakai
        // Schema::hasIndex() (dicek SEBELUM command diantre), bukan
        // try/catch di dalam closure (tidak pernah benar-benar
        // menangkap apa pun -- lihat docblock).
        if (! Schema::hasIndex('jadwal_kelas_reminder_logs', 'jkrl_kelas_rule_unique')) {
            Schema::table('jadwal_kelas_reminder_logs', function (Blueprint $table) {
                $table->unique(['jadwal_kelas_id', 'jadwal_reminder_rule_id'], 'jkrl_kelas_rule_unique');
            });
        }

        if (Schema::hasIndex('jadwal_kelas_reminder_logs', ['jadwal_kelas_id'], 'unique')) {
            Schema::table('jadwal_kelas_reminder_logs', function (Blueprint $table) {
                $table->dropUnique(['jadwal_kelas_id']);
            });
        }

        // Backfill (lihat docblock di atas): company yang cuma pernah
        // punya SATU rule (kasus paling umum, hasil backfill migration
        // sebelumnya) -- baris log lama miliknya di-set ke rule itu.
        // Company yang somehow sudah punya >1 rule di titik ini DILEWATI
        // (tidak ada cara aman menebak baris log lama itu dulunya untuk
        // rule yang MANA) -- rule_id-nya tetap NULL, konsekuensinya
        // sudah dijelaskan di docblock atas.
        DB::table('jadwal_reminder_settings')
            ->select('id', 'company_id')
            ->get()
            ->each(function ($setting) {
                $ruleIds = DB::table('jadwal_reminder_rules')
                    ->where('jadwal_reminder_setting_id', $setting->id)
                    ->pluck('id');

                if ($ruleIds->count() !== 1) {
                    return;
                }

                DB::table('jadwal_kelas_reminder_logs')
                    ->where('company_id', $setting->company_id)
                    ->whereNull('jadwal_reminder_rule_id')
                    ->update(['jadwal_reminder_rule_id' => $ruleIds->first()]);
            });
    }

    public function down(): void
    {
        // Sama seperti up() -- WAJIB pasang balik unique tunggal
        // `jadwal_kelas_id` DULU (supaya FK-nya tetap punya index
        // pendukung), BARU drop unique komposit-nya. Dibalik urutannya
        // bakal kena MySQL error 1553 yang sama persis dengan yang
        // diperbaiki di up().
        if (! Schema::hasIndex('jadwal_kelas_reminder_logs', ['jadwal_kelas_id'], 'unique')) {
            Schema::table('jadwal_kelas_reminder_logs', function (Blueprint $table) {
                $table->unique('jadwal_kelas_id');
            });
        }

        if (Schema::hasIndex('jadwal_kelas_reminder_logs', 'jkrl_kelas_rule_unique')) {
            Schema::table('jadwal_kelas_reminder_logs', function (Blueprint $table) {
                $table->dropUnique('jkrl_kelas_rule_unique');
            });
        }

        Schema::table('jadwal_kelas_reminder_logs', function (Blueprint $table) {
            if (Schema::hasColumn('jadwal_kelas_reminder_logs', 'jadwal_reminder_rule_id')) {
                $table->dropForeign('jkrl_reminder_rule_fk');
                $table->dropColumn('jadwal_reminder_rule_id');
            }
        });
    }
};
