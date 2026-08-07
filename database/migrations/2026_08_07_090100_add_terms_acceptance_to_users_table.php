<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which Syarat & Ketentuan (App\Models\WebTermCondition) row
     * a user agreed to and when — two columns rather than a single
     * boolean, following the same "nullable timestamp = not yet done"
     * convention `email_verified_at` already uses on this table:
     * `terms_accepted_at` null means never accepted, non-null is the
     * moment they checked the box on the register form.
     *
     * `terms_id` is kept alongside the timestamp (not just the
     * timestamp alone) so which *version* of the terms they agreed to is
     * provable later even after the content is edited — restrictOnDelete
     * so a superadmin can never hard-delete a terms version that at
     * least one user's acceptance still points to (TermConditionController
     * also checks this up front with a friendlier error message; this FK
     * is the last-resort safety net if that check is ever bypassed).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('terms_id')->nullable()->after('handphone')
                ->constrained('web_term_conditions')->restrictOnDelete();
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['terms_id']);
            $table->dropColumn(['terms_id', 'terms_accepted_at']);
        });
    }
};
