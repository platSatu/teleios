<?php

namespace App\Services\Moderation;

/**
 * What App\Services\Moderation\TemplateModerationService::moderate()
 * decided about one piece of content. Exactly one of four outcomes:
 *
 *   approved    — clean as submitted, nothing changed.
 *   corrected   — AI rewrote one or more fields to fix a minor issue;
 *                 $fields carries the corrected value for every field
 *                 that was sent in (same keys as the input).
 *   rejected    — too severe/unfixable without changing the message's
 *                 meaning; $reason explains why, in Indonesian, shown
 *                 back to the user.
 *   unavailable — moderation genuinely could not run (not configured,
 *                 API error, unparseable AI response) — the caller
 *                 should hold the content as pending rather than treat
 *                 this as either an approval or a rejection.
 */
final class ModerationResult
{
    private function __construct(
        public readonly string $outcome,
        public readonly array $fields = [],
        public readonly ?string $reason = null,
    ) {
    }

    public static function approved(): self
    {
        return new self('approved');
    }

    /**
     * @param  array<string, string>  $fields
     */
    public static function corrected(array $fields): self
    {
        return new self('corrected', $fields);
    }

    public static function rejected(string $reason): self
    {
        return new self('rejected', [], $reason);
    }

    public static function unavailable(string $reason): self
    {
        return new self('unavailable', [], $reason);
    }

    public function isApproved(): bool
    {
        return $this->outcome === 'approved';
    }

    public function isCorrected(): bool
    {
        return $this->outcome === 'corrected';
    }

    public function isRejected(): bool
    {
        return $this->outcome === 'rejected';
    }

    public function isUnavailable(): bool
    {
        return $this->outcome === 'unavailable';
    }
}
