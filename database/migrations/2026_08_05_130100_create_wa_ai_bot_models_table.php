<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Superadmin-managed catalog of models under each
     * wa_ai_bot_providers row (see that migration's docblock) — e.g.
     * "OpenAI (ChatGPT)" -> gpt-4o / gpt-4-turbo / gpt-3.5-turbo,
     * "Google (Gemini)" -> gemini-1.5-pro / gemini-1.5-flash. The AI
     * Bot form's Model dropdown is filtered to the selected Provider's
     * models only, same dependent-dropdown pattern as User\Profile\
     * CompanyRoleMenuController's Category Application -> Application
     * Menu picker.
     *
     * Seeded per provider below, matched by (wa_ai_bot_provider_id,
     * name) so re-running this migration (or a fresh install where it
     * already ran) never creates duplicates — same idempotent pattern
     * as 2026_08_03_170200_seed_chat_application_menu_catalog. Silently
     * seeds nothing for a provider name that isn't found (e.g. this
     * migration ran before the providers migration for some reason) —
     * a safe no-op rather than a hard failure, since a superadmin can
     * always add models by hand afterwards from the "AI Bot Models"
     * catalog screen.
     */
    public function up(): void
    {
        Schema::create('wa_ai_bot_models', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wa_ai_bot_provider_id')
                ->constrained('wa_ai_bot_providers')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('status', 20)->default('active'); // active | inactive
            $table->timestamps();
        });

        $catalog = [
            'OpenAI (ChatGPT)' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo', 'o1', 'o1-mini'],
            'Google (Gemini)' => ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro'],
            'Anthropic (Claude)' => ['claude-3-5-sonnet-20241022', 'claude-3-opus-20240229', 'claude-3-haiku-20240307'],
        ];

        foreach ($catalog as $providerName => $models) {
            $providerId = DB::table('wa_ai_bot_providers')
                ->where('name', $providerName)
                ->value('id');

            if (! $providerId) {
                continue;
            }

            foreach ($models as $modelName) {
                $exists = DB::table('wa_ai_bot_models')
                    ->where('wa_ai_bot_provider_id', $providerId)
                    ->where('name', $modelName)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('wa_ai_bot_models')->insert([
                    'id' => (string) Str::uuid(),
                    'wa_ai_bot_provider_id' => $providerId,
                    'name' => $modelName,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_ai_bot_models');
    }
};
