<?php

namespace App\Services\Chat;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolves which Company/BranchOffice a WhatsApp device belongs to.
 *
 * wa_devices is owned/created by the Go backend (GORM AutoMigrate, see
 * g_backend's cmd/server/main.go) but lives in this same "teleios" MySQL
 * database — Laravel has no Eloquent model for it (same reasoning as
 * 2026_08_05_120000_add_company_branch_fields_to_wa_devices_table.php's
 * docblock), so this is a plain read-only query builder call rather than
 * an Eloquent relation. Never writes to this table.
 *
 * Extracted out of App\Services\Chat\ConversationService (its original
 * home) once App\Services\Chat\BroadcastOptOutService/
 * BroadcastThrottleService needed the exact same lookup — both are, like
 * ConversationService, invoked from server-to-server webhooks and queued
 * jobs with no logged-in user/session to resolve company scope from any
 * other way.
 */
class DeviceDirectory
{
    /**
     * How long a device -> (company_id, branch_office_id) lookup is
     * cached. This mapping is written once when a device is linked and
     * essentially never changes afterwards, so a short cache saves a
     * cross-table lookup on every single incoming/outgoing WhatsApp
     * message without risking a stale value for long if it ever did
     * change (e.g. a device reassigned to another branch).
     */
    private const CACHE_TTL = 60;

    /**
     * @return array{company_id: ?string, branch_office_id: ?string}
     */
    public function scopeFor(string $deviceId): array
    {
        return Cache::remember("wa-device-scope:{$deviceId}", self::CACHE_TTL, function () use ($deviceId) {
            $device = DB::table('wa_devices')->where('id', $deviceId)->first(['company_id', 'branch_office_id']);

            if (! $device) {
                Log::warning('device-directory: device not found while resolving company scope', ['device_id' => $deviceId]);

                return ['company_id' => null, 'branch_office_id' => null];
            }

            return [
                'company_id' => $device->company_id,
                'branch_office_id' => $device->branch_office_id,
            ];
        });
    }

    /** Convenience shortcut when only the company is needed. */
    public function companyFor(string $deviceId): ?string
    {
        return $this->scopeFor($deviceId)['company_id'];
    }

    /**
     * Every device belonging to a company (optionally narrowed to one
     * branch) — powers App\Services\Chat\DeviceHealthService's
     * broadcast-device ranking, which needs to compare ALL of a
     * company's devices against each other, not resolve one at a time.
     * Not cached (unlike scopeFor()): this is only ever called for a
     * one-off ranking view, not on the hot path of every incoming
     * message.
     *
     * @return Collection<int, object{id: string, phone_number: ?string, status: string, connected_at: ?string}>
     */
    public function devicesForCompany(string $companyId, ?string $branchOfficeId = null): Collection
    {
        return DB::table('wa_devices')
            ->where('company_id', $companyId)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->orderBy('created_at')
            ->get(['id', 'phone_number', 'status', 'connected_at']);
    }
}
