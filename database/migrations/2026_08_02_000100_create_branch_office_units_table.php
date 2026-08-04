<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\BranchOfficeUnit — a unit/divisi belonging to a
     * BranchOffice (see App\Models\BranchOffice).
     */
    public function up(): void
    {
        Schema::create('branch_office_units', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // char(36) uuid FK to branch_offices.id.
            $table->foreignUuid('branch_office_id')
                ->constrained('branch_offices')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_office_units');
    }
};
