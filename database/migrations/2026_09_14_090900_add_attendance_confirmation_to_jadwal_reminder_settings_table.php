<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur konfirmasi kehadiran otomatis (permintaan user: "5 menit
 * setelah selesai jam pelajaran, sistem kirim WA ke pengajar apakah
 * murid hadir, kalau hadir tanya materi apa yang diajarkan") --
 * App\Console\Commands\DispatchJadwalAttendanceConfirmations only
 * proses company yang `attendance_confirmation_enabled` true DAN
 * `enabled`+`device_id` (kolom yang sudah ada) terisi, persis pola
 * gating yang sama dengan `remind_notify_pengajar`. Kalau dimatikan,
 * absensi tetap manual seperti sekarang (lihat menu Jadwal Kelas) --
 * TIDAK ADA perubahan perilaku untuk company yang belum sempat
 * mengaktifkannya (default false).
 *
 * `attendance_confirmation_flow_id` -- App\Models\WaChatbotFlow yang
 * dipakai sesi konfirmasi ini, dibuat/di-refresh sekali per company
 * oleh App\Services\Jadwal\AttendanceConfirmationFlowProvisioner
 * (bukan dibuat manual admin lewat flow builder seperti flow lain).
 * Disimpan di sini (bukan dicari ulang tiap kali lewat nama/marker
 * lain yang gampang meleset) supaya provisioner-nya idempotent dan
 * robust walau admin sempat mengubah nama flow-nya sendiri di builder.
 * nullOnDelete(): kalau baris flow-nya sampai terhapus manual, setting
 * ini tidak ikut rusak -- provisioner tinggal membuat flow baru lagi
 * saat berikutnya dibutuhkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_reminder_settings', function (Blueprint $table) {
            $table->boolean('attendance_confirmation_enabled')->default(false)->after('reschedule_notify_admin');

            $table->foreignUuid('attendance_confirmation_flow_id')
                ->nullable()
                ->after('attendance_confirmation_enabled')
                ->constrained('wa_chatbot_flows')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_reminder_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_confirmation_flow_id');
            $table->dropColumn('attendance_confirmation_enabled');
        });
    }
};
