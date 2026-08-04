<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the Go backend's authentication API. The Go backend issues its
 * own JWT (separate from Laravel's session/Sanctum auth) which the caller
 * is expected to store (typically in the Laravel session) and forward as
 * a Bearer token to other Go endpoints made on behalf of this user.
 */
class GolangAuthService
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.golang.url'), '/');
        $this->apiKey = config('services.golang.key');
    }

    /**
     * Exchange credentials for a JWT from the Go backend.
     *
     * @throws RuntimeException if the Go backend rejects the credentials
     *                          or is unreachable.
     */
    public function login(string $email, string $password): string
    {
        $response = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'Accept' => 'application/json',
        ])->post("{$this->baseUrl}/api/auth/login", [
            'email' => $email,
            'password' => $password,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Golang auth backend rejected login: '.$response->body()
            );
        }

        $token = $response->json('token');

        if (! $token) {
            throw new RuntimeException('Golang auth backend did not return a token.');
        }

        return $token;
    }
}
