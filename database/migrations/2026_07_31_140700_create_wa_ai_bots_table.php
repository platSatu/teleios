<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaAiBot — one AI-responder configuration per
     * connected device. ai_provider/ai_model are plain strings for now
     * (a fixed placeholder list in the form) — per the user's own note,
     * the real provider/model catalog will be superadmin-managed later,
     * which is a separate follow-up feature, not built here.
     *
     * api_configuration is `text` and cast `encrypted` on the model
     * (see App\Models\WaAiBot) since it holds a tenant's own AI provider
     * API key/config — encrypted at rest, matching how any other
     * per-tenant secret should be handled.
     *
     * attach_file_path/attach_file_original_name were added to the
     * original spec's single `attach_file`: one column for the stored
     * path, one for the original filename to display back to the user
     * (storing only a disk path loses the human-readable name).
     * activation_start_at was added to pair with the
     * custom_activation_time toggle — a "custom activation time" toggle
     * needs an actual time/date field to hold that value.
     */
    public function up(): void
    {
        Schema::create('wa_ai_bots', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Only devices currently connected are offered in the
            // form's dropdown (checked live against the Go backend),
            // but the column itself is required — a bot config always
            // belongs to some device.
            $table->string('device_id', 36);

            $table->string('ai_provider', 50);
            $table->string('ai_model', 100)->nullable();

            $table->string('attach_file_path')->nullable();
            $table->string('attach_file_original_name')->nullable();

            $table->text('api_configuration')->nullable();
            $table->text('ai_behaviour_prompt')->nullable();

            $table->boolean('active_bot_immediately')->default(false);
            $table->boolean('custom_activation_time')->default(false);
            $table->dateTime('activation_start_at')->nullable();

            $table->string('status', 20)->default('inactive'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_ai_bots');
    }
};
