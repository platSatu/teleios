<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company override for how many broadcast messages one WhatsApp
 * device is allowed to send per minute — nullable, meaning "use the
 * platform default" (see App\Services\Chat\BroadcastThrottleService::
 * DEFAULT_MAX_PER_MINUTE). This is enforced at actual send time
 * (App\Jobs\SendScheduledWaMessage), on top of — not instead of — the
 * randomized per-recipient stagger App\Console\Commands\
 * DispatchDueWaMessageSchedules already applies at dispatch time: the
 * stagger spaces out one schedule's own recipient list, but several
 * schedules on the same device becoming due in the same minute (or a
 * slow queue catching up on a backlog) could still burst past it — this
 * is the hard per-device ceiling that holds regardless of how many
 * schedules/queue workers are involved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('chat_broadcast_max_per_minute')->nullable()->after('chat_sla_resolution_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('chat_broadcast_max_per_minute');
        });
    }
};
