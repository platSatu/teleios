<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaCategoryPhoneBook — a company-defined group
     * ("Kelompok") for organizing its Buku Telepon contacts (e.g.
     * "Pelanggan VIP", "Reseller"), used both to filter the phone book
     * and as a pickable group when choosing recipients elsewhere in the
     * Chat feature.
     *
     * Table/column names follow the spec exactly as given rather than
     * this app's usual plural table-name convention, matching the
     * client-supplied schema. `branch_office_id` (not the literal
     * `branch_id` originally described) is used for the actual FK
     * column name to stay consistent with every other company-owned
     * resource in this app (wa_contacts, company_to_users, etc.), all of
     * which point at `branch_offices`.`id` — same "not every company
     * uses branches" nullability rule as those tables.
     */
    public function up(): void
    {
        Schema::create('wa_category_phone_book', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            // A company shouldn't end up with two groups named identically
            // (confusing in every picker that lists these) — scoped per
            // company, not globally.
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_category_phone_book');
    }
};
