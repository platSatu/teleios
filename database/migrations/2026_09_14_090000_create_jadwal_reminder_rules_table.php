<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Update 7 September 2026 (permintaan user: "kirim berapa lama sblmnya
 * itu di buat add row otomatis, soalnya kalau yang skrg itu misalkan
 * mau dikirim 1 jam sblmnya, ya uda gitu. kita siapin fitur biar admin
 * set sendiri mngkn mau ditambahkan 1 hari sblmnya 6 jam sblmnya").
 *
 * App\Models\JadwalReminderSetting SEBELUMNYA cuma punya SATU pasang
 * `remind_value`/`remind_unit` (kolom langsung di tabel itu) -- satu
 * company cuma bisa punya SATU waktu pengingat ("1 jam sebelumnya" ATAU
 * "1 hari sebelumnya", tidak bisa dua-duanya). Tabel baru ini
 * memisahkan "kapan mau dikirim" jadi baris-baris independen (hasMany
 * dari satu App\Models\JadwalReminderSetting, lihat `rules()`) supaya
 * admin bisa nambah SEBANYAK yang dia mau lewat UI "+ Tambah" (lihat
 * resources/views/jadwal/settings/edit.blade.php & JadwalReminderSettingController::update()'s
 * syncReminderRules()) -- misal "1 hari sebelumnya" DAN "6 jam
 * sebelumnya" SEKALIGUS, masing-masing jadi pengingat WA terpisah
 * (lihat perubahan App\Console\Commands\DispatchDueJadwalReminders &
 * App\Jobs\SendJadwalReminder di migration berikutnya soal
 * `jadwal_reminder_rule_id` di jadwal_kelas_reminder_logs).
 *
 * Kolom `remind_value`/`remind_unit` LAMA di jadwal_reminder_settings
 * SENGAJA TIDAK DIHAPUS (lihat migration ini TIDAK menyentuh tabel itu
 * sama sekali) -- dibiarkan apa adanya sebagai data historis/fallback,
 * cuma tidak dipakai lagi oleh kode baru (lihat docblock
 * JadwalReminderSetting::$fillable soal ini). Backfill di bawah
 * menyalin nilai lama itu jadi SATU baris rule pertama per company yang
 * sudah pernah mengisi pengaturan -- supaya company yang sudah jalan
 * TIDAK tiba-tiba kehilangan pengingatnya gara-gara migration ini
 * (perilaku lama "1 kali pengingat sesuai remind_value/remind_unit
 * lama" tetap jalan persis sama sampai admin sengaja menambah/mengubah
 * baris rule-nya sendiri).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jadwal_reminder_rules')) {
            Schema::create('jadwal_reminder_rules', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->foreignUuid('jadwal_reminder_setting_id')
                    ->constrained('jadwal_reminder_settings')
                    ->cascadeOnDelete();

                $table->unsignedSmallInteger('remind_value');

                // 'hours' | 'days' -- sama persis dengan
                // JadwalReminderSetting::UNIT_HOURS/UNIT_DAYS lama.
                $table->string('remind_unit', 10);

                $table->timestamps();

                $table->index('jadwal_reminder_setting_id');
            });
        }

        // Backfill APA ADANYA (lihat docblock di atas) -- dilakukan lewat
        // DB::table() mentah (bukan Eloquent model), pola yang sama
        // dipakai migration backfill lain di project ini (lihat
        // create_referral_codes_table.php), supaya migration ini tetap
        // jalan benar walau App\Models\JadwalReminderSetting/
        // JadwalReminderRule berubah bentuk nanti.
        $now = now();

        DB::table('jadwal_reminder_settings')
            ->whereNotNull('remind_value')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('jadwal_reminder_rules')
                    ->whereColumn('jadwal_reminder_rules.jadwal_reminder_setting_id', 'jadwal_reminder_settings.id');
            })
            ->orderBy('id')
            ->get(['id', 'remind_value', 'remind_unit'])
            ->each(function ($setting) use ($now) {
                DB::table('jadwal_reminder_rules')->insert([
                    'id' => (string) Str::uuid(),
                    'jadwal_reminder_setting_id' => $setting->id,
                    'remind_value' => $setting->remind_value,
                    'remind_unit' => $setting->remind_unit ?: 'days',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_reminder_rules');
    }
};
