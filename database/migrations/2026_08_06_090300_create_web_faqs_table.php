<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WebFaq — a flat FAQ list (no category grouping,
     * unlike articles/videos) for the (future) public web site.
     * Superadmin-managed via Superadmin\Web\FaqController
     * (dashboard/superadmin/web/faqs).
     */
    public function up(): void
    {
        Schema::create('web_faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->longText('descriptions');
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_faqs');
    }
};
