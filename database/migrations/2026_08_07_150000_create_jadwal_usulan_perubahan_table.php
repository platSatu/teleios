<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Jika murid mengajukan perubahan jadwal dan gurunya menjawab iya/bisa,
 * artinya jadwal terupdate — tapi sistem akan menolak jika jadwal guru
 * tersebut sudah ada [bentrok]." Distinct from the existing
 * jadwal_kelas_sesi_murid.pindah_ke_sesi_id flow (which only offers
 * slots inside an ALREADY EXISTING JadwalKelas — capacity-checked, but
 * that guru already committed to that slot, so there's nothing to ask
 * them) — this is for a genuinely custom, ad-hoc makeup date/time with
 * the SAME guru, which has to be proposed to and approved by them
 * before it's real.
 *
 * Two conflict checks happen around this row's lifecycle, not one:
 * once when an admin first proposes it (App\Http\Controllers\Jadwal\
 * JadwalUsulanController::store() — refuses to even send the WA ask if
 * the guru is already busy then), and again the moment the guru's
 * WA reply arrives (App\Http\Controllers\Api\
 * WaIncomingMessageWebhookController::tryConfirmUsulan() — something
 * else could have been booked into that same slot in the time between
 * asking and answering, so "iya" is re-verified, never blindly trusted).
 *
 * Deliberately NOT resolved by AI — a `status` flip here writes
 * directly into the schedule, so this only ever moves on an exact,
 * unambiguous CONFIRM/DECLINE keyword match (same constants
 * WaIncomingMessageWebhookController already uses for every other
 * Jadwal confirmation), same reasoning as everywhere else in this
 * feature: predictable and auditable beats clever-but-occasionally-wrong
 * for something that can silently double-book a teacher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_usulan_perubahan', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('jadwal_kelas_id')->constrained('jadwal_kelas')->cascadeOnDelete();
            $table->foreignUuid('jadwal_kelas_sesi_murid_id')->nullable()
                ->constrained('jadwal_kelas_sesi_murid')->cascadeOnDelete();
            $table->foreignUuid('guru_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('murid_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('tanggal_usulan');
            $table->time('jam_mulai_usulan');
            $table->time('jam_selesai_usulan');

            // pending | disetujui | ditolak | bentrok
            $table->string('status', 20)->default('pending');
            $table->text('catatan')->nullable();
            $table->string('diajukan_oleh', 20)->default('admin_manual'); // admin_manual (only source for now)

            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_usulan_perubahan');
    }
};
