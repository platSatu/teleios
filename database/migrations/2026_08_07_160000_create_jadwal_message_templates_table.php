<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company, per-message-type WA wording override for Jadwal's
 * automatic notifications — "ajarin format untuk konfirmasinya
 * menggunakan template whatsapp juga kan ya?"
 *
 * Deliberately NOT App\Models\WaMessageTemplate (Chat > Pengaturan >
 * Pesan's own "WA Template" feature): that one is built for broadcast
 * campaigns and gates every template behind a superadmin review_status
 * before it's usable — exactly wrong for something like an H-1 class
 * reminder that has to fire on its own every single day without anyone
 * remembering to re-approve it. This table is the lightweight version:
 * a company admin edits it themselves (Jadwal\
 * JadwalMessageTemplateController), it's live immediately, no approval
 * step, and it only ever has ONE row per (company, message_key) — a
 * NULL/blank `body` just means "use the built-in Indonesian default"
 * (see App\Services\Jadwal\JadwalMessageTemplateService::DEFINITIONS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_message_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('message_key', 50);
            $table->text('body')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'message_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_message_templates');
    }
};
