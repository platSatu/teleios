<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaCustomerSegment — CRM Roadmap Fase 4's "segmen
 * dinamis". A segment is a SAVED FILTER, not a stored list of members:
 * `filters` (JSON) is compiled into a live query against WaCustomer
 * every time the segment is viewed (see WaCustomerSegment::
 * matchingCustomersQuery()), so membership always reflects current
 * data — a customer who now matches shows up without anyone re-running
 * anything, and one who no longer matches drops out the same way. See
 * that model's docblock for the exact filter keys supported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_customer_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name', 150);
            $table->text('description')->nullable();

            $table->json('filters');

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customer_segments');
    }
};
