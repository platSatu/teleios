<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalKelasSesiMurid — one murid's attendance/
 * reschedule record for one dated JadwalKelasSesi occurrence. This is
 * the row the whole feature exists around: the reminder gets sent
 * against this row (reminder_sent_at), and a WA reply that matches it
 * (see App\Http\Controllers\Api\WaIncomingMessageWebhookController's
 * new confirmation check) updates `status`/`confirmed_at` directly —
 * closing the exact gap described as the recurring field problem: "di
 * WA konfirmasi tapi di Excel tidak terupdate sehingga kelupaan dan
 * bentrok".
 *
 * confirmation_channel records HOW status last changed (e.g.
 * 'wa_reply' vs 'admin_manual') — useful for telling "murid replied
 * themselves" apart from "an admin fixed this by hand later" without
 * losing either fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_kelas_sesi_murid', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('jadwal_kelas_sesi_id')->constrained('jadwal_kelas_sesi')->cascadeOnDelete();
            $table->foreignUuid('jadwal_kelas_murid_id')->constrained('jadwal_kelas_murid')->cascadeOnDelete();

            // terjadwal | hadir | izin | pindah_hari | tidak_ada_kabar
            $table->string('status', 20)->default('terjadwal');

            // Only set when status = pindah_hari (just THIS student
            // moved, class itself still runs as scheduled for everyone
            // else).
            $table->date('tanggal_pindah')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_channel', 20)->nullable(); // wa_reply | admin_manual

            $table->timestamps();

            $table->unique(['jadwal_kelas_sesi_id', 'jadwal_kelas_murid_id'], 'jks_murid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_kelas_sesi_murid');
    }
};
