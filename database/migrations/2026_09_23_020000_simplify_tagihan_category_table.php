<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 23 September 2026 redesign (user request): simplify tagihan_category.
 *
 * Dropped:
 * - `is_recurring` -- was always just a UI label with no real effect
 *   (see create_tagihan_category_table.php's docblock -- it never
 *   triggered any scheduled job), confirmed dead weight.
 * - `default_amount` -- amount is always entered fresh per Tagihan on
 *   the create form; this "default" was never actually consumed
 *   anywhere to prefill it.
 * - `denda_enabled` -- replaced by `denda_mode` below. Denda config
 *   moves from a free-form multi-tier system (tagihan_denda_tier,
 *   still present but no longer used by the new flow) to exactly one
 *   of 3 fixed modes per category, picked on the new "Setting Tagihan"
 *   page (see App\Http\Controllers\Tagihan\TagihanCategorySettingController).
 *
 * Added, all nullable (no denda_mode = denda off for that category,
 * same as denda_enabled=false used to mean):
 * - `deskripsi` -- free-text note admins can leave on a category,
 *   replaces the fields dropped above in the create/edit form.
 * - `denda_mode` -- 'flat' | 'persentase' | 'persentase_flat', see
 *   App\Models\TagihanCategory's DENDA_MODE_* constants and
 *   App\Models\TagihanPenerima::hitungDenda() for how each is
 *   computed.
 * - `denda_flat_amount` -- flat rupiah amount. Used alone by 'flat'
 *   mode (charged once, however late); used as the per-day flat
 *   component by 'persentase_flat' once `denda_persen_sampai_hari` is
 *   passed.
 * - `denda_persen` -- percentage of the Tagihan's amount. Used by
 *   'persentase' (per denda_persen_frekuensi) and by 'persentase_flat'
 *   (always per day, for the first `denda_persen_sampai_hari` days).
 * - `denda_persen_frekuensi` -- 'per_hari' | 'per_bulan', only read
 *   for 'persentase' mode (a checkbox on the Setting page).
 * - `denda_persen_sampai_hari` -- only read for 'persentase_flat':
 *   how many days late the percentage-only portion applies before the
 *   flat component starts stacking on top of it.
 * - `invoice_prefix` -- short text prefix (e.g. "PST") a random
 *   invoice number is generated from per Tagihan, shown to the
 *   paying customer -- see Setting Tagihan tab 3.
 * - `notifikasi_email_aktif` -- whether email notifications are sent
 *   for Tagihan under this category, alongside the WA notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_category', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'default_amount', 'denda_enabled']);

            $table->text('deskripsi')->nullable()->after('name');

            $table->string('denda_mode', 20)->nullable()->after('deskripsi');
            $table->decimal('denda_flat_amount', 15, 2)->nullable()->after('denda_mode');
            $table->decimal('denda_persen', 15, 4)->nullable()->after('denda_flat_amount');
            $table->string('denda_persen_frekuensi', 10)->nullable()->after('denda_persen');
            $table->unsignedSmallInteger('denda_persen_sampai_hari')->nullable()->after('denda_persen_frekuensi');

            $table->string('invoice_prefix', 20)->nullable()->after('denda_persen_sampai_hari');
            $table->boolean('notifikasi_email_aktif')->default(false)->after('invoice_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_category', function (Blueprint $table) {
            $table->dropColumn([
                'deskripsi',
                'denda_mode',
                'denda_flat_amount',
                'denda_persen',
                'denda_persen_frekuensi',
                'denda_persen_sampai_hari',
                'invoice_prefix',
                'notifikasi_email_aktif',
            ]);

            $table->boolean('is_recurring')->default(false);
            $table->decimal('default_amount', 15, 2)->nullable();
            $table->boolean('denda_enabled')->default(false);
        });
    }
};
