<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\HelpCenterAnswer — one message in a ticket's reply
 * thread. Deliberately a flat "one row per message" shape (like a chat),
 * not a single "answer" column on help_centers: both the ticket's own
 * user AND any superadmin can post into this table against the same
 * help_centers_id, and user_id records who authored THIS particular
 * message (not who owns the ticket) — the UI tells them apart by
 * comparing this row's user_id to the parent ticket's user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_center_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('help_centers_id')
                ->constrained('help_centers')
                ->cascadeOnDelete();

            // Not nullable: unlike help_centers.user_id (which may be a
            // walk-in complaint with no account), every reply is
            // necessarily typed by *somebody* logged in — either the
            // ticket's own user or a superadmin.
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('answers');
            $table->string('status', 20)->default('active'); // active | inactive
            $table->dateTime('date_answers');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_center_answers');
    }
};
