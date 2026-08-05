<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaContact — a company's CRM contact book, separate
 * from the raw WhatsApp chat it originated from. A "chat" (device_id +
 * chat_jid, see App\Models\WaChatNote's docblock) is tied to one specific
 * device; a Contact is tied to a phone number, company-wide — so the
 * same person messaging two different company devices is still
 * recognized as one contact, and survives a device being disconnected/
 * replaced.
 *
 * Auto-created the first time a chat is opened in the Inbox (see
 * App\Http\Controllers\Chat\InboxController::contact()) — not something
 * a user has to manually add first. branch_office_id/assigned_to start
 * null and get filled in from there or from the Kontak management page
 * (App\Http\Controllers\Chat\ContactController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Which branch "owns" this contact — nullable (not every
            // company uses branches, and an owner-created contact starts
            // unassigned/pusat). Editable independently of which device/
            // branch the contact first messaged, since a contact can be
            // reassigned later.
            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            // Digits only, no leading '+', e.g. "6281234567890" — same
            // normalization InboxService/Go already use for phone
            // numbers. This (not chat_jid) is the identity key: it's what
            // makes the same person recognizable across devices.
            $table->string('phone', 32);

            // Team's own label for this contact — starts as whatever
            // WhatsApp push name/resolved name was available, editable
            // afterwards independently of what WhatsApp reports.
            $table->string('name')->nullable();

            // Which team member currently "owns" this contact — the
            // same field the Inbox detail panel's "+ Assign" button
            // reads/writes (previously a disabled placeholder with no
            // backing data at all).
            $table->foreignUuid('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // 'whatsapp' (auto-created from a real chat) | 'manual'
            // (added directly from the Kontak page) — cosmetic, shown in
            // the UI so a team can tell which contacts came from an
            // actual conversation vs. were entered by hand.
            $table->string('source', 20)->default('whatsapp');

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('last_contacted_at')->nullable();

            $table->timestamps();

            // One contact per phone number per company — re-opening the
            // same person's chat updates this same row instead of
            // creating a duplicate.
            $table->unique(['company_id', 'phone']);

            // Every real query this feature makes: "contacts in my
            // branch" (Kontak page, branch-locked member) and "contacts
            // assigned to me".
            $table->index(['company_id', 'branch_office_id']);
            $table->index(['assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_contacts');
    }
};
