<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaFormIntegration — Chat > Third Party > Google
     * Form. Replaces the old client-side-only "Feedback dari Google Form
     * ke WhatsApp" block that used to live on the API Key page
     * (resources/views/chat/konekdevice/api-key.blade.php): that version
     * had nothing persisted server-side and built a hardcoded message
     * client-side in Apps Script. This table is a real, named,
     * per-company integration config: which device sends the reply,
     * which JSON key in the form's payload holds the recipient's WhatsApp
     * number, and which WA Message Template (App\Models\WaMessageTemplate)
     * is used as the reply body.
     *
     * `type` is deliberately not hardcoded to "google_form" at the schema
     * level — it's just the one value used today — so the same "Third
     * Party" menu/table can grow more integration types later (e.g. a
     * generic webhook, Typeform, etc.) without a new table.
     *
     * `device_id` is a plain string with no FK, same convention as
     * wa_api_keys/wa_message_schedules/wa_ai_bots — WhatsApp devices are
     * entirely owned by the Go backend, this app has no local devices
     * table to reference.
     *
     * `webhook_token` is the public, unguessable path segment a Google
     * Apps Script POSTs to (see App\Http\Controllers\Api\
     * GoogleFormWebhookController) — it IS the auth for that endpoint,
     * same idea as App\Models\WaApiKey's `token`, just delivered via URL
     * instead of a header since Apps Script's onFormSubmit trigger is
     * simplest wired to one fixed POST target.
     */
    public function up(): void
    {
        Schema::create('wa_form_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('type', 30)->default('google_form');

            // Admin-facing label, e.g. "Form Pendaftaran Event" — a
            // company can have several Google Form integrations (one per
            // form), so this is how they tell them apart in the list.
            $table->string('name');

            $table->string('device_id', 36);

            // Nullable: a company can save the integration before a
            // suitable approved template exists yet, same tolerance
            // WaMessageSchedule gives use_template rows.
            $table->foreignUuid('wa_message_template_id')
                ->nullable()
                ->constrained('wa_message_templates')
                ->nullOnDelete();

            // The exact key name (== the Google Form question title, since
            // Apps Script builds the JSON payload from question titles)
            // whose answer is the recipient's WhatsApp number. Matched
            // case-insensitively/trimmed at receive time — see
            // GoogleFormWebhookController::extractTargetNumber().
            $table->string('target_number_field');

            $table->string('webhook_token', 64)->unique();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Observability, same pattern as wa_ai_bots.last_triggered_at
            // / trigger_count — lets the detail page show "yep, this is
            // actually being hit" without joining wa_form_submissions.
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedInteger('trigger_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_form_integrations');
    }
};
