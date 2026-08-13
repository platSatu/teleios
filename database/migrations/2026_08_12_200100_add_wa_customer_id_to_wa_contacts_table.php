<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links each App\Models\WaContact to the one App\Models\WaCustomer
 * identity it belongs to — see the wa_customers migration's docblock
 * for the overall Fase 0 design. Nullable (and NOT backfilled by this
 * migration itself): existing rows are linked separately by
 * `php artisan crm:backfill-customer-identities` (see
 * App\Console\Commands\BackfillCustomerIdentities), the same "schema
 * change and data migration are separate, explicit steps" split every
 * other feature in this app follows — a schema migration should never
 * silently run a slow, all-rows data backfill inline where a developer
 * can't see it happening or re-run it safely.
 *
 * unique(wa_customer_id) enforces the 1:1 relationship at the database
 * level (MySQL allows any number of NULLs through a unique index, so
 * unlinked legacy rows before the backfill runs are unaffected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_contacts', function (Blueprint $table) {
            $table->foreignUuid('wa_customer_id')
                ->nullable()
                ->after('id')
                ->constrained('wa_customers')
                ->nullOnDelete();

            $table->unique('wa_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('wa_contacts', function (Blueprint $table) {
            $table->dropUnique(['wa_customer_id']);
            $table->dropConstrainedForeignId('wa_customer_id');
        });
    }
};
