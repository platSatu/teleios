<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal baru yang DIMINTA murid lewat chatbot flow -- diisi OTOMATIS
 * kalau flow-nya pakai step 'choice' dengan options_source =
 * OPTIONS_SOURCE_OPEN_SLOTS_SAME_PENGAJAR (lihat App\Services\Chat\
 * ChatbotFlowService::createJadwalRescheduleRequest()), NULL kalau
 * flow-nya tidak pakai step itu (mis. flow lama yang cuma tanya bebas
 * lewat teks) -- staff tetap isi manual seperti sekarang lewat form
 * approve() di App\Http\Controllers\Jadwal\
 * JadwalRescheduleRequestController.
 *
 * Sengaja terpisah dari `jadwal_kelas`.start_time/end_time (bukan
 * langsung mengubahnya) -- ini masih sekadar PERMINTAAN, belum
 * keputusan; App\Models\JadwalKelas cuma benar-benar berubah saat
 * staff approve() secara eksplisit, sesuai aturan "wajib approve
 * staff" yang sudah dipegang sejak Tahap 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kelas_reschedule_requests', function (Blueprint $table) {
            $table->timestamp('requested_new_start_time')->nullable()->after('jadwal_kelas_id');
            $table->timestamp('requested_new_end_time')->nullable()->after('requested_new_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kelas_reschedule_requests', function (Blueprint $table) {
            $table->dropColumn(['requested_new_start_time', 'requested_new_end_time']);
        });
    }
};
