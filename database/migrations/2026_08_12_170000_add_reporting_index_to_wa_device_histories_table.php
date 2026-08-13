<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `wa_device_histories` itself is owned/created by the Go backend
 * (g_backend's WaConnectDeviceService, via GORM AutoMigrate — see that
 * repo's cmd/server/main.go and models.WaDeviceHistory) — same
 * cross-service-shared-table situation as wa_devices (see
 * 2026_08_05_120000_add_company_branch_fields_to_wa_devices_table.php's
 * docblock for the full reasoning, including why there's no FK here
 * either).
 *
 * GORM's AutoMigrate already gives this table a plain index on
 * device_id — this adds the composite (device_id, created_at) index
 * App\Services\Chat\DeviceHealthService actually needs: every one of its
 * queries is "this device's history events in the last N days", and a
 * single-column device_id index still has to scan every historical row
 * for a long-lived device before filtering by date, unlike a composite
 * index which can seek straight to the date range.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard, not an assumption failure — see the referenced
        // migration's docblock: this table only exists once the Go
        // backend has started at least once and run its own AutoMigrate.
        if (! Schema::hasTable('wa_device_histories')) {
            return;
        }

        if ($this->indexExists('wa_device_histories', 'wa_device_histories_device_id_created_at_index')) {
            return;
        }

        Schema::table('wa_device_histories', function (Blueprint $table) {
            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_device_histories')) {
            return;
        }

        Schema::table('wa_device_histories', function (Blueprint $table) {
            $table->dropIndex(['device_id', 'created_at']);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        $rows = $connection->select(
            'SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$connection->getDatabaseName(), $table, $indexName]
        );

        return (int) ($rows[0]->cnt ?? 0) > 0;
    }
};
