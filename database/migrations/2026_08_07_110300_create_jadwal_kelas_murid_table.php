<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalKelasMurid — enrollment of one murid (a User
 * assigned the "Murid" CompanyRole) into one JadwalKelas. This is the
 * roster; actual per-date attendance/reschedule tracking lives in
 * jadwal_kelas_sesi_murid, one level down from jadwal_kelas_sesi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_kelas_murid', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('jadwal_kelas_id')->constrained('jadwal_kelas')->cascadeOnDelete();
            $table->foreignUuid('murid_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 20)->default('active'); // active | berhenti
            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->unique(['jadwal_kelas_id', 'murid_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_kelas_murid');
    }
};
