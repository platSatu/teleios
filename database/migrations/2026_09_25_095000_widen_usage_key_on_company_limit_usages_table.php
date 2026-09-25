<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * usage_key sekarang ikut memuat periode bulanan (lihat
 * App\Models\CompanyLimitUsage::booted()) -- 4 uuid + timestamp = +-162
 * karakter, lebih panjang dari 150 semula. Sengaja bertimestamp SEBELUM
 * migration 2026_09_25_100100 (yang menyimpan ulang baris usage lewat
 * model), supaya kolomnya sudah cukup lebar saat itu jalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_limit_usages', function (Blueprint $table) {
            $table->string('usage_key', 200)->change();
        });
    }

    public function down(): void
    {
        // Tidak dipersempit lagi: baris baru bisa lebih dari 150 karakter.
    }
};
