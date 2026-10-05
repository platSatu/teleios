<?php

namespace App\Http\Controllers\Chat;

use App\Exceptions\PackageLimitExceededException;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Services\Chat\ConnectDeviceService;
use App\Services\Chat\DeviceDirectory;
use App\Services\Company\CompanyContext;
use App\Services\PackageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Handles connecting and managing the logged-in user's WhatsApp devices
 * (QR pairing via the Go backend / whatsmeow). A user can own several
 * devices at once, shown as a data table; pairing a new one (or an
 * existing disconnected one) happens in a modal that polls for the QR
 * code and the resulting connection status. This is intentionally
 * separate from InboxController, which owns the chat UI for one already-
 * connected device.
 */
class ConnectDeviceController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(
        protected ConnectDeviceService $connectDeviceService,
        protected PackageLimitService $packageLimits,
        protected DeviceDirectory $deviceDirectory,
    ) {}

    /**
     * Show the device list page.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $jwt = session('golang_jwt_token');

        if (! $jwt) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['golang' => 'Sesi WhatsApp tidak ditemukan, silakan login ulang.']);
        }

        try {
            $devices = $this->devicesOfActiveBranch($request, $this->connectDeviceService->listDevices($jwt));
        } catch (Throwable $e) {
            report($e);
            $devices = [];
        }

        return view('chat.konekdevice.konekdevice', [
            'devices' => $devices,
        ]);
    }

    /**
     * AJAX: polled by the table to keep every device's status (and the
     * blinking connected/disconnected dot) fresh without a full reload.
     */
    public function list(Request $request): JsonResponse
    {
        return $this->safeJson(fn (string $jwt) => [
            'devices' => $this->devicesOfActiveBranch($request, $this->connectDeviceService->listDevices($jwt)),
        ]);
    }

    /**
     * AJAX: register a new device and start pairing it. The frontend
     * opens a modal with the returned QR code right after this call.
     *
     * Package quota guard: lihat deviceLimitError().
     */
    public function add(Request $request): JsonResponse
    {
        $jwt = session('golang_jwt_token');

        if (! $jwt) {
            return response()->json(['error' => 'Sesi WhatsApp tidak ditemukan.'], 401);
        }

        [$context, $branch] = $this->activeContext($request);

        if ($error = $this->deviceLimitError($context, $branch, $jwt)) {
            return $error;
        }

        return $this->safeJson(function (string $jwt) use ($context, $branch) {
            $result = $this->connectDeviceService->addDevice($jwt);

            if ($context && $branch && ! empty($result['device_id'])) {
                $this->deviceDirectory->assignBranchIfMissing($result['device_id'], $context->company->id, $branch->id);
            }

            return $result;
        });
    }

    /**
     * AJAX: polled while the pairing modal is open, to detect a
     * successful scan and pick up a refreshed QR code (WhatsApp rotates
     * it roughly every 20 seconds until scanned).
     */
    public function status(string $device): JsonResponse
    {
        return $this->safeJson(fn (string $jwt) => $this->connectDeviceService->status($jwt, $device));
    }

    /**
     * AJAX: request a fresh QR code for a device the user already owns
     * (typically one that's currently disconnected).
     */
    public function reconnect(Request $request, string $device): JsonResponse
    {
        // Menyambungkan ulang device lama juga memakan jatah -- tanpa ini
        // kuota bisa diakali dengan reconnect device putus satu per satu.
        if ($jwt = session('golang_jwt_token')) {
            [$context, $branch] = $this->activeContext($request);

            if ($error = $this->deviceLimitError($context, $branch, $jwt, $device)) {
                return $error;
            }
        }

        return $this->safeJson(fn (string $jwt) => $this->connectDeviceService->reconnect($jwt, $device));
    }

    /**
     * AJAX: log one device out of WhatsApp.
     */
    public function disconnect(string $device): JsonResponse
    {
        return $this->safeJson(fn (string $jwt) => $this->connectDeviceService->disconnect($jwt, $device));
    }

    /**
     * AJAX: one device's connection history (connected/disconnected/
     * logged out/reconnect attempts) — powers the "Riwayat" panel on the
     * Device list, so a user can see why their device dropped instead of
     * just its current status.
     */
    public function history(string $device): JsonResponse
    {
        return $this->safeJson(fn (string $jwt) => [
            'history' => $this->connectDeviceService->history($jwt, $device),
        ]);
    }

    /**
     * Run a callback that needs the Golang JWT, translating a missing
     * session or an upstream failure into a consistent JSON error
     * response instead of leaking exceptions to the frontend.
     */
    /**
     * Owner / member tingkat company hanya melihat device milik branch
     * yang sedang dibuka (menu & paket berlaku per branch). Member yang
     * terkunci ke satu branch sudah difilter backend Go sendiri.
     *
     * @param  array<int, array<string, mixed>>  $devices
     * @return array<int, array<string, mixed>>
     */
    /**
     * Company context + branch aktif, best-effort: controller ini aslinya
     * hanya berbasis sesi/JWT, jadi kalau context tidak bisa di-resolve
     * guard kuota tidak berlaku (fail open, sama seperti PackageLimitService).
     *
     * @return array{0: ?CompanyContext, 1: ?BranchOffice}
     */
    protected function activeContext(Request $request): array
    {
        try {
            $context = $this->companyContext($request);
        } catch (Throwable $e) {
            $context = null;
        }

        return [$context, $context?->activeBranch()];
    }

    /**
     * Kuota "device_count" (metric 'stock'): yang dihitung hanya device
     * yang SEDANG TERHUBUNG di branch aktif (perbaikan 6 Oktober 2026 --
     * dulu semua baris ikut terhitung, termasuk QR yang tidak jadi di-scan,
     * sehingga device baru ditolak padahal yang terhubung belum penuh).
     * Null = boleh lanjut.
     */
    protected function deviceLimitError(?CompanyContext $context, ?BranchOffice $branch, string $jwt, ?string $exceptDeviceId = null): ?JsonResponse
    {
        if (! $context) {
            return null;
        }

        // Device selalu milik satu branch (paket & kuota berlaku per branch).
        if (! $branch) {
            return response()->json(['error' => 'Buat branch terlebih dahulu sebelum menghubungkan device.'], 403);
        }

        try {
            $this->packageLimits->assertWithinLimit(
                $context->company,
                'device_count',
                1,
                $branch,
                fn () => $this->connectDeviceService->connectedCount($jwt, $branch->id, $exceptDeviceId),
            );
        } catch (PackageLimitExceededException $e) {
            return response()->json(['error' => 'Jatah device di paket Anda sudah penuh oleh device yang sedang terhubung. Putuskan (logout) salah satu device, atau upgrade paket untuk menambah device.'], 403);
        } catch (Throwable $e) {
            // listDevices() gagal tidak boleh memblokir; error asli
            // ditangani safeJson() di pemanggil.
            report($e);
        }

        return null;
    }

    protected function devicesOfActiveBranch(Request $request, array $devices): array
    {
        try {
            $context = $this->companyContext($request);
        } catch (Throwable $e) {
            return $devices;
        }

        if ($context->isLockedToBranch()) {
            return $devices;
        }

        $branchId = $context->activeBranch()?->id;

        return array_values(array_filter(
            $devices,
            fn (array $device) => ($device['branch_office_id'] ?? null) === $branchId
        ));
    }

    protected function safeJson(callable $callback): JsonResponse
    {
        $jwt = session('golang_jwt_token');

        if (! $jwt) {
            return response()->json(['error' => 'Sesi WhatsApp tidak ditemukan.'], 401);
        }

        try {
            return response()->json($callback($jwt));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Gagal menghubungi server WhatsApp.'], 502);
        }
    }
}
