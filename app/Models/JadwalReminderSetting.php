<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris pengaturan pengingat WA Jadwal PER COMPANY (company_id
 * unique) -- device pengirim, template pesan, kapan pengingat dikirim
 * (remind_value + remind_unit sebelum start_time), dan siapa yang
 * dihubungi (remind_target). Diedit lewat App\Http\Controllers\Jadwal\
 * JadwalReminderSettingController, dibaca oleh App\Console\Commands\
 * DispatchDueJadwalReminders & App\Jobs\SendJadwalReminder.
 *
 * Baris yang sama juga menyimpan pengaturan notifikasi WA balasan
 * Approve/Reject App\Models\JadwalKelasRescheduleRequest (kolom
 * reschedule_notify_pengajar / _requester / _admin, dan
 * wa_message_template_id_reschedule_approved / _rejected) -- lihat
 * migration add_reschedule_notification_settings_to_..._table.php's
 * docblock untuk kenapa 3 checkbox independen + 2 template terpisah,
 * bukan 1 kolom target seperti remind_target). Dibaca oleh
 * App\Http\Controllers\Jadwal\JadwalRescheduleRequestController::
 * sendRescheduleNotifications() -- fitur berbeda, cuma numpang satu
 * baris pengaturan yang sama per keputusan diskusi (satu halaman
 * "Pengaturan Pengingat" Jadwal untuk keduanya).
 *
 * CHAT_CATEGORY_NAMES adalah satu-satunya tempat nama kategori
 * "Chat"/"WhatsApp" didefinisikan untuk kebutuhan gating fitur
 * pengingat Jadwal -- dipakai bareng oleh controller (tampilkan/
 * sembunyikan halaman), job (guard sebelum kirim), dan
 * App\Providers\AppServiceProvider's menu composer (tampilkan/
 * sembunyikan menu), supaya daftar kategori ini tidak terduplikasi di
 * banyak tempat. Lihat App\Services\PackageLimitService::
 * hasActiveCategoryPackage() untuk cara ini dipakai, dan
 * App\Http\Middleware\EnsureActivePackage's docblock untuk kenapa gate
 * Chat yang SUDAH ADA sengaja TIDAK memakai filter kategori ini (belum
 * semua package existing ditandai kategorinya) -- fitur baru ini aman
 * memakainya sejak awal karena tidak ada customer existing yang
 * bergantung padanya.
 *
 * Update 7 September 2026 (permintaan user: "kita siapin fitur biar
 * admin set sendiri mngkn mau ditambahkan 1 hari sblmnya 6 jam
 * sblmnya") -- kolom `remind_value`/`remind_unit` DI BAWAH INI
 * **DEPRECATED, JANGAN DIPAKAI KODE BARU**: satu company sekarang bisa
 * punya BANYAK waktu pengingat lewat `rules()` (hasMany
 * App\Models\JadwalReminderRule) -- lihat docblock lengkap migration
 * create_jadwal_reminder_rules_table.php untuk alasan & migrasi
 * datanya. Kolom lama TETAP ADA di skema (tidak di-drop) MURNI sebagai
 * data historis/fallback, bukan sumber kebenaran lagi -- method
 * `remindMinutesBefore()` yang dulu membacanya SUDAH DIHAPUS dari
 * class ini (satu-satunya pemakainya, App\Console\Commands\
 * DispatchDueJadwalReminders, sekarang loop `rules()` dan pakai
 * App\Models\JadwalReminderRule::minutesBefore() per baris).
 * `remind_notify_pengajar_time` (kolom baru, default `'19:00'`) --
 * permintaan terpisah ("kirim rekap tambahkan jam, jam brp mau
 * dikirim") -- jam kirim rekap H-1 ke pengajar, PER COMPANY (sebelumnya
 * hardcode global di bootstrap/app.php's scheduler, lihat docblock
 * migration add_pengajar_reminder_time_to_....php & App\Console\
 * Commands\DispatchJadwalPengajarDailyReminders).
 */
class JadwalReminderSetting extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_reminder_settings';

    public const CHAT_CATEGORY_NAMES = ['Chat', 'WhatsApp', 'Whatsapp Blast'];

    public const TARGET_PARENT = 'parent';

    public const TARGET_STUDENT = 'student';

    public const TARGET_BOTH = 'both';

    public const TARGETS = [self::TARGET_PARENT, self::TARGET_STUDENT, self::TARGET_BOTH];

    public const UNIT_HOURS = 'hours';

    public const UNIT_DAYS = 'days';

    public const UNITS = [self::UNIT_HOURS, self::UNIT_DAYS];

    protected $fillable = [
        'company_id',
        'enabled',
        'device_id',
        'wa_message_template_id',
        'remind_value',
        'remind_unit',
        'remind_target',
        'remind_notify_pengajar',
        'remind_notify_pengajar_time',
        'wa_message_template_id_pengajar',
        'pengajar_request_keyword',
        'reschedule_notify_pengajar',
        'reschedule_notify_requester',
        'reschedule_notify_admin',
        'wa_message_template_id_reschedule_approved',
        'wa_message_template_id_reschedule_rejected',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'remind_value' => 'integer',
        'remind_notify_pengajar' => 'boolean',
        'reschedule_notify_pengajar' => 'boolean',
        'reschedule_notify_requester' => 'boolean',
        'reschedule_notify_admin' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function waMessageTemplate(): BelongsTo
    {
        return $this->belongsTo(WaMessageTemplate::class);
    }

    /** Template rekap jadwal untuk PENGAJAR (H-1 otomatis & request manual by WA) -- lihat spec Jadwal v2 poin 9/10. */
    public function waMessageTemplatePengajar(): BelongsTo
    {
        return $this->belongsTo(WaMessageTemplate::class, 'wa_message_template_id_pengajar');
    }

    public function waMessageTemplateRescheduleApproved(): BelongsTo
    {
        return $this->belongsTo(WaMessageTemplate::class, 'wa_message_template_id_reschedule_approved');
    }

    public function waMessageTemplateRescheduleRejected(): BelongsTo
    {
        return $this->belongsTo(WaMessageTemplate::class, 'wa_message_template_id_reschedule_rejected');
    }

    /**
     * Update 7 September 2026 -- lihat docblock class di atas. Diurutkan
     * `remind_value`/`remind_unit` (bukan created_at) MURNI supaya
     * urutan tampil di UI (& log/debug) konsisten dan predictable, bukan
     * urutan-dibuat yang bisa acak kalau admin edit/hapus/tambah rule
     * berkali-kali.
     */
    public function rules(): HasMany
    {
        return $this->hasMany(JadwalReminderRule::class, 'jadwal_reminder_setting_id')
            ->orderBy('remind_unit')
            ->orderBy('remind_value');
    }

}
