<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog of everything a Package can put a numeric ceiling on — e.g.
 * "broadcast_send" (how many broadcast messages a company may send in
 * one subscription period) or "device_count" (how many WhatsApp devices
 * a company may connect at once). Deliberately NOT hardcoded to Chat/
 * Konexa: `category_application_id` is nullable on purpose, so a metric
 * can either belong to one specific App\Models\CategoryApplication (a
 * "product") or be left null to mean "any product may reuse this same
 * metric key" — the whole point being that when a future application
 * beyond WhatsApp/Konexa is added, it can either register its own
 * metrics here or reuse an existing one, without this table (or
 * App\Models\PackageLimit / App\Models\CompanyLimitUsage below) needing
 * any structural change.
 *
 * `metric_type` decides how App\Services\PackageLimitService measures
 * usage against a limit:
 *   - 'consumable': usage accumulates over a subscription period (e.g.
 *     broadcast sends) and is tracked in company_limit_usages.used_value,
 *     reset whenever a new App\Models\Subscription becomes the active one.
 *   - 'stock': usage is just "how many of this resource exist right now"
 *     (e.g. contacts, devices) — checked live against the real count each
 *     time (WaPhoneBook::count(), Go backend's device list, ...) rather
 *     than a separately-maintained counter, so it can never drift out of
 *     sync with reality.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('limit_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_application_id')->nullable()
                ->constrained('category_applications')->nullOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->string('metric_type')->default('consumable');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['category_application_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('limit_metrics');
    }
};
