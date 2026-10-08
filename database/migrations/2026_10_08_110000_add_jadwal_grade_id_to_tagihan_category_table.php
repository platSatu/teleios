<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penghubung Jadwal -> Tagihan (paket Combo, 8 Oktober 2026): Kategori
 * Tagihan yang dibuat otomatis dari Grade menyimpan grade asalnya, satu
 * per branch (unik). Kategori buatan manual tetap NULL. Kalau Grade
 * dihapus, kolom ini jadi NULL (kategori & invoice-nya tetap ada).
 * Lihat App\Services\Jadwal\StudentTagihanLink.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_category', function (Blueprint $table) {
            $table->foreignUuid('jadwal_grade_id')->nullable()->after('branch_office_id')
                ->constrained('jadwal_grade')->nullOnDelete();
            $table->unique(['jadwal_grade_id', 'branch_office_id'], 'tagihan_cat_grade_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_category', function (Blueprint $table) {
            $table->dropForeign(['jadwal_grade_id']);
            $table->dropUnique('tagihan_cat_grade_branch_unique');
            $table->dropColumn('jadwal_grade_id');
        });
    }
};
