<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus kolom `language` dari `wa_message_templates` (ditambahkan di
 * add_builder_columns_to_wa_message_templates_table.php sebagai
 * ISO-639-1-ish code, default 'id'). Diputuskan dihapus total (bukan
 * cuma disembunyikan di form) karena setelah ditelusuri, kolom ini
 * TIDAK PERNAH benar-benar dibaca di manapun -- tidak dipakai untuk
 * filter/pemilihan template, tidak dipakai App\Models\WaMessageTemplate::
 * composedMessage()/scopeUsable(), tidak dipakai moderasi AI. Satu-
 * satunya "pemakaian" sebelumnya cuma validasi `required` di
 * App\Http\Controllers\Chat\MessageTemplateController -- murni
 * dekoratif, aman dihapus sepenuhnya (lihat diskusi: "hapus bahasa
 * saja karena tidak pengaruh apa-apa").
 *
 * Beda dengan section "Kontak / Tujuan Pengiriman" pada form yang
 * SAMA (kolom `recipients` dkk) -- itu SENGAJA TIDAK ikut dihapus,
 * karena ternyata jadi satu-satunya sumber penerima untuk Pesan
 * Terjadwal yang memakai toggle "Gunakan Template" (lihat
 * MessageScheduleController::finalize()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_message_templates', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_templates', function (Blueprint $table) {
            $table->string('language', 10)->default('id')->after('name');
        });
    }
};
