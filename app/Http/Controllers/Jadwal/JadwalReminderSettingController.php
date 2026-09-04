<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\JadwalReminderRule;
use App\Models\JadwalReminderSetting;
use App\Models\WaMessageTemplate;
use App\Services\Chat\DeviceDirectory;
use App\Services\PackageLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Satu halaman "Pengaturan Pengingat" Jadwal per company (bukan CRUD
 * list -- upsert satu baris App\Models\JadwalReminderSetting). Cuma
 * benar-benar bisa dipakai kalau company punya package aktif kategori
 * Chat/WhatsApp (lihat App\Services\PackageLimitService::
 * hasActiveCategoryPackage()) -- dicek DI DALAM controller ini
 * (bukan middleware route, lihat routes/web.php's docblock di grup
 * 'jadwal.settings'), sengaja untuk kasus seseorang membuka URL ini
 * langsung padahal menu-nya sendiri sudah disembunyikan (lihat
 * resources/views/layouts/partials/menu.blade.php) -- dapat pesan
 * yang jelas ("butuh package Chat/WhatsApp aktif"), bukan raw 403.
 */
class JadwalReminderSettingController extends Controller
{
    use ResolvesCompanyContext;

    public function edit(Request $request, PackageLimitService $packageLimits, DeviceDirectory $devices): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $chatActive = $packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES);

        // ->with('rules') -- update 7 September 2026, fitur multi waktu
        // pengingat: view butuh daftar rule (bukan cuma remind_value/
        // remind_unit tunggal) untuk merender baris "+ Tambah Waktu
        // Pengingat" yang sudah tersimpan.
        $setting = JadwalReminderSetting::where('company_id', $company->id)->with('rules')->first();

        if (! $chatActive) {
            return view('jadwal.settings.edit', [
                'chatActive' => false,
                'setting' => $setting,
                'devices' => collect(),
                'templates' => collect(),
            ]);
        }

        $branchOfficeId = $context->isLockedToBranch() ? $context->branchOffice?->id : null;

        return view('jadwal.settings.edit', [
            'chatActive' => true,
            'setting' => $setting,
            'devices' => $devices->devicesForCompany($company->id, $branchOfficeId),
            'templates' => WaMessageTemplate::where('company_id', $company->id)
                ->where('status', 'active')
                ->where('review_status', 'approved')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, PackageLimitService $packageLimits): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        if (! $packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES)) {
            return redirect()
                ->route('jadwal.settings.edit')
                ->with('error', 'Company Anda belum memiliki package aktif kategori Chat/WhatsApp -- pengingat WA belum bisa diaktifkan.');
        }

        // Update 7 September 2026 (permintaan user: "kita siapin fitur
        // biar admin set sendiri mngkn mau ditambahkan 1 hari sblmnya 6
        // jam sblmnya") -- `remind_value`/`remind_unit` TUNGGAL diganti
        // array `rules` (lihat App\Models\JadwalReminderRule &
        // syncReminderRules() di bawah). `rules.*.id` opsional (kosong =
        // baris baru); dibatasi maksimal 5 baris supaya UI/pesan
        // pengingat tidak membanjiri satu company dengan puluhan WA per
        // sesi. `remind_notify_pengajar_time` (permintaan terpisah
        // "kirim rekap tambahkan jam") divalidasi format `H:i`.
        $validator = Validator::make($request->all(), [
            'enabled' => ['nullable', 'boolean'],
            'device_id' => ['nullable', 'string', 'max:36'],
            'wa_message_template_id' => [
                'nullable', 'uuid', 'exists:wa_message_templates,id',
                $this->templateBelongsToCompanyRule($company),
            ],
            'rules' => ['required', 'array', 'min:1', 'max:5'],
            'rules.*.id' => ['nullable', 'uuid'],
            'rules.*.remind_value' => ['required', 'integer', 'min:1', 'max:720'],
            'rules.*.remind_unit' => ['required', 'in:'.implode(',', JadwalReminderSetting::UNITS)],
            'remind_target' => ['required', 'in:'.implode(',', JadwalReminderSetting::TARGETS)],
            'remind_notify_pengajar' => ['nullable', 'boolean'],
            'remind_notify_pengajar_time' => ['nullable', 'date_format:H:i'],
            'wa_message_template_id_pengajar' => [
                'nullable', 'uuid', 'exists:wa_message_templates,id',
                $this->templateBelongsToCompanyRule($company),
            ],
            'pengajar_request_keyword' => ['nullable', 'string', 'max:50'],
            'reschedule_notify_pengajar' => ['nullable', 'boolean'],
            'reschedule_notify_requester' => ['nullable', 'boolean'],
            'reschedule_notify_admin' => ['nullable', 'boolean'],
            'wa_message_template_id_reschedule_approved' => [
                'nullable', 'uuid', 'exists:wa_message_templates,id',
                $this->templateBelongsToCompanyRule($company),
            ],
            'wa_message_template_id_reschedule_rejected' => [
                'nullable', 'uuid', 'exists:wa_message_templates,id',
                $this->templateBelongsToCompanyRule($company),
            ],
        ]);

        // Tolak pasangan (remind_value, remind_unit) yang duplikat --
        // dua baris "1 hari sebelumnya" sekaligus tidak ada gunanya
        // (bakal jadi 2x klaim/kirim WA yang identik persis, membingungkan
        // orang tua/murid). Dicek di ->after() (bukan aturan per-item)
        // karena butuh membandingkan SEMUA baris satu sama lain.
        $validator->after(function ($validator) use ($request) {
            $seen = [];
            foreach ((array) $request->input('rules', []) as $i => $rule) {
                if (! isset($rule['remind_value'], $rule['remind_unit'])) {
                    continue;
                }

                $key = $rule['remind_unit'].':'.$rule['remind_value'];

                if (isset($seen[$key])) {
                    $validator->errors()->add("rules.{$i}.remind_value", 'Waktu pengingat ini sudah ada di baris lain.');
                } else {
                    $seen[$key] = true;
                }
            }
        });

        if ($validator->fails()) {
            return redirect()->route('jadwal.settings.edit')->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $setting = JadwalReminderSetting::updateOrCreate(
            ['company_id' => $company->id],
            [
                'enabled' => $request->boolean('enabled'),
                'device_id' => $validated['device_id'] ?? null,
                'wa_message_template_id' => $validated['wa_message_template_id'] ?? null,
                'remind_target' => $validated['remind_target'],
                'remind_notify_pengajar' => $request->boolean('remind_notify_pengajar'),
                'remind_notify_pengajar_time' => $validated['remind_notify_pengajar_time'] ?? '19:00',
                'wa_message_template_id_pengajar' => $validated['wa_message_template_id_pengajar'] ?? null,
                'pengajar_request_keyword' => $validated['pengajar_request_keyword'] ?? 'jadwal',
                'reschedule_notify_pengajar' => $request->boolean('reschedule_notify_pengajar'),
                'reschedule_notify_requester' => $request->boolean('reschedule_notify_requester'),
                'reschedule_notify_admin' => $request->boolean('reschedule_notify_admin'),
                'wa_message_template_id_reschedule_approved' => $validated['wa_message_template_id_reschedule_approved'] ?? null,
                'wa_message_template_id_reschedule_rejected' => $validated['wa_message_template_id_reschedule_rejected'] ?? null,
            ]
        );

        $this->syncReminderRules($setting, $validated['rules']);

        return redirect()->route('jadwal.settings.edit')->with('success', 'Pengaturan pengingat Jadwal berhasil disimpan.');
    }

    /**
     * Update 7 September 2026 -- lihat docblock update() di atas.
     * Sinkronisasi baris App\Models\JadwalReminderRule milik $setting
     * dengan array `rules` dari form: baris yang `id`-nya cocok dengan
     * rule MILIK SETTING INI (bukan sembarang UUID -- $existingIds
     * discope ke $setting->id supaya form yang di-tempel id rule company
     * lain tidak bisa mengklaim/mengubah rule company lain) di-UPDATE
     * di tempat (mempertahankan id-nya, supaya App\Models\
     * JadwalKelasReminderLog historis yang mereferensikannya tidak
     * ter-orphan tanpa perlu -- lihat docblock migration
     * add_reminder_rule_to_....php soal nullOnDelete()), baris tanpa id
     * (atau id yang tidak match) di-CREATE sebagai rule baru, dan baris
     * lama yang TIDAK ADA lagi di input (admin menghapusnya di form)
     * di-DELETE (nullOnDelete() pada FK log yang menjaga riwayat log-nya
     * tetap aman).
     *
     * @param  array<int, array{id?: ?string, remind_value: int, remind_unit: string}>  $rulesInput
     */
    private function syncReminderRules(JadwalReminderSetting $setting, array $rulesInput): void
    {
        $existingIds = $setting->rules()->pluck('id')->all();
        $keepIds = [];

        foreach ($rulesInput as $ruleData) {
            $id = $ruleData['id'] ?? null;

            if ($id && in_array($id, $existingIds, true)) {
                JadwalReminderRule::where('id', $id)->update([
                    'remind_value' => $ruleData['remind_value'],
                    'remind_unit' => $ruleData['remind_unit'],
                ]);
                $keepIds[] = $id;

                continue;
            }

            $newRule = JadwalReminderRule::create([
                'jadwal_reminder_setting_id' => $setting->id,
                'remind_value' => $ruleData['remind_value'],
                'remind_unit' => $ruleData['remind_unit'],
            ]);
            $keepIds[] = $newRule->id;
        }

        JadwalReminderRule::where('jadwal_reminder_setting_id', $setting->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * Closure validasi "template ini beneran milik company yang login,
     * bukan cuma UUID valid milik company lain" -- dipakai berulang buat
     * 3 kolom template (pengingat + reschedule approved/rejected), jadi
     * dikeluarkan ke satu method di sini alih-alih 3 closure identik
     * ditulis ulang.
     */
    private function templateBelongsToCompanyRule($company): \Closure
    {
        return function ($attribute, $value, $fail) use ($company) {
            if ($value && ! WaMessageTemplate::where('company_id', $company->id)->where('id', $value)->exists()) {
                $fail('Template tidak valid.');
            }
        };
    }
}
