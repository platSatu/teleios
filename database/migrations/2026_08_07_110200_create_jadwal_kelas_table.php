<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalKelas — the recurring weekly class "template"
 * (one hari + jam_mulai/jam_selesai pattern). Actual dated occurrences
 * live one level down in jadwal_kelas_sesi.
 *
 * guru_user_id: a User assigned the "Guru" CompanyRole. Deliberately NOT
 * scoped to require that role be branch-locked to this same
 * branch_office_id — "guru bisa saja ngajar di dua tempat yang berbeda
 * atau di cabang yang sama" (per spec), so one teacher can simply have
 * several JadwalKelas rows across different branches.
 *
 * device_id: which connected WhatsApp device sends this class's
 * notifications — plain nullable uuid, NO foreign key, same reasoning
 * as wa_devices.branch_office_id (see that migration's docblock):
 * wa_devices is owned/created by the Go backend's own GORM AutoMigrate,
 * a different storage engine than Laravel's own InnoDB tables, so a
 * real FK can't be formed across them. Chosen explicitly by whoever
 * creates the class (same picker component already used by Pesan
 * Terjadwal), not auto-resolved — mirrors WaMessageSchedule.device_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('branch_office_id')->constrained('branch_offices')->cascadeOnDelete();
            $table->foreignUuid('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();

            // nullOnDelete, not cascade: losing the assigned teacher's
            // user account shouldn't silently delete the whole class and
            // its roster — it just needs to be reassigned.
            $table->foreignUuid('guru_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->uuid('device_id')->nullable();

            $table->string('name')->nullable();
            $table->string('hari', 20); // Senin..Minggu
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->unsignedInteger('kapasitas')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            $table->index(['branch_office_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_kelas');
    }
};
