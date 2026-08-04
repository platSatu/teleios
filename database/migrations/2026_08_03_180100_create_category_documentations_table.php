<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\CategoryDocumentation — groups entries in the
     * public WhatsApp API documentation site (see PublicDocumentationController,
     * route GET /dokumentasi, no login required) into sections like
     * "Autentikasi" or "Kirim Pesan". Superadmin-managed, same catalog
     * pattern as App\Models\CategoryApplication/CategoryHelpCenter — see
     * Superadmin\CategoryDocumentationController
     * (dashboard/superadmin/wa-api-dokumentasi/categories).
     */
    public function up(): void
    {
        Schema::create('category_documentations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_documentations');
    }
};
