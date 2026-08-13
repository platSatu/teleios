<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\AiModerationSetting — the superadmin-owned AI
 * "penjaga konten" for Kategori Template & WA Template. Deliberately a
 * SINGLETON table (App\Models\AiModerationSetting::current() always
 * operates on the first/only row, creating it lazily if missing) rather
 * than a catalog like App\Models\WaAiBot: there is exactly one platform-
 * wide moderation policy, not one per company.
 *
 * Reuses the existing App\Models\WaAiBotProvider/WaAiBotModel catalog
 * for provider+model selection (same dropdown source the AI Bot feature
 * already uses) rather than inventing a second one — this row just
 * points at one of those PLUS carries its own `api_key`, since the
 * platform's moderation key is a separate credential from any
 * individual company's AI Bot key (App\Models\WaAiBot.api_configuration).
 *
 * The four block_* toggles map 1:1 to what Chat\CategoryTemplateController
 * and Chat\MessageTemplateController ask App\Services\Moderation\
 * TemplateModerationService to check for; `blocked_keywords` is a
 * supplementary deterministic pre-filter (checked before the AI call,
 * so a known bad word is caught instantly without spending an API call
 * or waiting on a network round-trip) on top of the AI's own judgment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_moderation_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wa_ai_bot_provider_id')
                ->nullable()
                ->constrained('wa_ai_bot_providers')
                ->nullOnDelete();

            $table->foreignUuid('wa_ai_bot_model_id')
                ->nullable()
                ->constrained('wa_ai_bot_models')
                ->nullOnDelete();

            // Cast `encrypted` on the model, same treatment
            // wa_ai_bots.api_configuration already gets — never stored
            // or logged in plain text.
            $table->text('api_key')->nullable();

            $table->boolean('block_pornography')->default(true);
            $table->boolean('block_gambling')->default(true);
            $table->boolean('block_drugs')->default(true);
            $table->boolean('block_negative_language')->default(true);

            // Comma/newline-separated hard-block list — checked BEFORE
            // the AI call. Optional; the four toggles above already
            // cover the requested categories through the AI's own
            // judgment on their own.
            $table->text('blocked_keywords')->nullable();

            // Free-text extra house rules appended to the AI's system
            // prompt (e.g. "jangan izinkan klaim medis berlebihan").
            $table->text('custom_instructions')->nullable();

            // 'active' | 'inactive' — the kill switch. Starts 'inactive'
            // on purpose: a freshly-migrated environment has no provider/
            // model/key configured yet, so there's nothing to actually
            // run until a superadmin sets this up deliberately (see
            // App\Services\Moderation\TemplateModerationService's
            // "unavailable" outcome for what happens while this is off
            // or incomplete).
            $table->string('status', 20)->default('inactive');

            $table->foreignUuid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_moderation_settings');
    }
};
