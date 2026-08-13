<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many of one App\Models\LimitMetric a given App\Models\Package
 * allows — e.g. Package "Paket A" + metric "broadcast_send" + max_value
 * 10000. Deliberately a simple (package_id, limit_metric_id) -> max_value
 * pivot rather than columns bolted onto `packages` directly, so adding a
 * new limitable metric (now or for a future application) never needs a
 * new migration on the packages table itself. A package with no row here
 * for a given metric is treated as unlimited for that metric — see
 * App\Services\PackageLimitService::limitFor().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignUuid('limit_metric_id')->constrained('limit_metrics')->restrictOnDelete();
            $table->unsignedInteger('max_value');
            $table->timestamps();

            $table->unique(['package_id', 'limit_metric_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_limits');
    }
};
