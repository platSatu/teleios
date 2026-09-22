<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak "pengingat ini sudah/belum dikirim ke siapa" -- satu baris per
 * (App\Models\TagihanPenerima, App\Models\TagihanReminderRule) yang
 * SEHARUSNYA dikirim, dibuat begitu waktu pengingatnya tiba. Struktur
 * disamakan persis dengan App\Models\JadwalKelasReminderLog supaya
 * nanti job pengirimnya bisa dicontoh langsung dari App\Jobs\
 * SendJadwalReminder tanpa perlu pola baru.
 *
 * Sama seperti tagihan_reminder_rule di atas: tabel ini disiapkan lebih
 * dulu, TIDAK ada job yang mengisi/membaca tabel ini sekarang (belum
 * disambungkan ke WhatsApp -- lihat docblock create_tagihan_reminder_rule_table.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_reminder_log', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tagihan_penerima_id')
                ->constrained(table: 'tagihan_penerima', indexName: 'tagihan_reminder_log_penerima_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('tagihan_reminder_rule_id')
                ->constrained(table: 'tagihan_reminder_rule', indexName: 'tagihan_reminder_log_rule_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'tagihan_reminder_log_company_fk')
                ->cascadeOnDelete();

            // pending | sent | failed
            $table->string('status', 20)->default('pending');

            $table->string('message_id')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['tagihan_penerima_id', 'tagihan_reminder_rule_id'],
                'tagihan_reminder_log_unique'
            );
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_reminder_log');
    }
};
