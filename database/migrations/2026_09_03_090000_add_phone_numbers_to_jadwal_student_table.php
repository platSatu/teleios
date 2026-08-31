<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 1 dari integrasi Chat<->Jadwal (lihat App\Jobs\SendScheduledWaMessage
 * & App\Services\PackageLimitService docblocks untuk konteks arsitektur
 * package/kategori): kedua nomor ini opsional & independen -- boleh isi
 * salah satu, keduanya, atau tidak sama sekali. Dipakai sebagai target
 * kirim WA nanti di fitur pengingat Jadwal & permintaan reschedule
 * (tahap-tahap berikutnya), TIDAK dipakai/dibaca oleh fitur mana pun
 * yang sudah ada saat ini -- murni penyimpanan data dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_student', function (Blueprint $table) {
            $table->string('parent_phone_number', 32)->nullable()->after('name');
            $table->string('student_phone_number', 32)->nullable()->after('parent_phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_student', function (Blueprint $table) {
            $table->dropColumn(['parent_phone_number', 'student_phone_number']);
        });
    }
};
