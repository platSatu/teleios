<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WebMetaTag — reusable SEO meta tag entries a
     * superadmin can attach to public web content (see web_articles.meta_tags
     * and future web_videos.meta_tags). Superadmin-managed via
     * Superadmin\Web\MetaTagController (dashboard/superadmin/web/meta-tags).
     * Same catalog shape as category_documentations/category_applications —
     * uuid PK, unique slug derived from name, plain active/inactive status.
     */
    public function up(): void
    {
        Schema::create('web_meta_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_meta_tags');
    }
};
