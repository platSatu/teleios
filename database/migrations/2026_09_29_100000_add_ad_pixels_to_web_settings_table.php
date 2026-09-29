<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID pixel iklan untuk retargeting di fe-konexa (Pengaturan Web):
 * Meta (Instagram/Facebook), TikTok, dan Google Ads (YouTube/Search/
 * Display). Semua nullable -- kosong berarti script-nya tidak dipasang.
 */
return new class extends Migration
{
    private const COLUMNS = ['meta_pixel_id', 'tiktok_pixel_id', 'google_ads_id'];

    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $after = 'google_analytics';

            foreach (self::COLUMNS as $column) {
                if (! Schema::hasColumn('web_settings', $column)) {
                    $table->string($column, 50)->nullable()->after($after);
                }
                $after = $column;
            }
        });
    }

    public function down(): void
    {
        $existing = array_values(array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn('web_settings', $column)));

        if ($existing !== []) {
            Schema::table('web_settings', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
