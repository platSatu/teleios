<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Superadmin-managed catalog of AI providers a company can pick from
     * on the "AI Bot" tab (App\Http\Controllers\Chat\AiBotController) —
     * replaces the hardcoded AiBotController::PROVIDERS placeholder list
     * that migration 2026_07_31_140700_create_wa_ai_bots_table's own
     * docblock flagged as a follow-up. Deliberately just `name` + status,
     * same minimal shape as App\Models\CategoryApplication — this table
     * only exists to be picked from a dropdown and to gate which
     * providers are currently offered platform-wide (a provider whose
     * API changed or that's being deprecated can be switched to
     * 'inactive' here without deleting any company's existing
     * configuration that already points at it).
     *
     * Seeded with the initial provider lineup below so `php artisan
     * migrate` alone leaves working options in the dropdown — no manual
     * superadmin data entry needed before the AI Bot feature is usable.
     * Idempotent (matched by `name`), same pattern as
     * 2026_08_03_170200_seed_chat_application_menu_catalog.
     */
    public function up(): void
    {
        Schema::create('wa_ai_bot_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('status', 20)->default('active'); // active | inactive
            $table->timestamps();
        });

        foreach (['OpenAI (ChatGPT)', 'Google (Gemini)', 'Anthropic (Claude)'] as $name) {
            $exists = \Illuminate\Support\Facades\DB::table('wa_ai_bot_providers')
                ->where('name', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            \Illuminate\Support\Facades\DB::table('wa_ai_bot_providers')->insert([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_ai_bot_providers');
    }
};
