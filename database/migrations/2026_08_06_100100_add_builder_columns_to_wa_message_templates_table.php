<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrades App\Models\WaMessageTemplate from a bare name+body pair
     * into a Meta-Business-style template: a category (App\Models\
     * WaCategoryTemplate), language, optional header/footer, tappable
     * buttons, and the superadmin review workflow every such template
     * needs before it can actually be used to broadcast (unmoderated
     * outbound WhatsApp content is exactly the kind of thing that gets
     * a whole number banned, not just this app).
     *
     * `template` (the body) is left as-is, unrenamed — every existing
     * caller (MessageScheduleController, WaMessageScheduleStep, Jobs\
     * SendScheduledWaMessage, the old _form.blade.php) already reads/
     * writes that column name.
     *
     * `status` (active|inactive) keeps meaning exactly what it always
     * has — the company's own on/off toggle. `review_status` is the new,
     * independent axis: a template is only actually usable once BOTH
     * are true (status=active AND review_status=approved) — see
     * WaMessageTemplate::scopeUsable(), mirroring WaCategoryTemplate's
     * own two-axis shape from the migration just before this one.
     */
    public function up(): void
    {
        Schema::table('wa_message_templates', function (Blueprint $table) {
            $table->foreignUuid('wa_category_template_id')->nullable()
                ->after('company_id')
                ->constrained('wa_category_templates')
                ->nullOnDelete();

            // ISO 639-1-ish short code (id, en, ...) — kept as a plain
            // string rather than an enum so a new language never needs
            // a migration, just a new <option> in the Blade select.
            $table->string('language', 10)->default('id')->after('name');

            // Both capped at 60 chars at the DB level too (not just
            // frontend maxlength) — WhatsApp's own template header/
            // footer limit, see MessageTemplateController's validator.
            $table->string('header', 60)->nullable()->after('language');
            $table->string('footer', 60)->nullable()->after('template');

            // [{"type": "url"|"phone", "label": "...", "value": "..."}]
            // — see WaMessageTemplate::buttons cast and the builder
            // form's repeater. "url" value is either a plain https://
            // link (Static) or a {{1}}-suffixed base URL (Dynamic) —
            // validated in the controller, not at the DB level.
            $table->json('buttons')->nullable()->after('footer');

            // {"fullname": "Budi", "diskon": "DISKON 30%"} — example
            // values for every {{variable}} auto-detected in header/
            // template/footer, shown back to the user on the builder
            // form (MessageTemplateController re-detects variables from
            // the saved text on every load, so this only ever needs to
            // carry the *values*, never the variable list itself, which
            // would risk drifting out of sync with the actual text).
            $table->json('variables_example')->nullable()->after('buttons');

            // draft | in_review | approved | rejected — a template
            // starts 'draft' while being edited, flips to 'in_review'
            // on submit (see MessageTemplateController::store()/
            // submitForReview()), and only a superadmin can move it to
            // 'approved'/'rejected' from there (Superadmin\
            // CategoryTemplateReviewController). Editing an
            // approved/rejected template resets it back to 'draft' —
            // content can't change out from under an approval.
            $table->string('review_status', 20)->default('draft')->after('status');
            $table->text('rejection_reason')->nullable()->after('review_status');
            $table->foreignUuid('reviewed_by')->nullable()
                ->after('rejection_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        // Every template that existed before this migration was, in
        // effect, already "approved" — nothing broadcasts through it new
        // by this migration alone, and it would be a genuinely bad
        // surprise for existing schedules to suddenly stop finding their
        // template usable because a backfill defaulted it to 'draft'.
        \Illuminate\Support\Facades\DB::table('wa_message_templates')->update(['review_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('wa_message_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wa_category_template_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'language',
                'header',
                'footer',
                'buttons',
                'variables_example',
                'review_status',
                'rejection_reason',
                'reviewed_at',
            ]);
        });
    }
};
