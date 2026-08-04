<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\Role. Two role names shouldn't coexist under the
     * same guard, hence the composite unique on (name, guard_name)
     * rather than name alone.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
