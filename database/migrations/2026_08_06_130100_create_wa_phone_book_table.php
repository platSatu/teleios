<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaPhoneBook — a company's phone book entry
     * (Chat > Buku Telepon), always attached to exactly one
     * wa_category_phone_book "Kelompok". Deliberately separate from
     * App\Models\WaContact (the CRM contact book auto-populated from real
     * Inbox conversations) — this table is the company's own manually
     * curated/imported address book used as a recipient source when
     * building a WA Template/Pesan Terjadwal, not something that gets
     * auto-created from chat activity.
     *
     * `is_blacklisted` folds the "Blacklist" feature into this same
     * table (rather than a separate wa_phone_book_blacklists table) —
     * blacklisting is a per-entry boolean flag, not a distinct record
     * with its own lifecycle, so a second table would only add a join
     * for zero benefit. A blacklisted entry stays visible in the phone
     * book (so it can be un-blacklisted) but is excluded from every
     * recipient picker — see WaPhoneBook::recipientCandidates().
     */
    public function up(): void
    {
        Schema::create('wa_phone_book', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Nullable — not every company uses branches, same rule as
            // wa_contacts.branch_office_id.
            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->foreignUuid('wa_category_phone_book_id')
                ->constrained('wa_category_phone_book')
                ->cascadeOnDelete();

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');

            // Digits only, no leading '+' — same normalization convention
            // as WaContact::normalizePhone().
            $table->string('phone', 32);

            $table->string('email')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive — company's own toggle

            // See docblock above — independent of `status`, which is the
            // company's general on/off toggle for the entry itself.
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();
            $table->timestamp('blacklisted_at')->nullable();

            $table->timestamps();

            // One entry per phone number per company — importing the same
            // number twice updates the same row instead of duplicating it.
            $table->unique(['company_id', 'phone']);

            $table->index(['company_id', 'wa_category_phone_book_id']);
            $table->index(['company_id', 'branch_office_id']);
            $table->index(['company_id', 'is_blacklisted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_phone_book');
    }
};
