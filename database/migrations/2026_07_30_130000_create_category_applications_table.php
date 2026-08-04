<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable: a category doesn't have to belong to a specific
            // user — superadmin-managed catalog data is often global.
            // nullOnDelete rather than cascade: deleting a user shouldn't
            // silently delete catalog data, just detach it.
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
        Schema::dropIfExists('category_applications');
    }
};
