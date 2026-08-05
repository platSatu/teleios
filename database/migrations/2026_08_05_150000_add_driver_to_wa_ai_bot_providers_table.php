<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `driver` is the stable, code-facing identifier (App\Services\AiBot\
 * AiReplyGenerator switches on this) — deliberately separate from
 * `name`, which is just the display label a superadmin can freely
 * rename ("OpenAI (ChatGPT)" -> "ChatGPT" etc.) without breaking which
 * HTTP client actually gets used. See App\Services\AiBot\Contracts\
 * AiProviderClient and its three implementations (Gemini/OpenAI/
 * Anthropic).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wa_ai_bot_providers', 'driver')) {
            Schema::table('wa_ai_bot_providers', function (Blueprint $table) {
                $table->string('driver', 20)->nullable()->after('name');
            });
        }

        // Backfill the three providers seeded in
        // 2026_08_05_130000_create_wa_ai_bot_providers_table.php by
        // matching their (fixed, known) names — idempotent, only ever
        // touches rows that still have driver=NULL.
        $map = [
            'OpenAI (ChatGPT)' => 'openai',
            'Google (Gemini)' => 'gemini',
            'Anthropic (Claude)' => 'anthropic',
        ];

        foreach ($map as $name => $driver) {
            DB::table('wa_ai_bot_providers')
                ->where('name', $name)
                ->whereNull('driver')
                ->update(['driver' => $driver]);
        }

        // Gemini's 1.x model line is being retired (2.5 series already
        // scheduled to shut down Oct 2026) — mark the stale seed models
        // inactive rather than deleting them, so any WaAiBot already
        // pointing at one doesn't lose its catalog reference, it just
        // can't be newly selected. Add the current free-tier models.
        $geminiProviderId = DB::table('wa_ai_bot_providers')
            ->where('driver', 'gemini')
            ->value('id');

        if ($geminiProviderId) {
            DB::table('wa_ai_bot_models')
                ->where('wa_ai_bot_provider_id', $geminiProviderId)
                ->whereIn('name', ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro'])
                ->update(['status' => 'inactive']);

            foreach (['gemini-3.5-flash', 'gemini-3.5-flash-lite'] as $modelName) {
                $exists = DB::table('wa_ai_bot_models')
                    ->where('wa_ai_bot_provider_id', $geminiProviderId)
                    ->where('name', $modelName)
                    ->exists();

                if (! $exists) {
                    DB::table('wa_ai_bot_models')->insert([
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'wa_ai_bot_provider_id' => $geminiProviderId,
                        'name' => $modelName,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wa_ai_bot_providers', 'driver')) {
            Schema::table('wa_ai_bot_providers', function (Blueprint $table) {
                $table->dropColumn('driver');
            });
        }
    }
};
