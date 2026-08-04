<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a company mark one "Auto Reply (Kata Kunci)" rule per device
     * as the fallback/default reply — sent when an incoming message
     * doesn't match ANY keyword rule (see
     * App\Http\Controllers\Api\WaIncomingMessageWebhookController),
     * instead of the bot silently doing nothing. This is what makes a
     * numbered menu ("1. Jadwal, 2. Pembayaran, 3. Daftar User — ketik
     * salah satu nomor") actually reachable: the default rule IS that
     * menu text, and "1"/"2"/"3" are just ordinary exact-match keyword
     * rules underneath it — no new conversation-flow engine needed for
     * this single-level case.
     *
     * `keyword` is relaxed to nullable to go with it: a default rule
     * doesn't match against any specific keyword, so forcing it to hold
     * a meaningless placeholder value would be worse than just allowing
     * null (enforced by MessageAutoReplyController's validator instead:
     * keyword is still required for every NON-default rule).
     */
    public function up(): void
    {
        Schema::table('wa_message_auto_replies', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('match_type');
        });

        DB::statement('ALTER TABLE wa_message_auto_replies MODIFY keyword VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // Any existing NULL keyword (from a default rule) would violate
        // NOT NULL on the way back down — backfill a visible placeholder
        // first so the rollback itself doesn't throw.
        DB::table('wa_message_auto_replies')->whereNull('keyword')->update(['keyword' => '(default)']);

        DB::statement('ALTER TABLE wa_message_auto_replies MODIFY keyword VARCHAR(255) NOT NULL');

        Schema::table('wa_message_auto_replies', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
