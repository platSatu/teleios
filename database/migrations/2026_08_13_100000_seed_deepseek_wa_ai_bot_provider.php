<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds "DeepSeek" to the App\Models\WaAiBotProvider catalog — the 4th
 * driver App\Services\AiBot\AiProviderClientResolver knows how to call
 * (see App\Services\AiBot\DeepSeekClient), requested alongside the new
 * superadmin-managed content-moderation AI (App\Models\
 * AiModerationSetting) but seeded into the same shared provider catalog
 * the AI Bot feature already uses — a company's own AI Bot can pick
 * DeepSeek too, not just the moderation engine. Idempotent, same
 * pattern as 2026_08_05_130000_create_wa_ai_bot_providers_table's own
 * seed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $providerId = DB::table('wa_ai_bot_providers')->where('driver', 'deepseek')->value('id');

        if (! $providerId) {
            $providerId = (string) Str::uuid();

            DB::table('wa_ai_bot_providers')->insert([
                'id' => $providerId,
                'name' => 'DeepSeek',
                'driver' => 'deepseek',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // A couple of starter models so the dropdown isn't empty the
        // moment this migration runs — same reasoning as the Gemini
        // model backfill in 2026_08_05_150000.
        foreach (['deepseek-chat', 'deepseek-reasoner'] as $modelName) {
            $exists = DB::table('wa_ai_bot_models')
                ->where('wa_ai_bot_provider_id', $providerId)
                ->where('name', $modelName)
                ->exists();

            if (! $exists) {
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
        DB::table('wa_ai_bot_providers')->where('driver', 'deepseek')->delete();
    }
};
