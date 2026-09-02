<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\FormHeader;
use App\Models\FormSetting;
use App\Models\JadwalReminderSetting;
use App\Models\WaMessageTemplate;
use App\Services\Chat\DeviceDirectory;
use App\Services\PackageLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Satu halaman "Pengaturan" per Form Header (level ke-6/terakhir
 * drill-down) -- upsert satu baris App\Models\FormSetting, bukan CRUD
 * list. Pola & alasannya sama persis dengan App\Http\Controllers\
 * Jadwal\JadwalReminderSettingController, cuma di sini scoped per
 * form_header_id (bukan per company) karena tiap form bisa punya
 * pengaturan notifikasi sendiri-sendiri.
 *
 * Notifikasi WA saat submission masuk dikirim ke ADMIN/STAFF BRANCH
 * form itu (bukan ke nomor pengisi) -- lihat App\Http\Controllers\
 * Form\PublicFormController::maybeSendWaNotification(), yang menarik
 * daftar penerimanya lewat companyTeamMembers() (App\Http\Controllers\
 * Concerns\ResolvesCompanyContext), bukan dari isian form.
 */
class FormSettingController extends Controller
{
    use ResolvesCompanyContext;

    public function edit(Request $request, string $formHeader, PackageLimitService $packageLimits, DeviceDirectory $devices): View
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);
        $company = $header->company;

        $waActive = $packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES);
        $setting = FormSetting::where('form_header_id', $header->id)->first();

        if (! $waActive) {
            return view('form.form-setting.edit', [
                'header' => $header,
                'setting' => $setting,
                'waActive' => false,
                'devices' => collect(),
                'templates' => collect(),
            ]);
        }

        return view('form.form-setting.edit', [
            'header' => $header,
            'setting' => $setting,
            'waActive' => true,
            'devices' => $devices->devicesForCompany($company->id, $header->branch_office_id),
            'templates' => WaMessageTemplate::where('company_id', $company->id)
                ->where('status', 'active')
                ->where('review_status', 'approved')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, string $formHeader, PackageLimitService $packageLimits): RedirectResponse
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);
        $company = $header->company;

        if ($request->boolean('notify_wa_enabled') && ! $packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES)) {
            return redirect()
                ->route('form.setting.edit', $header->id)
                ->with('error', 'Company Anda belum memiliki package aktif kategori Chat/WhatsApp -- notifikasi WA belum bisa diaktifkan.');
        }

        $validated = Validator::make($request->all(), [
            'device_id' => ['nullable', 'string', 'max:36'],
            'notify_wa_enabled' => ['nullable', 'boolean'],
            'wa_message_template_id' => [
                'nullable', 'uuid', 'exists:wa_message_templates,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! WaMessageTemplate::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Template WA tidak valid.');
                    }
                },
            ],
            'additional_info' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:'.FormSetting::STATUS_ACTIVE.','.FormSetting::STATUS_INACTIVE],
        ])->validate();

        FormSetting::updateOrCreate(
            ['form_header_id' => $header->id],
            array_merge($validated, [
                'company_id' => $header->company_id,
                'branch_office_id' => $header->branch_office_id,
                'form_category_id' => $header->form_category_id,
            ])
        );

        return redirect()
            ->route('form.setting.edit', $header->id)
            ->with('success', 'Pengaturan Form berhasil disimpan.');
    }

    private function ownedHeaderOrFail(Request $request, string $formHeaderId): FormHeader
    {
        $context = $this->companyContext($request);

        $query = FormHeader::where('company_id', $context->company->id)->where('id', $formHeaderId);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }
}
