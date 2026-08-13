<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `group_name` to web_footers — lets several footer rows share one
 * column header (e.g. "Support", "About", "Sales", "Explore"), matching
 * a typical multi-column site footer instead of one flat list of links.
 * Nullable on purpose: a row with no group_name is still valid (rendered
 * by fe-konexa as a loose/ungrouped link) — see App\View\Composers\
 * FooterComposer on the fe-konexa side for how rows are grouped for
 * display. `column_width` (already on this table) is reused as the
 * WIDTH OF THE WHOLE GROUP'S COLUMN now, taken from the first row in
 * each group, rather than a per-link width.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_footers', function (Blueprint $table) {
            $table->string('group_name', 100)->nullable()->after('name');
            $table->index(['group_name', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('web_footers', function (Blueprint $table) {
            $table->dropIndex(['group_name', 'sort_order']);
            $table->dropColumn('group_name');
        });
    }
};
