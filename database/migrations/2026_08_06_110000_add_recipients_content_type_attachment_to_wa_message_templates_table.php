<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Second round of changes to wa_message_templates, driven by the
     * user's decision (this session) that recipients move from the
     * schedule form onto the template itself — a template now carries
     * its own "Tujuan Pengiriman" (same tri-tab shape as
     * WaMessageSchedule::recipients: phone/group/user), plus a hard-
     * coded `content_type` that decides which fields the builder form
     * shows (mirrors the same text/location/image/document pattern
     * MessageScheduleController already uses for non-template manual
     * messages):
     *
     *   - text            → judul (name, already exists) + isi pesan (template, already exists)
     *   - text_link       → + link
     *   - text_link_file  → + link + attachment (the "lengkap" tier)
     *
     * `content_type` is deliberately a separate column from
     * wa_category_template_id — that FK is still the free-form,
     * superadmin-reviewed grouping (e.g. "Promo", "Reminder") added in
     * 2026_08_06_100000_create_wa_category_templates_table; this is an
     * orthogonal "which fields does this template need" switch.
     */
    public function up(): void
    {
        Schema::table('wa_message_templates', function (Blueprint $table) {
            // Same [{"type": "phone"|"group"|"user", "value": "..."}]
            // shape as WaMessageSchedule::recipients — see that model's
            // docblock. Null/empty for templates that don't carry their
            // own recipients (not every template necessarily needs one
            // yet — the schedule form's manual/non-template path is
            // untouched by this).
            $table->json('recipients')->nullable()->after('variables_example');

            $table->string('content_type', 20)->default('text')->after('recipients');

            // Single reference link (e.g. Google Maps / landing page) —
            // distinct from the "Tombol Aksi" `buttons` column: buttons
            // are tappable WhatsApp CTA buttons, this is just a plain
            // line of text/URL that content_type = text_link(_file)
            // reveals on the form.
            $table->string('link', 2000)->nullable()->after('content_type');

            $table->string('attachment_path')->nullable()->after('link');
            // video | image | document | text — matches the accepted
            // upload categories from the builder form; drives which MIME/
            // extension whitelist + max size the controller enforces.
            $table->string('attachment_type', 20)->nullable()->after('attachment_path');
            $table->string('attachment_original_name')->nullable()->after('attachment_type');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_templates', function (Blueprint $table) {
            $table->dropColumn([
                'recipients',
                'content_type',
                'link',
                'attachment_path',
                'attachment_type',
                'attachment_original_name',
                'attachment_size',
            ]);
        });
    }
};
