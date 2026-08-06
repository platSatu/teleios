<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WebArticle — one article under a
     * web_category_articles section. Adds the SEO trio on top of the
     * plain content fields: meta_keywords (free text), meta_descriptions
     * and meta_images (both nullable — App\Models\WebArticle falls back
     * to `description`/`images` when empty rather than leaving the
     * public page's <meta> tags blank, see the model's accessors).
     * `meta_tags` itself is NOT a column here — an article can carry
     * several, so it's the web_article_meta_tag pivot below instead of
     * a single FK. count_read is maintained by the (future) public page
     * view, not editable from this admin form.
     */
    public function up(): void
    {
        Schema::create('web_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // restrictOnDelete — same reasoning as api_documentations.category_documentation_id:
            // a category that already has articles under it can't be
            // deleted out from under them.
            $table->foreignUuid('web_category_article_id')
                ->constrained('web_category_articles')
                ->restrictOnDelete();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description');
            $table->string('images');

            $table->text('meta_keywords')->nullable();
            $table->text('meta_descriptions')->nullable();
            $table->string('meta_images')->nullable();

            $table->unsignedBigInteger('count_read')->default(0);
            $table->timestamp('date_publish')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });

        // web_articles <-> web_meta_tags, many-to-many: one article can
        // carry several reusable SEO meta tags from the catalog (see
        // create_web_meta_tags_table). No surrogate id — the composite
        // primary key is enough for a pure pivot and keeps sync()
        // idempotent.
        Schema::create('web_article_meta_tag', function (Blueprint $table) {
            $table->foreignUuid('web_article_id')->constrained('web_articles')->cascadeOnDelete();
            $table->foreignUuid('web_meta_tag_id')->constrained('web_meta_tags')->cascadeOnDelete();
            $table->primary(['web_article_id', 'web_meta_tag_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_article_meta_tag');
        Schema::dropIfExists('web_articles');
    }
};
