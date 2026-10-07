<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asal Student dari Form (7 Oktober 2026): Student yang dibuat lewat tombol
 * "+ Add Student" di Form > Submission menyimpan form asalnya (form_headers),
 * supaya nanti bisa dilihat murid ini mendaftar dari form mana. Nullable --
 * Student yang dibuat manual tetap kosong. Kalau form-nya dihapus, kolom ini
 * jadi NULL (Student tidak ikut terhapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_student', function (Blueprint $table) {
            $table->foreignUuid('form_id')->nullable()->after('status')
                ->constrained('form_headers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_student', function (Blueprint $table) {
            $table->dropConstrainedForeignId('form_id');
        });
    }
};
