<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaFormSubmission — one row per Google Form
     * response GoogleFormWebhookController receives for a given
     * App\Models\WaFormIntegration, success or failure. Exists purely for
     * visibility: without this, a company has no way to tell "did my
     * Google Form actually send anything" short of checking WhatsApp
     * itself. Shown as a recent-submissions table on the integration's
     * detail page (Chat > Third Party > Google Form > [integration]).
     *
     * `payload` keeps the raw JSON Apps Script posted — lets a company
     * confirm the field name they typed into `target_number_field`
     * actually matches what their form sends, without needing server log
     * access.
     */
    public function up(): void
    {
        Schema::create('wa_form_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wa_form_integration_id')
                ->constrained('wa_form_integrations')
                ->cascadeOnDelete();

            $table->json('payload');

            $table->string('target_number')->nullable();

            $table->text('message_sent')->nullable();

            $table->string('status', 20); // sent | failed

            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_form_submissions');
    }
};
