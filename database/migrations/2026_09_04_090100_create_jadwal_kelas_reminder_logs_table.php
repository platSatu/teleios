<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 2 integrasi Chat<->Jadwal -- jejak klaim/kirim pengingat WA per
 * App\Models\JadwalKelas. `jadwal_kelas_id` UNIQUE (bukan komposit
 * seperti wa_message_schedule_logs) karena satu baris Jadwal Kelas =
 * satu sesi 1-on-1 yang cuma butuh SATU pengingat, bukan jadwal
 * berulang -- lihat App\Console\Commands\DispatchDueJadwalReminders'
 * docblock untuk pola klaim race-safe-nya (mengikuti pola
 * WaMessageScheduleLog/DispatchDueWaMessageSchedules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_kelas_reminder_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('jadwal_kelas_id')
                ->unique()
                ->constrained('jadwal_kelas')
                ->cascadeOnDelete();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // 'pending' | 'sent' | 'skipped' | 'failed'
            $table->string('status', 20)->default('pending');

            $table->string('message_id')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);

            $table->text('error')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_kelas_reminder_logs');
    }
};
