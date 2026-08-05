<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets a company generate/regenerate a token+secret_key pair for one of
 * its connected WhatsApp devices, so a third party can send messages
 * through that device without ever logging into this dashboard — see
 * App\Models\WaApiKey, App\Http\Middleware\VerifyWaApiKey (how a
 * third-party request authenticates), and App\Http\Controllers\Api\
 * WaApiSendMessageController (the one thing those credentials can
 * currently do: send a message).
 *
 * Called from the Device page (resources/views/chat/konekdevice/
 * konekdevice.blade.php) via fetch() — same AJAX-JSON shape as
 * ConnectDeviceController, since the device list itself is JS-rendered
 * from the Go backend's response, not a Blade loop. `device` here is
 * just the opaque device_id string Go returns — this app has no local
 * devices table to validate it against (see App\Models\WaApiKey's
 * docblock), so "does this device actually belong to this company" is
 * NOT independently re-checked here; it's implicitly trusted because
 * the device_id came from ConnectDeviceService::listDevices() scoped to
 * this same logged-in user's Golang JWT in the first place.
 *
 * Scoped via ResolvesCompanyContext (not owner-only) — any member who
 * can reach the Device page at all (gated by 'menu.access', see
 * App\Http\Middleware\EnsureMenuAccess) can manage that company's device
 * API keys, same as they can already reconnect/disconnect devices via
 * ConnectDeviceController.
 */
class WaApiKeyController extends Controller
{
    use ResolvesCompanyContext;

    /**
     * The API Key page itself — was a Bootstrap modal launched from the
     * Device list ("API Key" button per row); now its own full page you
     * land on, so the URL is shareable/bookmarkable and refreshing it
     * doesn't lose your place back on the Device list. Renders an empty
     * shell; the page's own JS calls data() below to actually load the
     * key (avoids a second Golang-JWT-bearing round trip just to decide
     * which template to show).
     *
     * ?phone= is optional — passed through from the Device list link so
     * the page can pre-fill "Nomor WhatsApp Tujuan Feedback" and label a
     * freshly-generated key, without an extra request to look the phone
     * number back up.
     */
    public function page(Request $request, string $device): View
    {
        return view('chat.konekdevice.api-key', [
            'deviceId' => $device,
            'devicePhone' => $request->query('phone', ''),
        ]);
    }

    /**
     * AJAX: current key info for one device, or null if none has been
     * generated yet — the page uses this to decide whether to show
     * "Generate" or the existing token/secret + "Regenerate" buttons.
     */
    public function data(Request $request, string $device): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $apiKey = WaApiKey::where('company_id', $company->id)
            ->where('device_id', $device)
            ->first();

        return response()->json([
            'api_key' => $apiKey ? $this->present($apiKey) : null,
        ]);
    }

    /**
     * Creates the key pair the first time — if one already exists for
     * this device, returns it unchanged rather than silently rotating
     * credentials someone might already have handed to a third party.
     * Use regenerateToken()/regenerateSecret() to actually rotate.
     */
    public function generate(Request $request, string $device): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $apiKey = WaApiKey::where('company_id', $company->id)
            ->where('device_id', $device)
            ->first();

        if (! $apiKey) {
            $apiKey = WaApiKey::create([
                'company_id' => $company->id,
                'device_id' => $device,
                'device_label' => $request->string('device_label')->value() ?: null,
                'api_host' => rtrim(config('app.url'), '/'),
                'status' => 'active',
            ]);
        }

        return response()->json(['api_key' => $this->present($apiKey)]);
    }

    /**
     * Old token stops working the instant this saves — no grace period,
     * same as ReferralCodeController::regenerate(). The secret is left
     * untouched, so a third party only needs to update HALF their stored
     * credential if just this leaked.
     */
    public function regenerateToken(Request $request, string $device): JsonResponse
    {
        $apiKey = $this->findOrFail($request, $device);
        $apiKey->regenerateToken();

        return response()->json(['api_key' => $this->present($apiKey)]);
    }

    public function regenerateSecret(Request $request, string $device): JsonResponse
    {
        $apiKey = $this->findOrFail($request, $device);
        $apiKey->regenerateSecret();

        return response()->json(['api_key' => $this->present($apiKey)]);
    }

    private function findOrFail(Request $request, string $device): WaApiKey
    {
        $company = $this->ownedCompanyOrFail($request);

        return WaApiKey::where('company_id', $company->id)
            ->where('device_id', $device)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WaApiKey $apiKey): array
    {
        return [
            'device_id' => $apiKey->device_id,
            'api_host' => $apiKey->api_host,
            'token' => $apiKey->token,
            'secret_key' => $apiKey->secret_key,
            'status' => $apiKey->status,
            'last_used_at' => $apiKey->last_used_at?->diffForHumans(),
        ];
    }
}
