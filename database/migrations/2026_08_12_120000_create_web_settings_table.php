<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WebSetting — singleton site-wide settings row
     * (favicon, logo, meta tags, contact info, Google Tag Manager /
     * Analytics IDs, Google Maps embed) consumed by fe-konexa's public
     * frontend via GET /api/frontend/web-setting (see
     * App\Http\Controllers\Api\Frontend\WebSettingController). Same
     * singleton shape as App\Models\AiModerationSetting — always
     * accessed through WebSetting::current(), never queried directly,
     * so exactly one row ever exists.
     */
    public function up(): void
    {
        Schema::create('web_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Image paths (relative to public/web/images, same convention
            // as WebArticle/WebVideo/etc — see App\Helpers\WebImageUploader).
            $table->string('favicon')->nullable();
            $table->string('logo')->nullable();
            $table->string('meta_images')->nullable();

            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();

            $table->string('handphone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            // GTM Container ID (e.g. GTM-XXXXXXX) and GA4 Measurement ID
            // (e.g. G-XXXXXXXXXX) — just the ID, not a raw script snippet.
            // The official embed markup is generated from these in
            // layouts/frontend.blade.php, so no admin-authored script
            // ever gets echoed unescaped.
            $table->string('google_tag')->nullable();
            $table->string('google_analytics')->nullable();

            // Google Maps embed URL (the src of an <iframe>, from Maps'
            // own "Share > Embed a map" dialog).
            $table->text('gmaps')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_settings');
    }
};
