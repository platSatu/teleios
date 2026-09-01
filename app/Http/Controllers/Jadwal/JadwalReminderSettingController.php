<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
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

        $setting = JadwalReminderSetting::where('company_id', $company->id)->first();

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

        $validator = Validator::make($request->all(), [
            'enabled' => ['nullable', 'boolean'],
            'device_id' => ['nullable', 'string', 'max:36'],
            'wa_message_template_id' => [
                'nullable', 'uuid', 'exists:wa_message_templates,id',
                $this->templateBelongsToCompanyRule($company),
            ],
            'remind_value' => ['required', 'integer', 'min:1', 'max:720'],
            'remind_unit' => ['required', 'in:'.implode(',', JadwalReminderSetting::UNITS)],
            'remind_target' => ['required', 'in:'.implode(',', JadwalReminderSetting::TARGETS)],
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

        if ($validator->fails()) {
            return redirect()->route('jadwal.settings.edit')->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        JadwalReminderSetting::updateOrCreate(
            ['company_id' => $company->id],
            [
                'enabled' => $request->boolean('enabled'),
                'device_id' => $validated['device_id'] ?? null,
                'wa_message_template_id' => $validated['wa_message_template_id'] ?? null,
                'remind_value' => $validated['remind_value'],
                'remind_unit' => $validated['remind_unit'],
                'remind_target' => $validated['remind_target'],
                'reschedule_notify_pengajar' => $request->boolean('reschedule_notify_pengajar'),
                'reschedule_notify_requester' => $request->boolean('reschedule_notify_requester'),
                'reschedule_notify_admin' => $request->boolean('reschedule_notify_admin'),
                'wa_message_template_id_reschedule_approved' => $validated['wa_message_template_id_reschedule_approved'] ?? null,
                'wa_message_template_id_reschedule_rejected' => $validated['wa_message_template_id_reschedule_rejected'] ?? null,
            ]
        );

        return redirect()->route('jadwal.settings.edit')->with('success', 'Pengaturan pengingat Jadwal berhasil disimpan.');
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
