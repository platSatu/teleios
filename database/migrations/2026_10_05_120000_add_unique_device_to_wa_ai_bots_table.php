<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1 device = 1 AI Bot (5 Oktober 2026). Dulu satu device bisa punya
 * beberapa AI Bot dan webhook mengambil yang "pertama" -- tidak pasti AI
 * mana yang menjawab. Kalau data lama masih dobel, migration berhenti
 * dengan pesan jelas (dirapikan manual dulu, tidak dihapus otomatis).
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('wa_ai_bots')
            ->select('device_id')
            ->groupBy('device_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('device_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Masih ada device dengan lebih dari 1 AI Bot: '.$duplicates->implode(', ').'. Hapus/pindahkan yang dobel dulu, lalu jalankan migrate lagi.');
        }

        Schema::table('wa_ai_bots', function (Blueprint $table) {
            $table->unique('device_id');
        });
    }

    public function down(): void
    {
        Schema::table('wa_ai_bots', function (Blueprint $table) {
            $table->dropUnique(['device_id']);
        });
    }
};
