<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaMessageTemplate — reusable WhatsApp message
     * templates a company can save under Chat > Pengaturan > Pesan > WA
     * Template, then pick from (instead of typing the body out again
     * every time) on the "Pesan Terjadwal" form. `company_id` is a real
     * foreignUuid + FK (not a bare unconstrained char(36)) to match the
     * scoping convention every other company-owned table in this app
     * already uses (wa_message_schedules, branch_offices, company_roles,
     * ...) — see App\Http\Controllers\Chat\MessageTemplateController's
     * ownedCompanyOrFail().
     */
    public function up(): void
    {
        Schema::create('wa_message_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('template');
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_templates');
    }
};
