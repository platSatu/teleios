<?php

namespace App\Services\Crm;

use App\Models\WaCustomer;
use App\Support\PhoneNumber;

/**
 * The one place that finds-or-creates App\Models\WaCustomer rows — CRM
 * Roadmap Fase 0's "satukan data kontak" engine. Called from every place
 * that already creates/imports a WaContact or WaPhoneBook row (see
 * App\Http\Controllers\Chat\InboxController::contact(),
 * App\Http\Controllers\Chat\PhoneBookController::store()/update(), and
 * App\Imports\PhoneBookImport), so both sides converge onto the same
 * identity for the same (company_id, phone) instead of drifting apart.
 *
 * Deliberately dumb/small: this only resolves identity and fills in
 * blanks, it never overwrites data a human already curated on either
 * side. Fase 1 (Customer 360) is where richer merge/edit behavior would
 * live, once there's an actual UI for a person to review/fix a
 * customer's own record directly.
 */
class CustomerIdentityService
{
    /**
     * Finds the WaCustomer for ($companyId, $rawPhone), creating one if
     * none exists yet. $attributes may seed `name`, `branch_office_id`,
     * `created_by` — but ONLY when the customer row doesn't already have
     * a value for that field, so calling this from two different flows
     * (e.g. Inbox first, then a later Buku Telepon import) never clobbers
     * whichever side filled it in first.
     *
     * @param  array{name?: ?string, branch_office_id?: ?string, created_by?: ?string}  $attributes
     */
    public function resolve(string $companyId, string $rawPhone, array $attributes = []): WaCustomer
    {
        $phone = PhoneNumber::normalize($rawPhone);

        $customer = WaCustomer::firstOrNew([
            'company_id' => $companyId,
            'phone' => $phone,
        ]);

        foreach (['name', 'branch_office_id', 'created_by'] as $field) {
            if (! empty($attributes[$field]) && empty($customer->{$field})) {
                $customer->{$field} = $attributes[$field];
            }
        }

        if (empty($customer->first_seen_at)) {
            $customer->first_seen_at = now();
        }

        $customer->save();

        return $customer;
    }

    /**
     * Bumps last_contacted_at — called alongside WaContact's own
     * last_contacted_at update in InboxController::contact(), so the
     * customer identity's "last heard from" fact stays in step with the
     * chat-derived record it mirrors.
     */
    public function touchContacted(WaCustomer $customer): void
    {
        $customer->update(['last_contacted_at' => now()]);
    }
}
