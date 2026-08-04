<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turns App\Models\ApplicationMenu from a plain display-only catalog
     * ("name" + "description" shown in a settings table, never actually
     * consumed anywhere) into something the sidebar can render and
     * enforce access on:
     *
     * - `route_name` — the Laravel route name this entry represents
     *   (e.g. "chat.message-auto-replies.index"). Null for entries that
     *   are just a grouping label with no page of its own (e.g. a
     *   "Pesan" heading that only exists to nest real items under it).
     * - `parent_id` — self-referencing, lets a flat catalog table
     *   represent the sidebar's actual nesting (Chat > Pengaturan >
     *   Pesan > Auto Reply) instead of every entry being top-level.
     * - `icon` — remixicon/unicon class string, shown next to the label;
     *   optional since sub-items in the current sidebar design don't all
     *   have their own icon.
     * - `sort_order` — plain integer, lets superadmin control display
     *   order without relying on created_at/name ordering.
     *
     * See App\Http\Controllers\Superadmin\ApplicationMenuController
     * (catalog CRUD) and resources/views/layouts/partials/menu.blade.php
     * (where the tree actually gets rendered, filtered by
     * App\Models\CompanyRoleMenu for the logged-in user's resolved role).
     */
    public function up(): void
    {
        Schema::table('application_menus', function (Blueprint $table) {
            $table->string('route_name')->nullable()->after('name');
            $table->string('icon')->nullable()->after('route_name');
            $table->unsignedInteger('sort_order')->default(0)->after('icon');

            $table->foreignUuid('parent_id')
                ->nullable()
                ->after('category_application_id')
                ->constrained('application_menus')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('application_menus', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['route_name', 'icon', 'sort_order']);
        });
    }
};
