<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same link as the wa_contacts migration right before this one, for
 * App\Models\WaPhoneBook — see wa_customers' migration docblock for the
 * overall Fase 0 design and App\Console\Commands\
 * BackfillCustomerIdentities for how existing rows get linked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_phone_book', function (Blueprint $table) {
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
        Schema::table('wa_phone_book', function (Blueprint $table) {
            $table->dropUnique(['wa_customer_id']);
            $table->dropConstrainedForeignId('wa_customer_id');
        });
    }
};
