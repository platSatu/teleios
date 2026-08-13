<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `running_text` — a single line of text shown as an infinite
 * scrolling marquee/ticker banner on fe-konexa (see
 * frontend/partials/running-text.blade.php), repeated end-to-end and
 * separated by a small diamond icon, edge-to-edge like the Fitur
 * Unggulan slider. Plain nullable text column (not an image, so no
 * WebImageUploader involved) — banner is simply not rendered when
 * empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->text('running_text')->nullable()->after('tiktok_url');
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn('running_text');
        });
    }
};
