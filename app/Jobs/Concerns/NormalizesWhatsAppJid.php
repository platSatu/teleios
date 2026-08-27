<?php

namespace App\Jobs\Concerns;

use App\Support\PhoneNumber;

/**
 * Used by App\Jobs\SendScheduledWaMessage (covers all 3 WaMessageSchedule
 * types — once/recurring/drip — since the merge that retired the
 * separate SendMessageSequenceStep job): turns the plain digits a user
 * types into a "Nomor WhatsApp Tujuan"-style field (placeholder
 * convention across these forms: 6281234567890 — country code, no
 * leading 0/+) into a full WhatsApp JID. Defensive about stray
 * spaces/dashes/+ since none of these forms validate it as a strict
 * phone format.
 *
 * The actual digit-correction (stray formatting stripped, "0812..."/
 * "812..." rewritten to the "6281..." country-code form) is delegated to
 * App\Support\PhoneNumber::normalize() — the same single source of truth
 * App\Http\Controllers\Api\WaApiSendMessageController::normalizeJid() and
 * App\Http\Controllers\Api\GoogleFormWebhookController use, so a schedule
 * recipient typed as "0812..." resolves to the exact same JID a manually
 * entered Buku Telepon/Kontak number with the same digits would.
 */
trait NormalizesWhatsAppJid
{
    protected function toIndividualJid(?string $phoneNumber): ?string
    {
        $digits = PhoneNumber::normalize((string) $phoneNumber);

        if ($digits === '') {
            return null;
        }

        return $digits.'@s.whatsapp.net';
    }
}
