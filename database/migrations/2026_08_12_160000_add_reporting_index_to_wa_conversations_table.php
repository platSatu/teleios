<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Services\Chat\ChatReportingService's date-range queries
 * (response/resolution time averages, per-agent performance) — every one
 * of them filters "this company's conversations opened between two
 * dates", which wa_conversations' existing indexes (company_id+status,
 * assigned_to+status) don't cover efficiently on their own; without this,
 * each report query would fall back to scanning every row for the
 * company regardless of how narrow the date range requested is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->index(['company_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'opened_at']);
        });
    }
};
