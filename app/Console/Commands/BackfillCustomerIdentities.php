<?php

namespace App\Console\Commands;

use App\Models\WaContact;
use App\Models\WaPhoneBook;
use App\Services\Crm\CustomerIdentityService;
use Illuminate\Console\Command;

/**
 * One-time (but safely re-runnable) data migration for CRM Roadmap Fase
 * 0: links every EXISTING App\Models\WaContact and App\Models\WaPhoneBook
 * row to its App\Models\WaCustomer identity. New rows created after this
 * ships link themselves automatically (see
 * App\Services\Crm\CustomerIdentityService and its call sites in
 * InboxController/PhoneBookController/PhoneBookImport) — this command
 * only exists to catch up data that predates that wiring.
 *
 * Idempotent: skips any row that already has a wa_customer_id, so running
 * this more than once (e.g. after a rollback, or on a second environment)
 * never duplicates or re-links anything. Not scheduled — run manually
 * once per environment:
 *
 *   php artisan crm:backfill-customer-identities
 */
class BackfillCustomerIdentities extends Command
{
    protected $signature = 'crm:backfill-customer-identities';

    protected $description = 'Link existing wa_contacts and wa_phone_book rows to their wa_customers identity (CRM Fase 0)';

    public function handle(CustomerIdentityService $customers): int
    {
        $linkedContacts = 0;
        $linkedPhoneBook = 0;

        $this->info('Linking wa_contacts...');

        WaContact::whereNull('wa_customer_id')
            ->chunkById(200, function ($contacts) use ($customers, &$linkedContacts) {
                foreach ($contacts as $contact) {
                    $customer = $customers->resolve($contact->company_id, $contact->phone, [
                        'name' => $contact->name,
                        'branch_office_id' => $contact->branch_office_id,
                        'created_by' => $contact->created_by,
                    ]);

                    $contact->wa_customer_id = $customer->id;
                    $contact->saveQuietly();
                    $linkedContacts++;
                }
            });

        $this->info("Linked {$linkedContacts} wa_contacts rows.");

        $this->info('Linking wa_phone_book...');

        WaPhoneBook::whereNull('wa_customer_id')
            ->chunkById(200, function ($entries) use ($customers, &$linkedPhoneBook) {
                foreach ($entries as $entry) {
                    $customer = $customers->resolve($entry->company_id, $entry->phone, [
                        'name' => $entry->name,
                        'branch_office_id' => $entry->branch_office_id,
                        'created_by' => $entry->created_by,
                    ]);

                    $entry->wa_customer_id = $customer->id;
                    $entry->saveQuietly();
                    $linkedPhoneBook++;
                }
            });

        $this->info("Linked {$linkedPhoneBook} wa_phone_book rows.");
        $this->info('Done.');

        return self::SUCCESS;
    }
}
