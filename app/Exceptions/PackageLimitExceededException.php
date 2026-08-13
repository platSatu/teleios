<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by App\Services\PackageLimitService when an action would push a
 * company's usage of a metric past what its active Package allows. The
 * message is already human-readable Indonesian — safe to surface
 * directly to the end user (form error, job log, etc.) rather than a
 * generic "something went wrong".
 */
class PackageLimitExceededException extends RuntimeException
{
    public function __construct(
        string $message,
        protected string $metricKey,
    ) {
        parent::__construct($message);
    }

    public function metricKey(): string
    {
        return $this->metricKey;
    }
}
