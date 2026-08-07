<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalKelasSesi — one dated occurrence of a
 * JadwalKelas (whole-class level: did this Tuesday's class happen at
 * all, did the WHOLE class move, has the guru confirmed). Individual
 * student attendance/reschedule for this same date lives one level
 * down in jadwal_kelas_sesi_murid — kept as two tables (not flattened
 * into one) because "murid bisa pindah hari, absen, ganti hari, tidak
 * ada kabar tapi tetap ada kelas" means a single student's status can
 * diverge from the class's own status on the very same date.
 *
 * guru_reminder_sent_at / guru_confirmed_at: same reminder-then-confirm
 * mechanism as the per-student side (jadwal_kelas_sesi_murid), just for
 * the assigned guru instead — "ketika akan ada kelas, ketika dijawab
 * okey artinya sistem mengupdate", so the WA reply itself is what
 * flips guru_confirmed_at, not a manual step anyone has to remember
 * (that manual gap — WA confirms, Excel doesn't — is explicitly the
 * problem this whole feature exists to close).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_kelas_sesi', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('jadwal_kelas_id')->constrained('jadwal_kelas')->cascadeOnDelete();

            $table->date('tanggal');

            // terjadwal | berjalan | dipindah | dibatalkan
            $table->string('status', 20)->default('terjadwal');

            // Only set when status = dipindah (the WHOLE class moved,
            // not just one student — see jadwal_kelas_sesi_murid for
            // that case).
            $table->date('tanggal_pindah')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamp('guru_reminder_sent_at')->nullable();
            $table->timestamp('guru_confirmed_at')->nullable();

            $table->timestamps();

            $table->unique(['jadwal_kelas_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_kelas_sesi');
    }
};
