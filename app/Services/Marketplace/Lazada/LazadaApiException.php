<?php

namespace App\Services\Marketplace\Lazada;

use RuntimeException;
use Throwable;

/**
 * Error dari Lazada (atau koneksi ke Lazada). `lazadaCode` = kolom "code"
 * di respons Lazada, mis. "IllegalAccessToken".
 */
class LazadaApiException extends RuntimeException
{
    /** Kode Lazada yang berarti token toko sudah tidak berlaku. */
    private const TOKEN_ERRORS = ['IllegalAccessToken', 'IllegalRefreshToken', 'InvalidAccessToken', 'AccessTokenExpired', 'IllegalAuthCode'];

    public function __construct(string $message, public readonly string $lazadaCode = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function isTokenError(): bool
    {
        return in_array($this->lazadaCode, self::TOKEN_ERRORS, true);
    }
}
