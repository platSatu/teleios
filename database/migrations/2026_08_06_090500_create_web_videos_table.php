<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WebVideo — one video under a
     * web_category_videos section. Same SEO-trio shape as web_articles
     * (meta_keywords / meta_descriptions / meta_images, all nullable
     * with a fallback to description/thumbnail — see the model's
     * accessors) plus two video-specific fields: `videos` (an uploaded
     * file, stored under public/web/videos via App\Helpers\WebFileUploader
     * — NOT resized, unlike `thumbnail` which goes through
     * App\Helpers\WebImageUploader) and `link_youtube` (an embed or
     * short youtu.be link) — a video entry needs at least one of the
     * two, enforced in the controller rather than at the DB level since
     * "at least one of these two nullable columns" isn't expressible as
     * a plain column constraint.
     *
     * Column name note: the FK to web_category_videos is named
     * web_category_video_id here (not web_videos_id) — matches the
     * web_category_article_id convention on web_articles.
     */
    public function up(): void
    {
        Schema::create('web_videos', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // restrictOnDelete — same reasoning as web_articles.web_category_article_id.
            $table->foreignUuid('web_category_video_id')
                ->constrained('web_category_videos')
                ->restrictOnDelete();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('thumbnail')->nullable();
            $table->text('description')->nullable();
            $table->string('videos')->nullable();
            $table->string('link_youtube')->nullable();

            $table->text('meta_keywords')->nullable();
            $table->text('meta_descriptions')->nullable();
            $table->string('meta_images')->nullable();

            $table->unsignedBigInteger('count_read')->default(0);
            $table->timestamp('date_publish')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });

        // web_videos <-> web_meta_tags, many-to-many — same pattern as
        // web_article_meta_tag.
        Schema::create('web_video_meta_tag', function (Blueprint $table) {
            $table->foreignUuid('web_video_id')->constrained('web_videos')->cascadeOnDelete();
            $table->foreignUuid('web_meta_tag_id')->constrained('web_meta_tags')->cascadeOnDelete();
            $table->primary(['web_video_id', 'web_meta_tag_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_video_meta_tag');
        Schema::dropIfExists('web_videos');
    }
};
