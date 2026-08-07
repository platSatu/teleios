<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Perlu ada setting ga si 1 pertemuan itu berapa menit, terkadang di
 * lapangan yang terjadi itu jadi 2x atau jadi 1 jam" — durasi_menit is a
 * per-subject DEFAULT only (e.g. Matematika biasanya 90 menit), used
 * purely to auto-suggest jam_selesai when an admin picks this mata
 * pelajaran on the Jadwal Kelas create form (Jadwal\JadwalKelasController
 * ::formData(), resources/views/jadwal/jadwal-kelas/_form.blade.php) —
 * still freely editable per class, and the real-world "kadang jadi 2x
 * atau 1 jam" variance on any given date is handled by the already-built
 * per-sesi jam_mulai_override/jam_selesai_override (Jadwal\
 * JadwalKelasSesiController::rescheduleTime()), not by this column.
 * Nullable: leave empty and the form behaves exactly as before (no
 * auto-suggestion, admin types jam_selesai by hand).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->unsignedSmallInteger('durasi_menit')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->dropColumn('durasi_menit');
        });
    }
};
