<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `invoice_number` -- nomor invoice yang DITAMPILKAN ke pelanggan
 * (halaman publik & halaman Laporan admin), beda dari `order_number`
 * (migration 2026_09_22_140000_...) yang murni ID teknis buat Duitku dan
 * tidak pernah ditampilkan. Formatnya "{invoice_prefix category}-XXXXXX"
 * -- lihat App\Models\TagihanCategory::invoice_prefix (diatur admin
 * lewat Setting Tagihan tab "Invoice & Notifikasi") dan
 * App\Models\TagihanPenerima::boot() untuk cara generate-nya.
 *
 * 23 September 2026 (permintaan user poin 3.6 dari redesign Tagihan:
 * "user bisa buat nama invoice ... akan ditampilkan di tagihan ke user").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_penerima', function (Blueprint $table) {
            $table->string('invoice_number', 40)->nullable()->unique()->after('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_penerima', function (Blueprint $table) {
            $table->dropColumn('invoice_number');
        });
    }
};
