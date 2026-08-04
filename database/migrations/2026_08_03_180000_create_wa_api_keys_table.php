<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaApiKey — lets a third party send WhatsApp
     * messages through one specific connected device, without ever
     * logging into this dashboard. See:
     *
     * - App\Http\Controllers\Chat\WaApiKeyController — where the company
     *   owner (or a member with access to the Device page) generates and
     *   regenerates a device's token/secret_key.
     * - App\Http\Middleware\VerifyWaApiKey — how a third party's request
     *   is authenticated against this table.
     * - App\Http\Controllers\Api\WaApiSendMessageController — the one
     *   thing a third party can actually do with these credentials right
     *   now: send a message (e.g. as a notification channel). Deliberately
     *   NOT exposing read access (chat history, device status, etc.) yet —
     *   least privilege until there's a real need for more.
     *
     * `device_id` is a plain string with NO foreign key — this app has no
     * local `devices` table at all (WhatsApp devices are entirely managed
     * by the Go backend; `device_id` is just an opaque string returned
     * from Go's API), same convention already used by wa_message_auto_replies,
     * wa_message_schedules, wa_ai_bots, etc.
     */
    public function up(): void
    {
        Schema::create('wa_api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('device_id', 36);

            // Cosmetic only (e.g. the device's phone number at the time
            // the key was generated) — shown back in the UI/docs so
            // "which device is this key for" doesn't require cross-
            // referencing the Go backend's device list. Never used for
            // any actual auth/matching logic.
            $table->string('device_label')->nullable();

            // Recorded at generation time (this app's own public URL,
            // config('app.url')) — what a third party should point their
            // requests at. Not regenerated when the token/secret are
            // (it's not a secret), but re-stamped if the key is
            // regenerated, in case the app's domain changed since.
            $table->string('api_host');

            $table->string('token', 64)->unique();
            $table->string('secret_key', 64);

            $table->string('status', 20)->default('active'); // active | inactive

            // Updated by VerifyWaApiKey on every successful third-party
            // request — lets the owner see "yep, this key is actually
            // being used" instead of it being a black box.
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            // One API key set per device per company — regenerating
            // replaces the token/secret on this same row rather than
            // piling up old ones.
            $table->unique(['company_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_api_keys');
    }
};
