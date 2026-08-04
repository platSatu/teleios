<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\Company — the "Company" tab on the consolidated
     * dashboard/user/profile page (see User\Profile\ProfileController).
     * One row is created lazily the first time a user fills in the
     * Company tab; nothing here forces a user to have one.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // char(36) uuid FK to users.id. cascadeOnDelete: a company
            // profile has no meaning without its owning user, unlike
            // e.g. wallets which restrict (financial history must
            // survive). Safe to cascade here.
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // System-generated code — 3 uppercase letters + 3 digits
            // (e.g. "ABC123"), never user-supplied. Generated in
            // App\Models\Company::boot() via generateUniqueCompanyId(),
            // which loops until it finds one not already in this column.
            $table->string('company_id', 6)->unique();

            $table->string('name');

            // Derived from `name` in the controller (not here) at
            // create/update time. Still unique at the DB level as a
            // hard backstop against a race between two simultaneous
            // requests picking the same slug.
            $table->string('slug')->unique();

            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
