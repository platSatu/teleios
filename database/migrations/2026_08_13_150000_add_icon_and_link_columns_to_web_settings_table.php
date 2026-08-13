<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an uploadable ICON image per social platform, alongside its link
 * — Instagram/Facebook/YouTube/TikTok. `instagram_url` and
 * `facebook_url` already exist (see
 * 2026_08_13_140100_add_social_links_to_web_settings_table.php) and are
 * reused as-is for the "link" half of those two platforms, so this
 * migration only adds their new `icon_*` image columns plus BOTH the
 * icon and link columns for the two platforms that didn't exist yet
 * (YouTube, TikTok). `twitter_url` (added in that same earlier
 * migration) is left untouched — not part of this request.
 *
 * Icon columns follow the same convention as every other image column
 * in this app (favicon/logo/meta_images on this very table): a plain
 * nullable string storing a path relative to public/web/images (see
 * App\Helpers\WebImageUploader), not a binary/blob column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->string('icon_instagram')->nullable()->after('instagram_url');
            $table->string('icon_facebook')->nullable()->after('facebook_url');

            $table->string('icon_youtube')->nullable()->after('twitter_url');
            $table->string('youtube_url')->nullable()->after('icon_youtube');

            $table->string('icon_tiktok')->nullable()->after('youtube_url');
            $table->string('tiktok_url')->nullable()->after('icon_tiktok');
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn([
                'icon_instagram',
                'icon_facebook',
                'icon_youtube',
                'youtube_url',
                'icon_tiktok',
                'tiktok_url',
            ]);
        });
    }
};
