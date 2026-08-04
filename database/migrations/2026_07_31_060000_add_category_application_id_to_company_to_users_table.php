<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A member's access can now be scoped to one or more
     * CategoryApplications (e.g. an employee granted "Chat" but not
     * "WhatsApp") instead of the whole company at once. Since one user
     * can be registered under more than one category, this is modeled
     * as one company_to_users ROW PER (user, company, category) rather
     * than a comma-separated list column — see User\Profile\
     * CompanyUserController, which now creates/reconciles one row per
     * category a member is granted. The owner's own auto-created row
     * (see ProfileController::updateCompany()) is left with
     * category_application_id = null, representing unrestricted access
     * rather than one specific category.
     *
     * nullOnDelete (not restrict): category_applications is a
     * superadmin-managed catalog — deleting one shouldn't be blocked
     * just because some company assigned it to a member; the membership
     * row survives with category_application_id reset to null instead.
     *
     * Every step below is guarded with an existence check so this
     * migration can be safely re-run after a partial failure (MySQL DDL
     * auto-commits per statement — it doesn't roll back as a unit the
     * way a wrapped transaction would).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('company_to_users', 'category_application_id')) {
            Schema::table('company_to_users', function (Blueprint $table) {
                $table->foreignUuid('category_application_id')
                    ->nullable()
                    ->after('company_role_id')
                    ->constrained('category_applications')
                    ->nullOnDelete();
            });
        }

        // Add the new composite unique BEFORE dropping the old one. The
        // old unique(user_id, company_id) is currently the ONLY index
        // that satisfies MySQL's "an FK column needs a leftmost-prefix
        // index" requirement for the user_id foreign key — it was
        // created together with that FK inside the original CREATE
        // TABLE, so MySQL reused it instead of making a separate
        // single-column index. Dropping it first (while it's still the
        // sole supporting index) throws error 1553. Adding the new
        // composite unique first gives MySQL another index that also
        // starts with user_id, so it can re-point the FK there and then
        // safely let the old index go.
        //
        // Also uses an explicit short name: Laravel's auto-generated
        // name for this 3-column index (company_to_users_user_id_
        // company_id_category_application_id_unique) is 66 characters —
        // over MySQL's 64-character identifier limit.
        if (! $this->indexExists('company_to_users', 'company_to_users_user_company_category_unique')) {
            Schema::table('company_to_users', function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'company_id', 'category_application_id'],
                    'company_to_users_user_company_category_unique'
                );
            });
        }

        if ($this->indexExists('company_to_users', 'company_to_users_user_id_company_id_unique')) {
            Schema::table('company_to_users', function (Blueprint $table) {
                $table->dropUnique('company_to_users_user_id_company_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (! $this->indexExists('company_to_users', 'company_to_users_user_id_company_id_unique')) {
            Schema::table('company_to_users', function (Blueprint $table) {
                $table->unique(['user_id', 'company_id']);
            });
        }

        if ($this->indexExists('company_to_users', 'company_to_users_user_company_category_unique')) {
            Schema::table('company_to_users', function (Blueprint $table) {
                $table->dropUnique('company_to_users_user_company_category_unique');
            });
        }

        if (Schema::hasColumn('company_to_users', 'category_application_id')) {
            Schema::table('company_to_users', function (Blueprint $table) {
                $table->dropForeign(['category_application_id']);
                $table->dropColumn('category_application_id');
            });
        }
    }

    /**
     * MySQL-specific: Schema::hasColumn() has a first-class helper, but
     * there's no equivalent for "does this named index exist" without
     * doctrine/dbal (not installed — see prior migrations in this repo).
     * SHOW INDEX works directly against the connection instead.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        $result = $connection->select(
            'SHOW INDEX FROM ' . $connection->getTablePrefix() . $table . ' WHERE Key_name = ?',
            [$indexName]
        );

        return count($result) > 0;
    }
};
