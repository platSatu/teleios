<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Three additions to App\Models\WaAiBot (see
     * 2026_07_31_140700_create_wa_ai_bots_table for the original shape):
     *
     *   - wa_ai_bot_provider_id / wa_ai_bot_model_id: proper FKs into the
     *     new superadmin-managed catalog (wa_ai_bot_providers /
     *     wa_ai_bot_models — see those migrations), replacing the old
     *     free-text ai_provider/ai_model columns going forward. Those
     *     two old columns are LEFT IN PLACE (not dropped) rather than
     *     migrated/backfilled — existing rows keep displaying their
     *     original plain-text value even though the form no longer
     *     writes to them, which is simpler and safer than guessing which
     *     catalog row an arbitrary free-text value like "GPT4" or
     *     "chatgpt-4" was supposed to mean.
     *   - branch_office_id: same branch-scoping column/rule as every
     *     other per-branch feature in this app (see
     *     App\Services\Company\CompanyContextResolver) — a non-owner
     *     member's AI Bot configs are limited to their own branch, the
     *     owner sees/manages every branch's. Nullable: a company that
     *     hasn't set up branch offices yet keeps working exactly as
     *     before.
     *   - activation_end_at: pairs with the existing
     *     activation_start_at to make "custom activation time" an actual
     *     start-end WINDOW instead of only ever a start point with no
     *     defined end — matches the toggle's UI, which reveals both a
     *     start and an end field once switched on.
     */
    public function up(): void
    {
        Schema::table('wa_ai_bots', function (Blueprint $table) {
            $table->foreignUuid('wa_ai_bot_provider_id')
                ->nullable()
                ->after('ai_model')
                ->constrained('wa_ai_bot_providers')
                ->nullOnDelete();

            $table->foreignUuid('wa_ai_bot_model_id')
                ->nullable()
                ->after('wa_ai_bot_provider_id')
                ->constrained('wa_ai_bot_models')
                ->nullOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->after('device_id')
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->dateTime('activation_end_at')
                ->nullable()
                ->after('activation_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('wa_ai_bots', function (Blueprint $table) {
            $table->dropColumn('activation_end_at');

            $table->dropForeign(['branch_office_id']);
            $table->dropColumn('branch_office_id');

            $table->dropForeign(['wa_ai_bot_model_id']);
            $table->dropColumn('wa_ai_bot_model_id');

            $table->dropForeign(['wa_ai_bot_provider_id']);
            $table->dropColumn('wa_ai_bot_provider_id');
        });
    }
};
