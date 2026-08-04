<?php

namespace App\Jobs\Concerns;

/**
 * Used by App\Jobs\SendScheduledWaMessage (covers all 3 WaMessageSchedule
 * types — once/recurring/drip — since the merge that retired the
 * separate SendMessageSequenceStep job): turns the plain digits a user
 * types into a "Nomor WhatsApp Tujuan"-style field (placeholder
 * convention across these forms: 6281234567890 — country code, no
 * leading 0/+) into a full WhatsApp JID. Defensive about stray
 * spaces/dashes/+ since none of these forms validate it as a strict
 * phone format.
 */
trait NormalizesWhatsAppJid
{
    protected function toIndividualJid(?string $phoneNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phoneNumber);

        if ($digits === '') {
            return null;
        }

        // A locally-typed "0812..." without the shared country-code
        // convention these forms' placeholders assume — normalize it
        // the same way (Indonesian trunk prefix -> country code 62)
        // rather than send a malformed JID.
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits.'@s.whatsapp.net';
    }
}
