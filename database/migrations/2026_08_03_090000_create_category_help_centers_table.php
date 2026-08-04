<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\CategoryHelpCenter — superadmin-managed category
 * catalog for help-center tickets (e.g. "Billing", "Technical", "Akun"),
 * same shape as category_applications: nullable user_id since this is
 * catalog data, not tied to one specific user. See
 * Superadmin\HelpCenters\CategoryHelpCenterController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_help_centers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_help_centers');
    }
};
