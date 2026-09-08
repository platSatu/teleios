<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan jadwal_grade_id (nullable) ke jadwal_rutin -- kolom
 * jadwal_kategori_id yang SUDAH ADA (required, restrictOnDelete) TETAP
 * DIPERTAHANKAN APA ADANYA (auto-diisi dari Grade->Kategori-nya di
 * app, lihat App\Http\Controllers\Jadwal\JadwalRutinController &
 * App\Http\Controllers\Jadwal\JadwalStudentController::createRutinFromSlots())
 * supaya SEMUA query lama yang mengandalkan jadwal_kategori_id (badge
 * count, filter Bidang/Mata Pelajaran, dsb -- lihat App\Services\
 * Jadwal\JadwalCountsService) tetap jalan tanpa perubahan sama sekali.
 * jadwal_grade_id ini yang jadi sumber PRICING baru (lihat App\Services\
 * Jadwal\JadwalRutinSesiGenerator) & penentu keanggotaan Pengajar (lewat
 * App\Models\JadwalPengajarGrade), MENGGANTIKAN peran itu dari Kategori.
 *
 * Nullable (bukan required) supaya baris JadwalRutin LAMA (dibuat
 * sebelum fitur Grade ada) tidak langsung invalid -- akan dibackfill
 * lewat migration backfill_default_jadwal_grade_for_existing_kategori.php
 * ke Grade default milik Kategori-nya masing-masing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_rutin', function (Blueprint $table) {
            $table->foreignUuid('jadwal_grade_id')
                ->nullable()
                ->after('jadwal_kategori_id')
                ->constrained('jadwal_grade')
                ->restrictOnDelete();

            $table->index(['jadwal_grade_id']);
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_rutin', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jadwal_grade_id');
        });
    }
};
