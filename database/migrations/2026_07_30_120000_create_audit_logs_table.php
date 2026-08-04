<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\AuditLog, which CrudAdmin (app/Helpers/CrudAdmin.php)
     * writes to on every store/update/delete. The table is intentionally
     * append-only: the model blocks updates and deletes at the Eloquent
     * level, and there's no updated_at column here to match — a real
     * audit trail has to be tamper-evident, not just descriptive.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            // UUID primary key, matching AuditLog's incrementing=false /
            // keyType='string' and the rest of the app's convention
            // (users.id is also a UUID).
            $table->uuid('id')->primary();

            $table->string('actor_type')->nullable();
            $table->string('actor_id')->nullable()->index();

            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id')->index();

            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // No foreign key on actor_id: an audit entry must survive
            // even if the actor (or the record it refers to) is later
            // deleted — that's the whole point of an audit log.
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
