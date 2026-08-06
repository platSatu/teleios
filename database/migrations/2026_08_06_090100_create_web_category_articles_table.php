<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WebCategoryArticle — groups entries in
     * web_articles into sections (e.g. "Tips", "Rilis Produk"). Same
     * catalog shape as category_documentations, plus `images` (a path
     * relative to public/web/images — see App\Helpers\WebImageUploader)
     * and `date_publish` since this catalog is meant for a public-facing
     * blog/news listing rather than internal documentation. Superadmin-
     * managed via Superadmin\Web\CategoryArticleController
     * (dashboard/superadmin/web/category-articles).
     */
    public function up(): void
    {
        Schema::create('web_category_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('images')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('date_publish')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_category_articles');
    }
};
