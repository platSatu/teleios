<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\BranchOffice — a branch/cabang belonging to a
     * company (see App\Models\Company).
     */
    public function up(): void
    {
        Schema::create('branch_offices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // char(36) uuid FK to companies.id.
            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name');

            // Derived from `name` at create/update time. Unique at the
            // DB level as a backstop against a race between two
            // simultaneous requests picking the same slug.
            $table->string('slug')->unique();

            $table->text('description')->nullable();
            $table->string('address')->nullable();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_offices');
    }
};
