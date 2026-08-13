<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plain many-to-many pivot between App\Models\WaCustomer and
 * App\Models\WaCustomerTag (WaCustomer::tags() / WaCustomerTag::
 * customers()) — no Eloquent model of its own, no surrogate id, same as
 * every other pure pivot table Laravel's belongsToMany expects. The
 * composite primary key is the de-dupe guard: attaching an already-
 * attached tag is a silent no-op instead of a duplicate row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_customer_tag_customer', function (Blueprint $table) {
            $table->foreignUuid('wa_customer_id')
                ->constrained('wa_customers')
                ->cascadeOnDelete();

            $table->foreignUuid('wa_customer_tag_id')
                ->constrained('wa_customer_tags')
                ->cascadeOnDelete();

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Fires App\Services\Crm\CustomerAutomationService::
            // fireTagAdded() the moment a tag is attached (see
            // App\Http\Controllers\Crm\CustomerTagController::attach())
            // — this timestamp is what a 'tag_added' automation rule's
            // audit trail (wa_customer_automation_logs) ultimately traces
            // back to.
            $table->timestamp('created_at')->nullable();

            $table->primary(['wa_customer_id', 'wa_customer_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customer_tag_customer');
    }
};
