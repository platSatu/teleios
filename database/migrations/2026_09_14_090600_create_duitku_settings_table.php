<?php

use App\Models\DuitkuSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\DuitkuSetting — moves the Duitku (payment gateway)
 * merchant credentials OUT of .env (DUITKU_MERCHANT_CODE/DUITKU_API_KEY/
 * DUITKU_SANDBOX) and into the database, so a superadmin can manage them
 * from Superadmin > Deposits > Pengaturan Duitku (App\Http\Controllers\
 * Superadmin\DuitkuSettingController) instead of editing the server's
 * .env file directly.
 *
 * Deliberately a SINGLETON table (App\Models\DuitkuSetting::current()
 * always operates on the first/only row, creating it lazily if missing)
 * — same pattern as App\Models\AiModerationSetting — there is exactly one
 * platform-wide Duitku merchant account, not one per company (deposits
 * top up each user's own wallet, but the Duitku MERCHANT receiving the
 * money is teleios/Konexa itself).
 *
 * Sandbox and production credentials are stored as TWO SEPARATE pairs
 * (`sandbox_merchant_code`/`sandbox_api_key` vs `production_merchant_code`/
 * `production_api_key`) rather than one shared pair — Duitku issues
 * different merchant accounts for its sandbox and production
 * environments, so the two are rarely (if ever) the same value. `mode`
 * ('sandbox'|'production') just picks which pair App\Services\Payment\
 * DuitkuService::make() actually uses (see DuitkuSetting::
 * activeMerchantCode()/activeApiKey()/isSandbox()) — flipping it doesn't
 * touch either credential pair, so a superadmin can switch back and
 * forth without re-typing anything, as long as both pairs were entered
 * up front.
 *
 * Both *_api_key columns are `text` + cast `encrypted` on the model
 * (same treatment ai_moderation_settings.api_key and
 * wa_ai_bots.api_configuration already get) — never stored or logged in
 * plain text.
 *
 * Backfill below: an existing deployment (like this one) already has
 * live DUITKU_MERCHANT_CODE/DUITKU_API_KEY/DUITKU_SANDBOX values in its
 * .env, actively used by the deposit/top-up flow — this migration
 * copies them into the new table's first row (mode taken from the
 * current DUITKU_SANDBOX) so App\Services\Payment\DuitkuService keeps
 * working immediately once this migration runs, with ZERO manual
 * re-entry required. Uses the Eloquent model (not DB::table()->insert())
 * specifically so the `encrypted` cast actually runs on the API key
 * being backfilled. A fresh install with no DUITKU_* in .env just skips
 * the backfill — DuitkuSetting::current() creates an empty default row
 * (mode 'sandbox', both credential pairs null) the first time anything
 * asks for it, same as AiModerationSetting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duitku_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // 'sandbox' | 'production' — which credential pair below is
            // actually in use right now.
            $table->string('mode', 20)->default(DuitkuSetting::MODE_SANDBOX);

            $table->string('sandbox_merchant_code')->nullable();
            $table->text('sandbox_api_key')->nullable();

            $table->string('production_merchant_code')->nullable();
            $table->text('production_api_key')->nullable();

            $table->foreignUuid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

        // Lihat docblock class di atas ("Backfill") — pindahkan
        // kredensial .env yang SEKARANG dipakai supaya flow deposit tidak
        // langsung mati begitu migration ini jalan di produksi.
        $merchantCode = env('DUITKU_MERCHANT_CODE');
        $apiKey = env('DUITKU_API_KEY');

        if ($merchantCode || $apiKey) {
            $sandbox = (bool) env('DUITKU_SANDBOX', true);

            DuitkuSetting::create([
                'mode' => $sandbox ? DuitkuSetting::MODE_SANDBOX : DuitkuSetting::MODE_PRODUCTION,
                'sandbox_merchant_code' => $sandbox ? $merchantCode : null,
                'sandbox_api_key' => $sandbox ? $apiKey : null,
                'production_merchant_code' => $sandbox ? null : $merchantCode,
                'production_api_key' => $sandbox ? null : $apiKey,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('duitku_settings');
    }
};
