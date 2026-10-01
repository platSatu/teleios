<?php

namespace App\Services\Marketplace\Lazada;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien HTTP Lazada Open Platform (satu-satunya tempat yang tahu cara
 * menandatangani request).
 *
 * Tanda tangan (sama dengan SDK resmi "lazop"): semua parameter (sistem +
 * API) diurutkan berdasarkan nama, lalu string = path API + nama1nilai1
 * nama2nilai2..., di-HMAC-SHA256 dengan App Secret, hasil hex huruf besar.
 *
 * App Key/Secret hanya dari config/services.php (.env) -- tidak pernah
 * disimpan di database atau ditulis ke log. Log error hanya memuat path,
 * kode, pesan, dan request_id dari Lazada.
 */
class LazadaClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.lazada.app_key')) && filled(config('services.lazada.app_secret'));
    }

    public function appKey(): string
    {
        return (string) config('services.lazada.app_key');
    }

    /** API data toko (pesanan, seller, dll.) -- gateway per negara. */
    public function get(string $apiPath, array $params, string $accessToken): array
    {
        return $this->send(config('services.lazada.api_url'), $apiPath, $params, $accessToken);
    }

    /** API otorisasi (/auth/token/create & /auth/token/refresh). */
    public function auth(string $apiPath, array $params): array
    {
        return $this->send(config('services.lazada.auth_api_url'), $apiPath, $params, null, 'POST');
    }

    public function sign(string $apiPath, array $params): string
    {
        ksort($params, SORT_STRING);

        $payload = $apiPath;

        foreach ($params as $key => $value) {
            $payload .= $key.$value;
        }

        return strtoupper(hash_hmac('sha256', $payload, (string) config('services.lazada.app_secret')));
    }

    private function send(string $baseUrl, string $apiPath, array $params, ?string $accessToken, string $method = 'GET'): array
    {
        if (! $this->isConfigured()) {
            throw new LazadaApiException('Integrasi Lazada belum dikonfigurasi (LAZADA_APP_KEY / LAZADA_APP_SECRET).', 'NotConfigured');
        }

        $params = array_filter($params, fn ($value) => $value !== null && $value !== '');
        $params += [
            'app_key' => $this->appKey(),
            'timestamp' => (string) round(microtime(true) * 1000),
            'sign_method' => 'sha256',
        ];

        if ($accessToken !== null) {
            $params['access_token'] = $accessToken;
        }

        $params['sign'] = $this->sign($apiPath, $params);
        $url = rtrim($baseUrl, '/').$apiPath;

        try {
            $request = Http::timeout(20)->acceptJson();
            // Hanya GET yang aman diulang -- POST token/create memakai kode
            // sekali pakai, mengulangnya bisa membuat kode hangus.
            $response = $method === 'GET'
                ? $request->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)->get($url, $params)
                : $request->asForm()->post($url, $params);
        } catch (ConnectionException $e) {
            throw new LazadaApiException('Tidak dapat terhubung ke Lazada. Coba lagi beberapa saat lagi.', 'ConnectionError', previous: $e);
        }

        $body = $response->json();

        if (! is_array($body)) {
            Log::warning('lazada: respons bukan JSON', ['path' => $apiPath, 'status' => $response->status()]);

            throw new LazadaApiException('Lazada memberikan respons yang tidak dikenali.', 'InvalidResponse');
        }

        $code = (string) ($body['code'] ?? '0');

        if ($code !== '0') {
            Log::warning('lazada: API error', [
                'path' => $apiPath,
                'code' => $code,
                'message' => $body['message'] ?? null,
                'request_id' => $body['request_id'] ?? null,
            ]);

            throw new LazadaApiException((string) ($body['message'] ?? 'Permintaan ke Lazada gagal.'), $code);
        }

        return $body;
    }
}
