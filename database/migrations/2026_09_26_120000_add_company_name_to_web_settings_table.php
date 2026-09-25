<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama badan usaha (mis. "PT Bizbos Teknologi Indonesia") untuk baris
 * copyright footer fe-konexa. Nullable: kalau kosong footer tetap
 * menampilkan "Bizbos". Tidak dipakai di tempat lain (APP_NAME, judul
 * halaman, email tidak ikut berubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('web_settings', 'company_name')) {
            Schema::table('web_settings', function (Blueprint $table) {
                $table->string('company_name')->nullable()->after('address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('web_settings', 'company_name')) {
            Schema::table('web_settings', fn (Blueprint $table) => $table->dropColumn('company_name'));
        }
    }
};
