<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaCustomerTag — CRM Roadmap Fase 4 ("Segmentasi &
 * Automation"), per docs/Roadmap_CRM_WhatsApp_Konexa.docx section 2:
 * "tagging/segmen dinamis". This is the per-company tag catalog (e.g.
 * "VIP", "Reseller", "Churn Risk") — see the next migration
 * (wa_customer_tag_customer) for how a tag attaches to a
 * App\Models\WaCustomer, and App\Models\WaCustomerSegment for how tags
 * feed into a dynamic segment's filter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_customer_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name', 50);

            // Hex color for the tag chip/badge — defaulted in
            // App\Models\WaCustomerTag rather than here so a plain
            // find-or-create-by-name (see
            // App\Http\Controllers\Crm\CustomerTagController::attach())
            // never has to think about color.
            $table->string('color', 20)->default('secondary');

            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customer_tags');
    }
};
