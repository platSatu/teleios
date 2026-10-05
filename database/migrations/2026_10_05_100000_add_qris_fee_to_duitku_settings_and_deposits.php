<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya QRIS bisa dibebankan ke customer saat top up (5 Oktober 2026),
 * lihat App\Models\DuitkuSetting::qrisFeeFor().
 * - duitku_settings: saklar, tarif (%), dan kode metode QRIS di Duitku.
 * - deposits: fee_amount (biaya yang dibayar customer) & payment_amount
 *   (total yang ditagihkan ke Duitku). Saldo yang masuk tetap `amount`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duitku_settings', function (Blueprint $table) {
            $table->boolean('qris_fee_to_customer')->default(false)->after('mode');
            $table->decimal('qris_fee_percent', 5, 2)->default(0.70)->after('qris_fee_to_customer');
            $table->string('qris_payment_code', 10)->default('SQ')->after('qris_fee_percent');
        });

        Schema::table('deposits', function (Blueprint $table) {
            $table->decimal('fee_amount', 15, 2)->default(0)->after('amount');
            $table->decimal('payment_amount', 15, 2)->nullable()->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'payment_amount']);
        });

        Schema::table('duitku_settings', function (Blueprint $table) {
            $table->dropColumn(['qris_fee_to_customer', 'qris_fee_percent', 'qris_payment_code']);
        });
    }
};
