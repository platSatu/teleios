<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds social profile links to the singleton web_settings row — powers
 * the social icon row in fe-konexa's footer (frontend/partials/footer.
 * blade.php). All nullable; fe-konexa only renders an icon for whichever
 * of these is actually filled in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->string('instagram_url')->nullable()->after('gmaps');
            $table->string('facebook_url')->nullable()->after('instagram_url');
            $table->string('twitter_url')->nullable()->after('facebook_url');
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn(['instagram_url', 'facebook_url', 'twitter_url']);
        });
    }
};
