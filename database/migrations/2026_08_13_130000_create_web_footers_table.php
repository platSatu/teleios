<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WebFooter — a flat list of footer link
     * blocks/columns for the public web site (fe-konexa), same shape as
     * web_headers/web_features (flat list, status-filtered, only
     * `status = active` rows exposed publicly). Each row is one footer
     * link/block: a name + destination link, whether it should open in
     * a new tab, and the grid width it takes in the footer layout
     * (column_width — col-md-3 or col-md-4), plus an optional
     * background_image/background_color for that block. sort_order
     * added for the same reason web_headers has it — an orderable
     * public list needs an explicit display order, not just
     * created_at.
     *
     * Table/model named "web_footers"/WebFooter (plural table, matching
     * every other Web* table in this app — web_headers, web_features,
     * web_faqs, ...) even though the request said "web_footer"
     * (singular) — kept consistent with the established naming
     * convention rather than the literal singular form.
     */
    public function up(): void
    {
        Schema::create('web_footers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('background_image')->nullable();
            $table->string('background_color', 20)->nullable();

            // Lebar kolom blok ini di grid footer — pilihan terbatas
            // (bukan bebas) supaya tetap konsisten dengan grid Bootstrap
            // frontend. Default col-md-3 (4 blok per baris).
            $table->string('column_width', 20)->default('col-md-3'); // col-md-3 | col-md-4

            $table->string('name');
            $table->string('link');
            $table->boolean('target_blank')->default(false); // true = buka di tab baru (target="_blank")

            $table->unsignedInteger('sort_order')->default(0);

            $table->string('status', 20)->default('active'); // active | inactive — hanya active yang tampil di frontend

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_footers');
    }
};
