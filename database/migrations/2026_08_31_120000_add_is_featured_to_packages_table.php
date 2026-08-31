<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superadmin bisa menandai satu atau lebih package sebagai "TERPOPULER"
 * secara manual lewat form Package (lihat resources/views/superadmin/
 * package/_form.blade.php). Sebelumnya badge ini murni heuristik posisi
 * di fe-konexa (paket harga tengah dari 3+ paket) -- lihat komentar lama
 * di fe-konexa's resources/views/frontend/partials/packages.blade.php.
 * Default false supaya package lama/baru tidak tiba-tiba semuanya
 * dianggap unggulan begitu kolom ini ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
