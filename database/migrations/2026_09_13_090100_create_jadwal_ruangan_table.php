<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalRuangan — "Ruangan" per branch, murni info
 * (nama + catatan kegunaan opsional). SENGAJA tidak mengunci satu
 * ruangan ke satu Kelas/Kategori tertentu -- satu ruangan bisa dipakai
 * piano lalu gitar akustik gantian (spec Jadwal v2 poin 2 di
 * CLAUDE.md), jadi tidak ada FK ke jadwal_mata_pelajaran/jadwal_kategori
 * di tabel ini. Validasi bentrok ruangan dicek di level
 * App\Models\JadwalRutin (lihat migration create_jadwal_rutin_table),
 * bukan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_ruangan', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained('branch_offices')
                ->cascadeOnDelete();

            $table->string('name');

            // Catatan kegunaan -- murni informasi/saran untuk admin
            // saat memilih ruangan di Jadwal Rutin, BUKAN pengunci.
            $table->text('catatan_kegunaan')->nullable();

            // 'active' | 'inactive'
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['company_id', 'branch_office_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_ruangan');
    }
};
