<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\CategoryPackage — an older, separate model from
     * App\Models\CategoryApplication (2026_07_30_130000). Both are kept
     * per this project's "don't delete old code, build alongside it"
     * convention; this migration only covers CategoryPackage's own
     * fillable columns (user_id, name, description, status) — its
     * package() relation references a 'packages_id' column that no
     * longer exists on the current packages table and is left as-is,
     * untouched, matching that same convention.
     */
    public function up(): void
    {
        Schema::create('category_packages', function (Blueprint $table) {
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
        Schema::dropIfExists('category_packages');
    }
};
