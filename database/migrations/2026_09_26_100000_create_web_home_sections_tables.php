<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Susunan beranda fe-konexa (Superadmin > Web > Susunan Beranda).
 *
 * - web_home_sections: satu baris = satu section beranda, urut sort_order.
 *   Section "bawaan" (hero, running_text, packages, features, faq) hanya
 *   menunjuk data dari menunya masing-masing; section tambahan (icon_grid,
 *   cards, logos, stats, testimonials, text_media, banner, articles)
 *   diisi di sini. Lihat App\Models\WebHomeSection::TYPES.
 * - web_home_section_items: item section tambahan (ikon, kartu, logo, ...).
 * - web_features.sort_order & web_faqs.sort_order: urutan item Fitur/FAQ
 *   bisa diatur (sebelumnya Fitur = tanggal dibuat, FAQ = abjad). Diisi
 *   awal mengikuti urutan lama supaya tampilan tidak berubah.
 *
 * Susunan awal beranda di-seed persis seperti sekarang (Hero, Running
 * text, Paket, Fitur, FAQ) dengan judul yang selama ini ditulis mati di
 * view fe-konexa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('web_home_sections')) {
            Schema::create('web_home_sections', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type', 30);
                $table->string('title')->nullable();
                $table->text('subtitle')->nullable();
                $table->string('background_type', 10)->default('none'); // none | color | image | video
                $table->string('background_color', 20)->nullable();
                $table->string('background_image')->nullable();
                $table->string('background_video')->nullable();
                $table->string('text_align', 10)->default('center'); // center | left
                $table->string('cta_text', 60)->nullable();
                $table->string('cta_link', 500)->nullable();
                $table->string('cta2_text', 60)->nullable();
                $table->string('cta2_link', 500)->nullable();
                $table->longText('content')->nullable();
                $table->string('media_image')->nullable();
                $table->string('media_position', 10)->default('right'); // left | right
                $table->unsignedTinyInteger('item_limit')->nullable();
                $table->foreignUuid('web_category_article_id')->nullable()
                    ->constrained('web_category_articles')->nullOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 20)->default('active'); // active | inactive
                $table->timestamps();

                $table->index(['status', 'sort_order']);
            });
        }

        if (! Schema::hasTable('web_home_section_items')) {
            Schema::create('web_home_section_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('web_home_section_id')->constrained('web_home_sections')->cascadeOnDelete();
                $table->string('icon', 60)->nullable();
                $table->string('image')->nullable();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('value', 100)->nullable();
                $table->string('link_text', 60)->nullable();
                $table->string('link_url', 500)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['web_home_section_id', 'sort_order']);
            });
        }

        foreach (['web_features' => 'created_at', 'web_faqs' => 'name'] as $table => $legacyOrder) {
            if (Schema::hasColumn($table, 'sort_order')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedInteger('sort_order')->default(0)->after('status');
            });

            DB::table($table)->orderBy($legacyOrder)->orderBy('id')->pluck('id')
                ->each(fn (string $id, int $index) => DB::table($table)->where('id', $id)->update(['sort_order' => $index + 1]));
        }

        if (DB::table('web_home_sections')->doesntExist()) {
            $now = now();
            $defaults = [
                ['type' => 'hero'],
                ['type' => 'running_text'],
                ['type' => 'packages', 'title' => 'Paket Layanan', 'subtitle' => 'Pilih paket sesuai layanan yang dibutuhkan bisnis Anda. Paket berlaku untuk setiap branch.'],
                ['type' => 'features', 'title' => 'Fitur Unggulan', 'subtitle' => 'Semua yang Anda butuhkan untuk mengelola percakapan WhatsApp bisnis dalam satu platform — dari otomasi berbasis AI sampai manajemen pelanggan yang terintegrasi.'],
                ['type' => 'faq', 'title' => 'FAQs', 'subtitle' => 'Temukan jawaban atas pertanyaan umum seputar chatbot AI, broadcast WhatsApp, CRM, dan paket harga Bizbos.'],
            ];

            DB::table('web_home_sections')->insert(array_map(fn (array $row, int $index) => $row + [
                'id' => (string) Str::uuid(),
                'title' => $row['title'] ?? null,
                'subtitle' => $row['subtitle'] ?? null,
                'sort_order' => $index + 1,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ], $defaults, array_keys($defaults)));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('web_home_section_items');
        Schema::dropIfExists('web_home_sections');

        foreach (['web_features', 'web_faqs'] as $table) {
            if (Schema::hasColumn($table, 'sort_order')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('sort_order'));
            }
        }
    }
};
