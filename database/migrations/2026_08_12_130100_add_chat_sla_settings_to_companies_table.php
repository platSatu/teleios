<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company override for how many minutes a WaConversation is allowed
 * to sit before it counts as an SLA breach — both nullable, meaning
 * "use the platform default" (see App\Services\Chat\ConversationService
 * ::DEFAULT_FIRST_RESPONSE_MINUTES/DEFAULT_RESOLUTION_MINUTES). Kept as
 * plain columns on `companies` rather than another row in the generic
 * App\Models\Setting table, since Setting is a global (not per-company)
 * key-value store — these two values need to vary per tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('chat_sla_first_response_minutes')->nullable()->after('status');
            $table->unsignedInteger('chat_sla_resolution_minutes')->nullable()->after('chat_sla_first_response_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['chat_sla_first_response_minutes', 'chat_sla_resolution_minutes']);
        });
    }
};
