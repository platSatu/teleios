<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor HP pengirim, dinormalisasi (App\Support\PhoneNumber::normalize()),
 * diisi sekali saat sesi dimulai (App\Services\Chat\ChatbotFlowService::
 * start()) dari `sender_phone` yang dikirim Go bersama webhook -- BUKAN
 * dari chat_jid, karena chat_jid tidak selalu berisi nomor HP sama sekali
 * (WhatsApp addressing lewat "...@lid" untuk sebagian chat, ditemukan
 * langsung di produksi: chat_jid "141013138055357@lid" sama sekali tidak
 * mengandung digit nomor HP, padahal sender_phone-nya "6287748532308").
 *
 * Nullable & tidak diisi ulang lagi setelah sesi berjalan -- sesi lama
 * yang sudah ada sebelum migration ini tetap jalan (lihat
 * ChatbotFlowService::senderPhone()'s fallback ke chat_jid), dan payload
 * webhook lama yang belum kirim sender_phone (build Go lama) juga tidak
 * error, cuma jatuh ke fallback yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_chatbot_states', function (Blueprint $table) {
            $table->string('sender_phone', 32)->nullable()->after('chat_jid');
        });
    }

    public function down(): void
    {
        Schema::table('wa_chatbot_states', function (Blueprint $table) {
            $table->dropColumn('sender_phone');
        });
    }
};
