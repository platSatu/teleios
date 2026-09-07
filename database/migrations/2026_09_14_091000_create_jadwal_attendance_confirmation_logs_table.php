<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalAttendanceConfirmationLog -- jejak klaim/kirim
 * sesi konfirmasi kehadiran WA ke pengajar (fitur "5 menit setelah
 * selesai jam pelajaran, sistem kirim WA ke pengajar apakah murid
 * hadir"), SATU baris per jadwal_kelas_id (unique) -- App\Models\
 * JadwalKelas cuma boleh dikonfirmasi SEKALI, beda dari
 * jadwal_kelas_reminder_logs yang keyed per (jadwal_kelas_id, rule)
 * karena satu sesi bisa dapat BEBERAPA pengingat H-sekian. Dicek App\
 * Console\Commands\DispatchJadwalAttendanceConfirmations dengan pola
 * klaim race-safe yang sama (lockForUpdate + catch QueryException)
 * seperti App\Console\Commands\DispatchDueJadwalReminders::
 * claimAndDispatch() -- begitu baris ini ada (status apa pun), sesi
 * yang sama TIDAK PERNAH diklaim ulang oleh run command berikutnya,
 * sengaja sama seperti App\Models\JadwalPengajarReminderLog (tidak ada
 * retry otomatis untuk baris skipped/failed -- lihat App\Jobs\
 * SendJadwalAttendanceConfirmation's `tries`/backoff untuk retry
 * TRANSIENT dalam SATU klaim yang sama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_attendance_confirmation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('jadwal_kelas_id')
                ->constrained('jadwal_kelas')
                ->cascadeOnDelete();

            $table->foreignUuid('pengajar_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // 'pending' | 'sent' | 'failed' | 'skipped'
            $table->string('status', 20)->default('pending');

            $table->string('message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();

            $table->timestamps();

            $table->unique('jadwal_kelas_id');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_attendance_confirmation_logs');
    }
};
