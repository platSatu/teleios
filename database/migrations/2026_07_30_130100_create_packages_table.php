<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable: same reasoning as category_applications.user_id —
            // a package doesn't have to be tied to one specific user.
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Required: every package belongs to a category. restrictOnDelete
            // so a category with existing packages can't be deleted out
            // from under them — has to be cleaned up explicitly first.
            $table->foreignUuid('category_application_id')
                ->constrained('category_applications')
                ->restrictOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration'); // in days, e.g. 30
            $table->decimal('price', 12, 2);
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
