<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the Go backend's WhatsApp device endpoints on behalf of the
 * currently logged-in user. Every call needs that user's Golang JWT
 * (stored in the Laravel session at login) plus the shared server-to-
 * server API key. A user can own several devices, so every call except
 * listDevices/addDevice is scoped to one device ID.
 */
class ConnectDeviceService
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.golang.url'), '/');
        $this->apiKey = config('services.golang.key');
    }

    /**
     * List every WhatsApp device the user owns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDevices(string $jwt): array
    {
        return $this->request('get', '/api/wa/devices', $jwt)['devices'] ?? [];
    }

    /**
     * Register a new device and start pairing it.
     *
     * @return array{device_id: string, status: string, qr_string: string}
     */
    public function addDevice(string $jwt): array
    {
        return $this->request('post', '/api/wa/devices', $jwt);
    }

    /**
     * Get one device's current connection status (and latest QR, if still
     * pending).
     *
     * @return array{status: string, qr_string: string, phone_number: string}
     */
    public function status(string $jwt, string $deviceId): array
    {
        return $this->request('get', "/api/wa/devices/{$deviceId}/status", $jwt);
    }

    /**
     * (Re)start pairing for a device the user already owns — how a fresh
     * QR code is requested for a disconnected device.
     *
     * @return array{status: string, qr_string: string}
     */
    public function reconnect(string $jwt, string $deviceId): array
    {
        return $this->request('post', "/api/wa/devices/{$deviceId}/reconnect", $jwt);
    }

    /**
     * Log one device out of WhatsApp.
     */
    public function disconnect(string $jwt, string $deviceId): array
    {
        return $this->request('post', "/api/wa/devices/{$deviceId}/disconnect", $jwt);
    }

    /**
     * @throws RuntimeException if the Go backend rejects the request or is
     *                          unreachable.
     */
    protected function request(string $method, string $path, string $jwt, array $payload = []): array
    {
        $response = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'Authorization' => 'Bearer '.trim($jwt),
            'Accept' => 'application/json',
        ])->{$method}("{$this->baseUrl}{$path}", $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Golang WhatsApp backend request to {$path} failed: ".$response->body()
            );
        }

        return $response->json();
    }
}
