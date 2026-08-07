<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two new capabilities on one dated JadwalKelasSesi occurrence:
 *
 * 1. jam_mulai_override / jam_selesai_override — "guru nya memajukan
 *    jadwalnya, biasanya ngajar dari jam 13.00 tiba-tiba dimajukan start
 *    ngajar nya dari jam 12.00": a ONE-OFF time change for this single
 *    date only, distinct from editing JadwalKelas.jam_mulai/jam_selesai
 *    itself (which changes the whole recurring pattern going forward —
 *    see Jadwal\JadwalKelasController::update()/notifyScheduleChanged()).
 *    Left null on every normal sesi; the class's usual jam_mulai/
 *    jam_selesai keeps applying whenever these are null.
 *
 * 2. guru_status + guru_pengganti_user_id — "gurunya sakit dan tidak
 *    bisa mengajar, cari pengganti": when the assigned guru can't teach
 *    a specific date, this sesi gets flagged 'sakit' and (once an admin
 *    picks a candidate — see App\Services\Jadwal\JadwalAvailabilityService
 *    ::findSubstituteGuru() — Jadwal\JadwalKelasSesiController::
 *    assignPengganti()) guru_pengganti_user_id records who's actually
 *    teaching THIS date, without touching JadwalKelas.guru_user_id (the
 *    regular guru is still the regular guru for every other date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kelas_sesi', function (Blueprint $table) {
            $table->time('jam_mulai_override')->nullable()->after('tanggal_pindah');
            $table->time('jam_selesai_override')->nullable()->after('jam_mulai_override');

            // normal | sakit | diganti
            $table->string('guru_status', 20)->default('normal')->after('guru_confirmed_at');
            $table->foreignUuid('guru_pengganti_user_id')->nullable()->after('guru_status')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kelas_sesi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guru_pengganti_user_id');
            $table->dropColumn(['jam_mulai_override', 'jam_selesai_override', 'guru_status']);
        });
    }
};
