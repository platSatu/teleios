<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Services\Chat\DeviceDirectory;
use App\Services\Chat\DeviceHealthService;
use App\Services\Company\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exposes App\Services\Chat\DeviceHealthService's scoring to the
 * frontend — one device's own health breakdown (for the Connect Device
 * page), and a company-wide ranking (for "which number should I use for
 * this broadcast"). Device ownership here is checked against the
 * requesting user's own CompanyContext (company_id/branch_office_id)
 * rather than proxied through the Go backend's JWT-based
 * assertOwnership like Chat\ConnectDeviceController — this data comes
 * straight from MySQL (see DeviceDirectory's docblock), so there's no
 * Go API round trip to lean on for the check.
 */
class DeviceHealthController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(
        protected DeviceHealthService $health,
        protected DeviceDirectory $devices,
    ) {
    }

    /**
     * AJAX: one device's health score + signal breakdown — powers a
     * "Kesehatan Nomor" panel alongside the existing Riwayat panel on the
     * Connect Device page.
     */
    public function show(Request $request, string $device): JsonResponse
    {
        $context = $this->companyContext($request);

        if (! $this->ownsDevice($context, $device)) {
            abort(404);
        }

        return response()->json(['health' => $this->health->assess($device)]);
    }

    /**
     * AJAX: every device in the company (branch-scoped for a locked
     * member), ranked healthiest-and-least-busy first — the practical
     * "rotate to a safer number" recommendation for a company about to
     * send a large broadcast, without requiring App\Models\
     * WaMessageSchedule itself to support splitting one broadcast across
     * several devices (a materially bigger change — this is the
     * decision-support half of device rotation, done today; automatic
     * cross-device splitting is a natural next step once this is proven
     * useful).
     */
    public function ranking(Request $request): JsonResponse
    {
        $context = $this->companyContext($request);
        $branchOfficeId = $context->isLockedToBranch() ? $context->branchOffice?->id : null;

        return response()->json([
            'devices' => $this->health->rankDevicesForBroadcast($context->company->id, $branchOfficeId)->values(),
        ]);
    }

    private function ownsDevice(CompanyContext $context, string $deviceId): bool
    {
        $scope = $this->devices->scopeFor($deviceId);

        if ($scope['company_id'] !== $context->company->id) {
            return false;
        }

        if ($context->isLockedToBranch() && $scope['branch_office_id'] !== $context->branchOffice?->id) {
            return false;
        }

        return true;
    }
}
