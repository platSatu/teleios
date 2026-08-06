<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual (non-template) message content on WaMessageSchedule used to
     * be a single `message` textarea regardless of `category_schedule` —
     * fine for 'text', but 'location'/'image'/'document' need more than
     * one field:
     *
     *   - text     → `message` alone (unchanged)
     *   - location → `message` is repurposed as the location NAME (the
     *                form relabels the same textarea to a single-line
     *                input for this category), `link` holds the maps/
     *                address link
     *   - image / document → `attachment_*` columns, `message` unused
     *
     * Same attachment_path/type/original_name/size shape as
     * 2026_08_06_110000_add_recipients_content_type_attachment_to_wa_message_templates_table
     * on wa_message_templates, so both controllers can share identical
     * upload/validation logic.
     */
    public function up(): void
    {
        Schema::table('wa_message_schedules', function (Blueprint $table) {
            $table->string('link', 2000)->nullable()->after('message');
            $table->string('attachment_path')->nullable()->after('link');
            $table->string('attachment_type', 20)->nullable()->after('attachment_path');
            $table->string('attachment_original_name')->nullable()->after('attachment_type');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'link',
                'attachment_path',
                'attachment_type',
                'attachment_original_name',
                'attachment_size',
            ]);
        });
    }
};
