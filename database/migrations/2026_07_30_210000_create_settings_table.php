<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Generic key-value store for global, superadmin-editable settings —
     * starting with the purchase cashback/point rule (App\Models\Setting).
     * Kept generic rather than a dedicated "point_settings" table so
     * future admin-configurable values (PIN attempt limits, transfer
     * minimums, etc.) have somewhere to live without a new table each
     * time.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $now = now();

        // Default cashback rule: every complete Rp 10.000 spent on a
        // package purchase earns Rp 100 credited straight back to the
        // buyer's own wallet (App\Http\Controllers\Dashboard\
        // PackageCheckoutController::payPurchaseCashback()). Both
        // numbers are editable by superadmin (Superadmin\
        // PointSettingController).
        DB::table('settings')->insert([
            [
                'id' => (string) Str::uuid(),
                'key' => 'point_amount_threshold',
                'value' => '10000',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'key' => 'point_value',
                'value' => '100',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'key' => 'point_enabled',
                'value' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
