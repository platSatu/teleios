<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Update 7 September 2026 (permintaan user: "kita siapin fitur biar
 * admin set sendiri mngkn mau ditambahkan 1 hari sblmnya 6 jam
 * sblmnya") -- SATU baris = SATU waktu pengingat ("kirim N jam/hari
 * sebelumnya"), banyak baris bisa dimiliki SATU App\Models\
 * JadwalReminderSetting (hasMany, lihat `rules()` di sana) supaya admin
 * bisa nambah lebih dari satu waktu pengingat sekaligus lewat UI
 * "+ Tambah Waktu Pengingat" (resources/views/jadwal/settings/edit.blade.php).
 * Lihat docblock lengkap migration create_jadwal_reminder_rules_table.php
 * untuk alasan desain & migrasi dari kolom remind_value/remind_unit
 * lama di jadwal_reminder_settings.
 *
 * Dipakai App\Console\Commands\DispatchDueJadwalReminders (satu Jadwal
 * Kelas bisa punya SATU baris App\Models\JadwalKelasReminderLog PER
 * rule di sini, lihat `jadwal_reminder_rule_id` di tabel itu) & App\Jobs\
 * SendJadwalReminder (dispatch per (jadwal_kelas_id, rule_id)).
 */
class JadwalReminderRule extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_reminder_rules';

    protected $fillable = [
        'jadwal_reminder_setting_id',
        'remind_value',
        'remind_unit',
    ];

    protected $casts = [
        'remind_value' => 'integer',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(JadwalReminderSetting::class, 'jadwal_reminder_setting_id');
    }

    /** Selisih waktu (dalam menit) sebelum start_time pengingat ini harus dikirim -- lihat App\Models\JadwalReminderSetting::remindMinutesBefore() (versi single-rule lama, sekarang digantikan method ini per-rule). */
    public function minutesBefore(): int
    {
        return $this->remind_unit === JadwalReminderSetting::UNIT_HOURS
            ? $this->remind_value * 60
            : $this->remind_value * 60 * 24;
    }

    /** Label ringkas dipakai log/debug, mis. "1 hari" / "6 jam". */
    public function label(): string
    {
        $unitLabel = $this->remind_unit === JadwalReminderSetting::UNIT_HOURS ? 'jam' : 'hari';

        return "{$this->remind_value} {$unitLabel}";
    }
}
