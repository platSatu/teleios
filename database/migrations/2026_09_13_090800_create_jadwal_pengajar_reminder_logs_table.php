<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalPengajarReminderLog -- jejak klaim/kirim
 * rekap WA H-1 KE PENGAJAR (Jadwal v2, CLAUDE.md item #15 spec poin 9),
 * satu baris per (pengajar_id, reminder_date). BEDA dari
 * jadwal_kelas_reminder_logs (yang keyed per SATU jadwal_kelas_id,
 * dipakai reminder ke orang tua/murid) -- reminder pengajar itu SATU
 * pesan REKAP semua sesi hari itu, bukan satu pesan per sesi, jadi
 * butuh kunci unik yang berbeda: (pengajar_id, reminder_date), dicek
 * App\Console\Commands\DispatchJadwalPengajarDailyReminders dengan pola
 * race-safe yang sama (lockForUpdate + catch QueryException) seperti
 * App\Console\Commands\DispatchDueJadwalReminders::claimAndDispatch().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pengajar_reminder_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('pengajar_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('reminder_date');

            // 'pending' | 'sent' | 'failed' | 'skipped'
            $table->string('status', 20)->default('pending');

            $table->string('message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();

            $table->timestamps();

            $table->unique(['pengajar_id', 'reminder_date']);
            $table->index(['company_id', 'reminder_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pengajar_reminder_logs');
    }
};
