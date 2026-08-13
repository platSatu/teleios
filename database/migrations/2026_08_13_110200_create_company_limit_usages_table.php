<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One running counter per (company, branch office, metric, subscription)
 * — the "sudah terpakai berapa dari yang dibeli" row the user asked for
 * ("user membeli 10000 terpakai 5000 itu ada laporannya"). Scoped to a
 * specific App\Models\Subscription (nullable only for the edge case of a
 * company with no active subscription yet) rather than reset on a fixed
 * calendar month, per the user's explicit call: the "acuan" (reference
 * point) is whatever was actually purchased, so a new counter — and a
 * fresh `used_value` starting at 0 — only begins when a NEW subscription
 * becomes active, not on the 1st of every month.
 *
 * `branch_office_id` is nullable: null means the counter is a whole-
 * company aggregate (every branch shares one pool), while a real id
 * scopes it to just that App\Models\BranchOffice, per the user's request
 * to support both company-wide and per-cabang limits. Plain SQL can't
 * put a unique constraint straight on (company_id, branch_office_id,
 * limit_metric_id, subscription_id) and have it actually block a
 * duplicate "whole-company aggregate" row, because both nullable columns
 * can hold multiple NULLs that a unique index treats as distinct values
 * — so `usage_key` below is a NOT NULL column that substitutes a literal
 * placeholder for NULL, and the real uniqueness guarantee lives on IT
 * instead. This is what actually stops two simultaneous first-time
 * reservations for the same combination from both slipping through
 * App\Services\PackageLimitService::reserve()'s `lockForUpdate()` (which
 * only locks rows that already exist — it can't protect the very first
 * INSERT for a combination on its own).
 *
 * `usage_key` is computed in App\Models\CompanyLimitUsage's `saving`
 * event, NOT as a DB-level GENERATED ALWAYS AS column — MySQL/MariaDB
 * reject a generated column whose expression depends on a base column
 * that has a foreign key with an ON DELETE/UPDATE action of CASCADE,
 * SET NULL, or SET DEFAULT (error 1901), and both `branch_office_id`
 * and `subscription_id` below use ->nullOnDelete(). Computing it in PHP
 * sidesteps that restriction entirely while keeping the same NOT NULL +
 * UNIQUE guarantee at the database level.
 *
 * Only meaningful for 'consumable' metrics (see limit_metrics.metric_type)
 * — 'stock' metrics are measured live against the real resource count
 * and never write to `used_value`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_limit_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('branch_office_id')->nullable()
                ->constrained('branch_offices')->nullOnDelete();
            $table->foreignUuid('limit_metric_id')->constrained('limit_metrics')->restrictOnDelete();
            $table->foreignUuid('subscription_id')->nullable()
                ->constrained('subscriptions')->nullOnDelete();
            $table->unsignedInteger('used_value')->default(0);
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'limit_metric_id']);

            // NULL-safe uniqueness guarantee — see the class docblock.
            // "-" can never collide with a real uuid, so this is safe as
            // a stand-in for "no branch"/"no subscription". Value is
            // written by App\Models\CompanyLimitUsage's `saving` event,
            // not by the database (see the class docblock for why).
            $table->string('usage_key', 150)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_limit_usages');
    }
};
