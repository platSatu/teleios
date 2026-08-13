<?php

namespace App\Support;

/**
 * Single source of truth for the phone-number normalization rule every
 * customer-identity table keys on: digits only, no leading '+', e.g.
 * "+62 812-3456-7890" -> "6281234567890". Previously duplicated
 * verbatim as a static method on both App\Models\WaContact and
 * App\Models\WaPhoneBook — extracted here so App\Models\WaCustomer (and
 * anything else that needs it later) shares the exact same rule instead
 * of risking the copies drifting apart. Those two models keep their own
 * `normalizePhone()` static methods for backward compatibility with
 * existing call sites, but both just delegate here now.
 */
class PhoneNumber
{
    public static function normalize(string $raw): string
    {
        return preg_replace('/\D/', '', $raw) ?? '';
    }
}
