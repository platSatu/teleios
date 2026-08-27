<?php

namespace App\Support;

/**
 * Single source of truth for the phone-number normalization rule every
 * customer-identity table keys on: digits only, no leading '+', PLUS the
 * Indonesian trunk-prefix/country-code correction below, e.g.
 * "+62 812-3456-7890" -> "6281234567890", "0812-3456-7890" ->
 * "6281234567890", "812-3456-7890" -> "6281234567890". Previously
 * duplicated verbatim as a static method on both App\Models\WaContact
 * and App\Models\WaPhoneBook — extracted here so App\Models\WaCustomer
 * (and anything else that needs it later) shares the exact same rule
 * instead of risking the copies drifting apart. Those two models keep
 * their own `normalizePhone()` static methods for backward compatibility
 * with existing call sites, but both just delegate here now.
 *
 * Indonesian number correction (added after the "7500 rows imported, only
 * 700 saved" bug investigation — most rows in a real-world export come in
 * as a local "0812..." or bare "812..." format, neither of which
 * previously matched anything already stored as "62812...", so they
 * looked like fresh rows every time instead of de-duping/validating
 * consistently):
 *   - Starts with "0"  -> replace the leading "0" with "62" (the
 *     Indonesian trunk prefix is not part of the number once a country
 *     code is present).
 *   - Starts with "8" (no leading "0"/"62" at all) -> prepend "62".
 *   - Starts with "62" already -> left exactly as-is.
 *   - Anything else (other country codes, landline numbers, etc.) -> left
 *     exactly as-is (digits-only), same as before this correction existed.
 *
 * This is also the ONE place every WhatsApp JID builder in this app
 * (App\Jobs\Concerns\NormalizesWhatsAppJid, App\Http\Controllers\Api\
 * WaApiSendMessageController::normalizeJid(), App\Http\Controllers\Api\
 * GoogleFormWebhookController) delegates its digit-normalization to —
 * each of those still appends its own "@s.whatsapp.net"/passthrough
 * logic on top, but the actual digit-correction rule lives only here.
 */
class PhoneNumber
{
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }
}
