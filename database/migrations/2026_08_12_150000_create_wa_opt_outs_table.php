<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaOptOut — a company-wide "do not message this
 * number again" list, the core anti-ban/compliance safeguard for
 * broadcast sending (see App\Services\Chat\BroadcastOptOutService).
 * Company-wide (not per-device) on purpose: if a customer replies STOP
 * to one device, the same company blasting them again from a second
 * device would defeat the whole point — it's the relationship with the
 * COMPANY the customer is opting out of, not one specific phone number
 * they happened to receive a broadcast from.
 *
 * Checked by App\Jobs\SendScheduledWaMessage before every broadcast
 * send (never for Inbox replies — an agent replying to an inbound
 * message from an opted-out customer is still a normal 1:1 conversation,
 * not a broadcast).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_opt_outs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Digits only, no leading '+' — same normalization
            // App\Models\WaContact::normalizePhone() already uses.
            $table->string('phone', 32);

            // 'wa_reply' (customer texted a STOP-style keyword, see
            // WaIncomingMessageWebhookController::tryOptOutKeyword()) |
            // 'manual' (added by a team member from the opt-out list).
            $table->string('source', 20)->default('wa_reply');

            $table->text('note')->nullable();

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('opted_out_at')->useCurrent();

            $table->timestamps();

            // Opting out twice (e.g. two STOP replies) must stay a no-op,
            // not a duplicate row — App\Services\Chat\BroadcastOptOutService
            // ::optOut() relies on this via updateOrCreate().
            $table->unique(['company_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_opt_outs');
    }
};
