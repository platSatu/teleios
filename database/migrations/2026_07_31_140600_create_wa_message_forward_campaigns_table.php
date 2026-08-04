<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaMessageForwardCampaign ("Forward Pesan" /
     * "Campaign Broadcast" — the two were merged here since forwarding
     * one piece of content to many recipients IS a broadcast campaign).
     * message_type/message_content/recipients were added to the
     * original spec (title/description/group_contact/status only): a
     * campaign needs to know what it's sending and to whom.
     * scheduled_at was added so a campaign can be queued for later
     * rather than only "right now".
     */
    public function up(): void
    {
        Schema::create('wa_message_forward_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('device_id', 36);

            $table->string('title');
            $table->text('description')->nullable();

            // text | image | document
            $table->string('message_type', 20)->default('text');
            $table->text('message_content');

            // Array of phone numbers and/or WhatsApp group JIDs picked
            // via the checkbox list on the form. Stored as JSON rather
            // than a pivot table — there's no local `contacts`/`groups`
            // table to pivot against (both live in the Go backend), so
            // the selection is just a flat list of target identifiers.
            $table->json('recipients');

            $table->dateTime('scheduled_at')->nullable();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_forward_campaigns');
    }
};
