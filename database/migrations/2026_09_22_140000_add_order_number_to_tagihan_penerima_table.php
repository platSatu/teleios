<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan setelah create_tagihan_penerima_table.php -- BUKAN diedit
 * langsung di migration lama itu (best practice: migration yang sudah
 * ada jangan diubah lagi), meskipun migration itu sendiri belum pernah
 * dijalankan (php artisan migrate) di database manapun saat ini.
 *
 * `order_number` = merchantOrderId yang dikirim ke Duitku, lihat
 * App\Models\TagihanPenerima::boot() (prefix "TGH-") dan
 * App\Http\Controllers\Tagihan\TagihanDuitkuCallbackController (yang
 * mencari baris ini balik pakai kolom ini, persis pola
 * App\Models\Deposit::reference_number).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_penerima', function (Blueprint $table) {
            $table->string('order_number', 40)->nullable()->unique()->after('tagihan_pelanggan_id');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_penerima', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });
    }
};
