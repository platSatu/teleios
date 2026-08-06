<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WebCategoryVideo — groups entries in web_videos
     * into sections. Same shape as create_web_category_articles_table,
     * `thumbnail` instead of `images` (both store a path relative to
     * public/web/images — see App\Helpers\WebImageUploader). Superadmin-
     * managed via Superadmin\Web\CategoryVideoController
     * (dashboard/superadmin/web/category-videos).
     */
    public function up(): void
    {
        Schema::create('web_category_videos', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('thumbnail')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('date_publish')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_category_videos');
    }
};
