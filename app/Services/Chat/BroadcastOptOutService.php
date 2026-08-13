<?php

namespace App\Services\Chat;

use App\Models\WaContact;
use App\Models\WaOptOut;
use Illuminate\Support\Collection;

/**
 * The one place that knows whether a phone number has opted out of a
 * company's broadcast messages, and the only place allowed to change
 * that. Backs the STOP/START keyword handling in
 * App\Http\Controllers\Api\WaIncomingMessageWebhookController and the
 * pre-send guard in App\Jobs\SendScheduledWaMessage.
 */
class BroadcastOptOutService
{
    /**
     * Records an opt-out, or refreshes one that already exists (a second
     * STOP reply re-stamps opted_out_at/note rather than erroring on the
     * unique index) — idempotent by design, since a customer might reply
     * STOP more than once and that must never surface as a failure
     * anywhere upstream.
     */
    public function optOut(string $companyId, string $phone, string $source, ?string $note = null, ?string $createdBy = null): WaOptOut
    {
        $normalized = WaContact::normalizePhone($phone);

        return WaOptOut::updateOrCreate(
            ['company_id' => $companyId, 'phone' => $normalized],
            [
                'source' => $source,
                'note' => $note,
                'created_by' => $createdBy,
                'opted_out_at' => now(),
            ]
        );
    }

    /**
     * Removes an opt-out (a STOP-then-START reply, or a manual
     * re-subscribe from the opt-out list). Returns whether a row was
     * actually removed, so callers can tell "successfully opted back in"
     * from "wasn't opted out in the first place".
     */
    public function optIn(string $companyId, string $phone): bool
    {
        $normalized = WaContact::normalizePhone($phone);

        return WaOptOut::where('company_id', $companyId)->where('phone', $normalized)->delete() > 0;
    }

    public function isOptedOut(string $companyId, string $phone): bool
    {
        $normalized = WaContact::normalizePhone($phone);

        if ($normalized === '') {
            return false;
        }

        return WaOptOut::where('company_id', $companyId)->where('phone', $normalized)->exists();
    }

    /**
     * Bulk variant for anything that needs to check many numbers at once
     * (e.g. a future recipient picker warning "12 of these are opted
     * out") — one query instead of N, returning just the subset that IS
     * opted out.
     *
     * @param  Collection<int, string>  $phones
     * @return Collection<int, string>
     */
    public function filterOptedOut(string $companyId, Collection $phones): Collection
    {
        $normalized = $phones->map(fn (string $phone) => WaContact::normalizePhone($phone))->filter()->unique()->values();

        if ($normalized->isEmpty()) {
            return collect();
        }

        return WaOptOut::where('company_id', $companyId)
            ->whereIn('phone', $normalized)
            ->pluck('phone');
    }
}
