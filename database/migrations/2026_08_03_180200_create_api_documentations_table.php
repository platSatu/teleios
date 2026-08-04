<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\ApiDocumentation — one article per endpoint in the
     * public WhatsApp API documentation site (GET /dokumentasi, no login
     * required — see PublicDocumentationController). Superadmin-managed
     * via Superadmin\ApiDocumentationController
     * (dashboard/superadmin/wa-api-dokumentasi/articles).
     */
    public function up(): void
    {
        Schema::create('api_documentations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // restrictOnDelete, same reasoning as application_menus.
            // category_application_id — a category that already has
            // articles under it can't be deleted out from under them.
            $table->foreignUuid('category_documentation_id')
                ->constrained('category_documentations')
                ->restrictOnDelete();

            $table->string('title');
            $table->string('slug')->unique();

            // Plain string, not an enum — keeps this catalog free to
            // describe any HTTP method without a migration every time
            // (mirrors wa_message_auto_replies.match_type-style plain
            // strings elsewhere in this app, validated in the controller
            // instead of at the DB level).
            $table->string('method', 10)->default('POST');
            $table->string('endpoint');

            $table->text('description')->nullable();

            // Free-form text (usually a JSON or curl snippet) rather than
            // a `json` column — this is documentation copy meant to be
            // displayed close to verbatim, not data this app ever reads
            // back structurally.
            $table->text('request_example')->nullable();
            $table->text('response_example')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_documentations');
    }
};
