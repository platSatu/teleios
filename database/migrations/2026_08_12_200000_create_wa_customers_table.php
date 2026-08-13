<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaCustomer — CRM Roadmap Fase 0 ("Satukan Data
 * Kontak"). This is the ONE customer identity a phone number resolves
 * to, company-wide — the anchor every later CRM phase (Customer 360,
 * Task & Follow-up, Sales Pipeline, Segmentation) hangs off, per the
 * roadmap doc's docs/Roadmap_CRM_WhatsApp_Konexa.docx section 2.
 *
 * Deliberately does NOT replace App\Models\WaContact or
 * App\Models\WaPhoneBook — those two stay exactly as they are today,
 * with their own genuinely different lifecycles (WaContact:
 * auto-created the moment a chat opens; WaPhoneBook: manually
 * added/imported for broadcast targeting, with its own category/status/
 * blacklist fields — see WaPhoneBook's migration docblock for why they
 * were kept separate in the first place). Merging them into one table
 * would have thrown that distinction away and forced a redesign of both
 * the "Kontak" and "Buku Telepon" pages for zero benefit — Fase 0 only
 * asks for "satu identitas pelanggan", not "satu tabel".
 *
 * Instead, this table sits ALONGSIDE both: a nullable wa_customer_id
 * column is added to wa_contacts and wa_phone_book (see the next two
 * migrations) that both resolve to the same WaCustomer row for the same
 * (company_id, phone) — see App\Services\Crm\CustomerIdentityService,
 * the only place that ever creates one. A phone that only ever shows up
 * in the Inbox gets a WaCustomer with no linked WaPhoneBook row (and
 * vice versa); a phone that appears in both links to the SAME
 * WaCustomer, which is the whole point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_customers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            // Same normalization rule (App\Support\PhoneNumber) and same
            // per-company uniqueness shape as wa_contacts.phone /
            // wa_phone_book.phone — this is deliberately the shared key
            // all three tables agree on.
            $table->string('phone', 32);

            // Best-known display name — CustomerIdentityService only
            // ever fills this when it's still empty (see its docblock),
            // never overwrites a name either side already curated.
            $table->string('name')->nullable();

            $table->foreignUuid('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Earliest moment this person became known to the company at
            // all (first chat OR first phone book entry, whichever came
            // first) — a simple "customer since" fact Customer 360
            // (Fase 1) can show without recomputing it from scratch.
            $table->timestamp('first_seen_at')->nullable();

            $table->timestamp('last_contacted_at')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'phone']);
            $table->index(['company_id', 'branch_office_id']);
            $table->index(['assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customers');
    }
};
