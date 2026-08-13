<?php

namespace App\Http\Controllers\Chat;

use App\Exceptions\PackageLimitExceededException;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Services\Chat\ConnectDeviceService;
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
    ) {}

    /**
     * Show the device list page.
     */
    public function index(): View|RedirectResponse
    {
        $jwt = session('golang_jwt_token');

        if (! $jwt) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['golang' => 'Sesi WhatsApp tidak ditemukan, silakan login ulang.']);
        }

        try {
            $devices = $this->connectDeviceService->listDevices($jwt);
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
    public function list(): JsonResponse
    {
        return $this->safeJson(fn (string $jwt) => [
            'devices' => $this->connectDeviceService->listDevices($jwt),
        ]);
    }

    /**
     * AJAX: register a new device and start pairing it. The frontend
     * opens a modal with the returned QR code right after this call.
     *
     * Package quota guard: "device_count" is a 'stock' metric (see
     * App\Models\LimitMetric) — checked live against how many devices
     * this user already has via connectDeviceService->listDevices()
     * rather than a separately-tracked counter (there's no local devices
     * table to keep in sync — see the class docblock). Resolving a
     * company context is best-effort here: this controller is otherwise
     * purely session/JWT-scoped, not company-scoped, so if a context
     * can't be resolved the guard simply doesn't apply (fails open,
     * same as everywhere else PackageLimitService is used) rather than
     * blocking a device connection outright.
     */
    public function add(Request $request): JsonResponse
    {
        $jwt = session('golang_jwt_token');

        if (! $jwt) {
            return response()->json(['error' => 'Sesi WhatsApp tidak ditemukan.'], 401);
        }

        try {
            $company = $this->companyContext($request)->company;
        } catch (Throwable $e) {
            $company = null;
        }

        if ($company) {
            try {
                $this->packageLimits->assertWithinLimit(
                    $company,
                    'device_count',
                    1,
                    null,
                    fn () => count($this->connectDeviceService->listDevices($jwt)),
                );
            } catch (PackageLimitExceededException $e) {
                return response()->json(['error' => $e->getMessage()], 403);
            } catch (Throwable $e) {
                // listDevices() itself failing shouldn't block adding a
                // device — report it and fall through to addDevice()'s
                // own error handling (safeJson below) instead.
                report($e);
            }
        }

        return $this->safeJson(fn (string $jwt) => $this->connectDeviceService->addDevice($jwt));
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
    public function reconnect(string $device): JsonResponse
    {
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
