<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\ApplicationMenu — superadmin-managed menu naming
     * per Category Application (each category defines its own set of
     * menu labels, e.g. "Chat" category might have different menu items
     * than "CRM" category). Superadmin-only CRUD, see
     * Superadmin\ApplicationMenuController.
     */
    public function up(): void
    {
        Schema::create('application_menus', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable, same reasoning as category_applications.user_id —
            // this is superadmin-managed catalog data, not tied to one
            // specific user. nullOnDelete so deleting a user doesn't
            // wipe out the menu definition, just detaches it.
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Required: every menu entry belongs to a category. restrictOnDelete
            // (same as packages.category_application_id) so a category
            // with existing menu entries can't be deleted out from under
            // them — has to be cleaned up explicitly first.
            $table->foreignUuid('category_application_id')
                ->constrained('category_applications')
                ->restrictOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_menus');
    }
};
