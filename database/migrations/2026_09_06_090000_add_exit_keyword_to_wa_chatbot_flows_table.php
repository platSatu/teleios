<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kata kunci keluar paksa, OPSIONAL per flow -- lihat App\Models\
 * WaChatbotFlow::matchesExit() & App\Services\Chat\ChatbotFlowService::
 * exitIfRequested(). Nullable & default NULL supaya SEMUA flow yang
 * sudah ada sebelum kolom ini (termasuk milik company lain) tidak
 * berubah perilakunya sedikit pun -- fitur ini cuma aktif untuk flow
 * yang company-nya sendiri isi kata kuncinya.
 *
 * Dipisah dari trigger_keyword (bukan menambah match_type baru ke
 * kolom yang sama) karena keduanya punya arti & siklus hidup yang
 * beda: trigger_keyword cuma dicek saat TIDAK ada sesi aktif (memulai
 * flow), exit_keyword cuma dicek saat SEDANG ada sesi aktif
 * (mengakhirinya paksa) -- lihat ChatbotFlowService::handleIncoming().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_chatbot_flows', function (Blueprint $table) {
            $table->string('exit_keyword')->nullable()->after('trigger_match_type');
        });
    }

    public function down(): void
    {
        Schema::table('wa_chatbot_flows', function (Blueprint $table) {
            $table->dropColumn('exit_keyword');
        });
    }
};
