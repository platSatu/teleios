<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaCategoryTemplate — a company-defined category
     * for organizing its WA Templates (e.g. "Promo", "Reminder"), free-
     * form rather than locked to Meta's fixed Marketing/Utility/
     * Authentication set — see Chat\CategoryTemplateController where a
     * company creates these.
     *
     * `review_status` is a superadmin gate: a brand new category starts
     * 'pending' and isn't selectable on the template form until a
     * superadmin approves it (Superadmin\Web... no — see
     * Superadmin\CategoryTemplateReviewController) — reusing this app's
     * existing pattern of not letting user-submitted catalog entries go
     * live unmoderated. Deliberately separate from `status`
     * (active|inactive), which stays the company's own on/off toggle,
     * same "two independent axes" shape as wa_message_templates itself
     * (see that table's migration).
     */
    public function up(): void
    {
        Schema::create('wa_category_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('status', 20)->default('active'); // active | inactive — company's own toggle

            // pending | approved | rejected — superadmin gate, see docblock above
            $table->string('review_status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // A company shouldn't end up with two categories named
            // identically (confusing in the template form's dropdown) —
            // scoped per company, not globally, since "Promo" is a
            // perfectly reasonable name for many different companies to
            // each want independently.
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_category_templates');
    }
};
