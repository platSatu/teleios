<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Waktu pengingat per App\Models\Tagihan (H-7/H-3/H-1/hari-H, dst) --
 * konfirmasi user 22 September 2026: scope-nya per TAGIHAN individual
 * (bukan per category seperti denda), "user bisa setting tiap tagihan
 * berbeda-beda".
 *
 * Struktur & konvensi kolom sengaja disamakan PERSIS dengan
 * App\Models\JadwalReminderRule (satu baris = satu waktu, remind_value
 * + remind_unit, banyak baris per induk) supaya nanti gampang dipakai
 * ulang App\Console\Commands\* yang serupa DispatchDueJadwalReminders.
 *
 * PENTING: tabel ini CUMA menyimpan JADWAL pengingatnya (kapan mau
 * dikirim) -- belum ada mekanisme pengiriman WhatsApp apa pun yang
 * membaca tabel ini sekarang (permintaan user eksplisit 22 September
 * 2026: "kita jangan sambungkan dulu ya dengan whatsapp"). Disiapkan
 * dari awal supaya begitu bagian pengiriman digarap, cukup tambah job
 * baru yang query tabel ini + App\Models\TagihanReminderLog (menyusul)
 * -- tidak perlu migration/restrukturisasi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_reminder_rule', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tagihan_id')
                ->constrained(table: 'tagihan', indexName: 'tagihan_reminder_rule_tagihan_fk')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('remind_value');

            // 'hours' | 'days' -- sama persis konvensi App\Models\
            // JadwalReminderRule.
            $table->string('remind_unit', 10);

            $table->timestamps();

            $table->index('tagihan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_reminder_rule');
    }
};
