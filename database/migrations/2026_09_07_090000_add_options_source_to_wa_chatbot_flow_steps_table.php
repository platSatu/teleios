<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber pilihan OPSIONAL untuk step bertipe 'choice' -- lihat
 * App\Models\WaChatbotFlowStep::OPTIONS_SOURCE_* & App\Services\Chat\
 * ChatbotFlowService::resolveOptions(). Nullable & default NULL:
 * semua step yang sudah ada (dan semua step baru yang admin tidak
 * pilih apa-apa) tetap pakai kolom `options` yang sudah ada (pilihan
 * statis ditulis manual di flow builder) -- tidak ada perubahan
 * perilaku sama sekali untuk flow yang tidak opt-in ke fitur ini.
 *
 * Kalau diisi, `options` (kolom lama) diabaikan untuk step itu --
 * pilihannya di-generate saat itu juga dari data Jadwal murid yang
 * sedang chat (mis. jadwal dia yang akan datang, atau jam kosong
 * pengajar yang sama) alih-alih ditulis manual admin. Ini kolom
 * paling sederhana yang cukup untuk kebutuhan itu -- belum perlu
 * tabel/konsep baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_chatbot_flow_steps', function (Blueprint $table) {
            $table->string('options_source', 40)->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('wa_chatbot_flow_steps', function (Blueprint $table) {
            $table->dropColumn('options_source');
        });
    }
};
