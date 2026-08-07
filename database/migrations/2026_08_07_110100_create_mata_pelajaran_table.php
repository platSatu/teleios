<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\MataPelajaran — a subject/course offered at ONE
 * specific cabang. Deliberately scoped to branch_office_id, not just
 * company_id, per the spec: "1 cabang punya mata pelajaran yang tidak
 * sama dengan cabang lainnya meskipun di dalam satu perusahaan" — two
 * branches under the same company each maintain their own subject
 * catalog, they don't share one company-wide list.
 *
 * company_id is still stored directly (not just reachable via
 * branch_office_id -> branch_offices.company_id) — same redundant-but-
 * convenient pattern already used by wa_phone_book, so company-wide
 * queries (e.g. a superadmin view) don't need an extra join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('branch_office_id')->constrained('branch_offices')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            // Same subject name can exist at two different branches (each
            // maintains its own catalog) but not duplicated within one.
            $table->unique(['branch_office_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_pelajaran');
    }
};
