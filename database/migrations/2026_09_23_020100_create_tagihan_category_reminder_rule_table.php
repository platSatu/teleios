<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template aturan pengingat per App\Models\TagihanCategory -- 23
 * September 2026 redesign, membalik keputusan 22 September
 * (tagihan_reminder_rule.php's docblock) yang bilang pengingat itu
 * per-Tagihan saja: sekarang admin atur SEKALI di level category lewat
 * halaman "Setting Tagihan" tab "Pengingat", dan tiap kali Tagihan baru
 * dibuat di bawah category itu (TagihanController::store()), baris di
 * sini disalin jadi baris App\Models\TagihanReminderRule milik Tagihan
 * itu -- persis pola yang sama dengan bagaimana `amount`/`pakai_denda`
 * sudah lebih dulu disalin dari category ke Tagihan supaya tidak
 * berubah diam-diam kalau template-nya diedit belakangan.
 *
 * tagihan_reminder_rule (per-Tagihan) TETAP ada dan TETAP jadi yang
 * dibaca job pengiriman pengingat sebenarnya -- tabel ini cuma
 * template, tidak pernah dibaca langsung buat kirim apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_category_reminder_rule', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tagihan_category_id')
                ->constrained(table: 'tagihan_category', indexName: 'tagihan_cat_reminder_rule_cat_fk')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('remind_value');
            $table->string('remind_unit', 10); // 'hours' | 'days'

            $table->timestamps();

            $table->index('tagihan_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_category_reminder_rule');
    }
};
