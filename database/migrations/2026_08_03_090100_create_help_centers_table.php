<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\HelpCenter — one support ticket. number_ticket is the
 * human-facing reference (e.g. HP260803A1B2, see
 * HelpCenter::generateNumberTicket()); user_id is whoever filed the
 * complaint (nullable so a superadmin can log a ticket on a user's
 * behalf without one, or for a walk-in/phone complaint with no account).
 * category_help_centers_id is required (restrictOnDelete, same rule as
 * application_menus.category_application_id — a category with tickets
 * against it can't be deleted out from under them). The reply thread
 * itself lives in help_center_answers (see that migration) — this table
 * only holds the ticket's own fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_centers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('number_ticket')->unique();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('category_help_centers_id')
                ->constrained('category_help_centers')
                ->restrictOnDelete();

            $table->string('name');
            $table->text('description');

            // Path on the 'public' disk (Storage::disk('public')), same
            // convention as avatars/company logos elsewhere in this app.
            // Nullable: an attachment is optional on a ticket.
            $table->string('attachment')->nullable();

            $table->dateTime('open_date');
            $table->dateTime('close_date')->nullable();

            // active | inactive | open | close — deliberately a plain
            // string (not two separate booleans) since the business
            // rule for these four values was specified as one status
            // field; validated in the controller, not at the DB level.
            $table->string('status', 20)->default('open');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_centers');
    }
};
