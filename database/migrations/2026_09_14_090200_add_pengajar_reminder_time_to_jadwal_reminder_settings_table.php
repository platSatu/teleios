<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Update 7 September 2026 (permintaan user: "kirim rekap tambahkan jam,
 * jam brp mau dikirim ketika di slide tampilkan jam brp mau dikirim").
 * Jam kirim rekap H-1 ke pengajar SEBELUMNYA hardcode `19:00` langsung
 * di scheduler (`bootstrap/app.php`'s `$schedule->command('jadwal:
 * dispatch-pengajar-reminders')->dailyAt('19:00')`) -- SATU jam yang
 * sama untuk SEMUA company, tidak bisa diatur admin sama sekali,
 * padahal CLAUDE.md item #15 spec eksplisit bilang "semua harus via
 * setting, tidak ada yang hardcode". Kolom baru ini bikin jam kirimnya
 * per-company (default `19:00`, SAMA PERSIS dengan nilai hardcode lama,
 * jadi company yang sudah jalan TIDAK berubah perilakunya sama sekali
 * sampai admin sengaja mengubahnya).
 *
 * String `H:i` (bukan kolom `time`) -- konsisten dengan pola kolom jam
 * lain di project ini (mis. `jadwal_branch_settings.jam_buka`/
 * `jam_tutup` juga string), lebih gampang dibandingkan langsung dengan
 * `now()->format('H:i')` di App\Console\Commands\
 * DispatchJadwalPengajarDailyReminders (lihat perubahan di sana --
 * scheduler-nya sendiri diganti dari `dailyAt('19:00')` jadi
 * `everyFifteenMinutes()`, command yang sekarang MEMBANDINGKAN jam
 * saat ini terhadap kolom ini per company, bukan lagi mengandalkan cron
 * fire persis di jam yang diinginkan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_reminder_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('jadwal_reminder_settings', 'remind_notify_pengajar_time')) {
                $table->string('remind_notify_pengajar_time', 5)->default('19:00')->after('remind_notify_pengajar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_reminder_settings', function (Blueprint $table) {
            $table->dropColumn('remind_notify_pengajar_time');
        });
    }
};
