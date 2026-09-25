<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Halaman dinamis fe-konexa di /page/{slug} (Superadmin > Web > Halaman).
 *
 * - type 'document': teks panjang (Markdown sederhana) dengan daftar isi
 *   otomatis -- Kebijakan Privasi, Syarat Affiliate, dll.
 * - type 'landing': disusun dari section yang sama dengan beranda
 *   (web_home_sections.web_page_id = halaman ini; NULL = beranda).
 *
 * show_in_navbar / show_in_footer menentukan di mana link halaman tampil.
 * Syarat & Ketentuan dan Artikel sengaja tetap di modulnya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('web_pages')) {
            Schema::create('web_pages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('slug', 191)->unique();
                $table->string('type', 20)->default('document'); // document | landing
                $table->text('subtitle')->nullable();
                $table->string('hero_image')->nullable();
                $table->longText('content')->nullable();
                $table->string('meta_description', 500)->nullable();
                $table->string('meta_image')->nullable();
                $table->boolean('show_in_navbar')->default(false);
                $table->unsignedInteger('navbar_order')->default(0);
                $table->boolean('show_in_footer')->default(false);
                $table->string('footer_group', 100)->nullable();
                $table->unsignedInteger('footer_order')->default(0);
                $table->string('status', 20)->default('active'); // active | inactive
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('web_home_sections', 'web_page_id')) {
            Schema::table('web_home_sections', function (Blueprint $table) {
                $table->foreignUuid('web_page_id')->nullable()->after('id')
                    ->constrained('web_pages')->cascadeOnDelete();
                $table->index(['web_page_id', 'status', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('web_home_sections', 'web_page_id')) {
            Schema::table('web_home_sections', function (Blueprint $table) {
                $table->dropConstrainedForeignId('web_page_id');
            });
        }

        Schema::dropIfExists('web_pages');
    }
};
