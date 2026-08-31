<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris pengaturan pengingat WA Jadwal PER COMPANY (company_id
 * unique) -- device pengirim, template pesan, kapan pengingat dikirim
 * (remind_value + remind_unit sebelum start_time), dan siapa yang
 * dihubungi (remind_target). Diedit lewat App\Http\Controllers\Jadwal\
 * JadwalReminderSettingController, dibaca oleh App\Console\Commands\
 * DispatchDueJadwalReminders & App\Jobs\SendJadwalReminder.
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
 */
class JadwalReminderSetting extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_reminder_settings';

    public const CHAT_CATEGORY_NAMES = ['Chat', 'WhatsApp'];

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
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'remind_value' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function waMessageTemplate(): BelongsTo
    {
        return $this->belongsTo(WaMessageTemplate::class);
    }

    /** Selisih waktu (dalam menit) sebelum start_time pengingat harus dikirim. */
    public function remindMinutesBefore(): int
    {
        return $this->remind_unit === self::UNIT_HOURS
            ? $this->remind_value * 60
            : $this->remind_value * 60 * 24;
    }
}
