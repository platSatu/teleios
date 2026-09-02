<?php

namespace App\Http\Controllers\Form;

use App\Helpers\FormImageUploader;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\FormCategory;
use App\Models\FormContent;
use App\Models\FormHeader;
use App\Models\FormSubmission;
use App\Models\FormSubmissionAnswer;
use App\Models\JadwalReminderSetting;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\PackageLimitService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Halaman PUBLIK (tanpa auth) buat orang mengisi App\Models\FormHeader
 * lewat app.konexa.id/{slug} -- didaftarkan sebagai rute top-level di
 * routes/web.php, PALING BAWAH file itu supaya tidak pernah menang
 * lawan rute spesifik manapun (/dashboard, /login, dst).
 *
 * Sengaja TIDAK pakai App\Http\Controllers\Concerns\ResolvesCompanyContext
 * ::companyContext() di sini (pengunjung tidak login) -- trait-nya
 * cuma dipakai untuk companyTeamMembers() di maybeSendWaNotification(),
 * yang tidak butuh $request sama sekali.
 */
class PublicFormController extends Controller
{
    use ResolvesCompanyContext;

    public function show(string $slug): View
    {
        $header = FormHeader::where('slug', $slug)
            ->with(['contents' => fn ($q) => $q->orderBy('position'), 'footers' => fn ($q) => $q->where('status', 'active'), 'formCategory'])
            ->firstOrFail();

        if ($header->formCategory->status !== FormCategory::STATUS_ACTIVE) {
            abort(404);
        }

        return view('form.public.show', [
            'header' => $header,
            'canSubmit' => $header->isOpenForSubmission(),
        ]);
    }

    public function store(Request $request, string $slug, PackageLimitService $packageLimits): RedirectResponse
    {
        $header = FormHeader::where('slug', $slug)->with('contents')->firstOrFail();

        if ($header->formCategory->status !== FormCategory::STATUS_ACTIVE || ! $header->isOpenForSubmission()) {
            abort(404);
        }

        $validated = $request->validate($this->answerRules($header));

        $submission = DB::transaction(function () use ($header, $request, $validated) {
            $submission = FormSubmission::create([
                'company_id' => $header->company_id,
                'branch_office_id' => $header->branch_office_id,
                'form_category_id' => $header->form_category_id,
                'form_header_id' => $header->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'submitted_at' => now(),
            ]);

            foreach ($header->contents as $content) {
                $value = $validated['answers'][$content->id] ?? null;
                $filePath = null;

                if ($content->type === FormContent::TYPE_FILE_UPLOAD) {
                    if ($request->hasFile("answers.{$content->id}")) {
                        $filePath = FormImageUploader::upload($request->file("answers.{$content->id}"), 'submissions');
                    }
                    $value = null;
                } elseif (is_array($value)) {
                    // multiple_choice -- disimpan JSON-encoded di kolom
                    // text yang sama, lihat App\Models\
                    // FormSubmissionAnswer::decodedValue().
                    $value = json_encode(array_values($value));
                }

                FormSubmissionAnswer::create([
                    'form_submission_id' => $submission->id,
                    'form_content_id' => $content->id,
                    'value' => $value,
                    'file_path' => $filePath,
                ]);
            }

            return $submission;
        });

        $this->maybeSendWaNotification($header, $submission, $packageLimits);

        return redirect()
            ->route('form.public.show', $header->slug)
            ->with('success', 'Form berhasil dikirim, terima kasih!');
    }

    private function answerRules(FormHeader $header): array
    {
        $rules = [];

        foreach ($header->contents as $content) {
            $key = "answers.{$content->id}";
            $required = $content->is_required ? 'required' : 'nullable';

            $rules[$key] = match ($content->type) {
                FormContent::TYPE_SINGLE_LINE => [$required, 'string', 'max:255'],
                FormContent::TYPE_TEXTAREA => [$required, 'string', 'max:5000'],
                FormContent::TYPE_SINGLE_CHOICE => [$required, 'string', Rule::in($content->options ?? [])],
                FormContent::TYPE_MULTIPLE_CHOICE => [$required, 'array'],
                FormContent::TYPE_FILE_UPLOAD => [
                    $required, 'file',
                    'mimes:'.($content->allowed_file_types ?: FormContent::DEFAULT_ALLOWED_FILE_TYPES),
                    'max:5120', // 5MB
                ],
                default => [$required, 'string'],
            };

            if ($content->type === FormContent::TYPE_MULTIPLE_CHOICE) {
                $rules["{$key}.*"] = ['string', Rule::in($content->options ?? [])];
            }
        }

        return $rules;
    }

    /**
     * Notifikasi WA ke ADMIN/STAFF BRANCH form ini (bukan ke nomor
     * pengisi) begitu submission masuk -- ditarik lewat
     * companyTeamMembers() (owner + anggota yang terkunci ke branch
     * ini), sama daftar yang dipakai Chat > Kontak untuk "siapa yang
     * bisa kelola branch ini". Gagal diam-diam (log warning, tidak
     * pernah melempar balik ke pengisi form) kapan pun salah satu
     * syarat tidak terpenuhi -- pengisi form tidak boleh lihat error
     * internal cuma karena admin belum setting WA.
     */
    private function maybeSendWaNotification(FormHeader $header, FormSubmission $submission, PackageLimitService $packageLimits): void
    {
        $setting = $header->setting;

        if (! $setting || ! $setting->notify_wa_enabled || ! $setting->wa_message_template_id || ! $setting->device_id) {
            return;
        }

        $company = $header->company;

        if (! $packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES)) {
            return;
        }

        $template = $setting->waMessageTemplate;

        if (! $template || $template->status !== 'active' || $template->review_status !== 'approved') {
            return;
        }

        $recipients = $this->companyTeamMembers($company, $header->branch_office_id)
            ->pluck('handphone')
            ->filter()
            ->unique();

        $owner = $company->user;

        if ($recipients->isEmpty() || ! $owner) {
            return;
        }

        try {
            $token = app(SystemJwtService::class)->mintFor($owner);
        } catch (Throwable $e) {
            Log::warning('PublicFormController: gagal membuat token pengirim WA notifikasi form', [
                'form_header_id' => $header->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $message = strtr($template->composedMessage(), [
            '{{nama_form}}' => $header->name,
            '{{waktu_submit}}' => $submission->submitted_at->translatedFormat('d M Y H:i'),
        ]);

        $inbox = app(InboxService::class);

        foreach ($recipients as $phone) {
            try {
                $jid = PhoneNumber::normalize($phone).'@s.whatsapp.net';
                $inbox->send($token, $setting->device_id, $jid, $message);
            } catch (Throwable $e) {
                Log::warning('PublicFormController: gagal mengirim notifikasi WA submission form', [
                    'form_header_id' => $header->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
