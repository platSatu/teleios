<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company CSAT (Customer Satisfaction) survey settings — Fitur #7.
 * Off by default: a company opts in explicitly, since auto-sending an
 * extra WhatsApp message to every customer the moment their conversation
 * closes is a behavior change a company should choose, not something
 * silently switched on. `csat_question` is nullable — null means "use
 * App\Services\Chat\CsatSurveyService::DEFAULT_QUESTION" rather than
 * duplicating that default text into every company row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('csat_enabled')->default(false)->after('chat_broadcast_max_per_minute');
            $table->string('csat_question', 255)->nullable()->after('csat_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['csat_enabled', 'csat_question']);
        });
    }
};
