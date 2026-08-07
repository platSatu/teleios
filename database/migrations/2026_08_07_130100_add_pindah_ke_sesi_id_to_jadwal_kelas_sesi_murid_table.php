<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ada murid tidak masuk dan pengen geser jadwal, sistem langsung
 * mencari... memberikan pilihan available nya di tanggal segini jam
 * segini apakah berminat" — pindah_ke_sesi_id is the difference between
 * the OLD tanggal_pindah (just a free-text-ish date, no real link to
 * anywhere) and an actual cross-class transfer: it points at the real
 * JadwalKelasSesi row (possibly belonging to a DIFFERENT JadwalKelas —
 * a makeup class on another day, another subject-group's slot, etc.)
 * the murid is being offered/moved into, found by
 * App\Services\Jadwal\JadwalAvailabilityService::findAlternativeSlotsForMurid().
 *
 * tanggal_pindah is kept as-is for the simple "same class, admin just
 * typed a date" case — this column is only set on top of it when the
 * move is to a concrete, capacity-checked alternate session.
 *
 * nullOnDelete(): if that target sesi is later deleted, this row's own
 * history (status/catatan) shouldn't disappear with it — it just loses
 * the link to where they moved to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kelas_sesi_murid', function (Blueprint $table) {
            $table->foreignUuid('pindah_ke_sesi_id')->nullable()->after('tanggal_pindah')
                ->constrained('jadwal_kelas_sesi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kelas_sesi_murid', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pindah_ke_sesi_id');
        });
    }
};
