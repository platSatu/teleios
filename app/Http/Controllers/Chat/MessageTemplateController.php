<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\AppliesTemplateModeration;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyToUser;
use App\Models\WaCategoryTemplate;
use App\Models\WaMessageTemplate;
use App\Services\Moderation\ModerationResult;
use App\Services\Moderation\TemplateModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorContract;
use Illuminate\View\View;

/**
 * CRUD for "WA Template" (Chat > Pesan > WA Template) — a company's
 * reusable WhatsApp message. Recipients now live HERE (see `recipients`
 * — same tri-tab phone/group/user shape App\Http\Controllers\Chat\
 * MessageScheduleController used to own alone) rather than only on the
 * schedule: the user's own call this session was "kalau mau broadcast
 * tetap pasang di template ... bagian contact itu hanya dipindahkan
 * sebenarnya dan modifikasi" — the recipient picker moved over, wholesale,
 * from the schedule form to this one. MessageScheduleController's own
 * tri-tab now only applies when a schedule sends a manual (non-template)
 * message; when "Gunakan Template" is on, the schedule pulls whichever
 * recipients are saved on the selected template instead.
 *
 * `content_type` (text | text_link | text_link_file) hardcodes which
 * fields the builder form shows, same idea as MessageScheduleController's
 * `category_schedule` for manual messages — see WaMessageTemplate::
 * CONTENT_TYPES.
 *
 * Builder fields (category, language, header/footer, buttons, variables,
 * content_type, link, attachment) mirror WhatsApp Business's own template
 * shape. Any edit to a reviewable field re-runs App\Services\Moderation\
 * TemplateModerationService — see contentFieldsChanged() below — so an
 * approved template can never be silently swapped for different content
 * without going back through moderation. Recipients are NOT in that
 * reviewable list: who a message goes to isn't something the AI
 * moderates, only what it says.
 *
 * The AI only ever judges/corrects the free-text header/body/footer
 * (see moderateContent()) — buttons, link, category, and language still
 * trigger a fresh moderation pass when changed, but aren't themselves
 * rewritten by it. There is no more manual superadmin approve/reject
 * queue for this resource (see Superadmin\WaTemplateReviewController,
 * now read-only oversight).
 */
class MessageTemplateController extends Controller
{
    use ResolvesCompanyContext, AppliesTemplateModeration;

    public function __construct(protected TemplateModerationService $moderation)
    {
    }

    /**
     * Fields that require a fresh superadmin review whenever they change.
     * Deliberately excludes `name`, `status`, and `recipients` — renaming
     * a template, toggling active/inactive, or changing who it's aimed at
     * doesn't change what gets sent.
     */
    private const REVIEWABLE_FIELDS = [
        'wa_category_template_id',
        'language',
        'header',
        'template',
        'footer',
        'buttons',
        'content_type',
        'link',
    ];

    /** Extension whitelist + max size (KB) per attachment category. */
    private const ATTACHMENT_RULES = [
        'image' => ['ext' => ['jpg', 'jpeg', 'png'], 'max' => 5120],
        'video' => ['ext' => ['mp4', '3gp', 'mov'], 'max' => 16384],
        'document' => ['ext' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'], 'max' => 10240],
        'text' => ['ext' => ['txt'], 'max' => 2048],
    ];

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $templates = WaMessageTemplate::with('category')
            ->where('company_id', $company->id)
            ->latest()
            ->paginate(15);

        return view('chat.message-templates.index', compact('templates'));
    }

    public function create(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        return view('chat.message-templates.create', $this->formData($company) + ['template' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request, $company->id);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-templates.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $this->sanitize($validator->validated(), $request);
        $validated['company_id'] = $company->id;
        $validated['recipients'] = $this->collectRecipients($request);

        $moderation = $this->moderateContent($validated);

        // AI correction can change header/template/footer text — re-run
        // the strip_tags + "only keep variables that still appear in the
        // text" pass so variables_example never drifts from what the AI
        // actually left behind. No-op (safe to repeat) when nothing was
        // corrected.
        if ($moderation->isCorrected()) {
            $validated = $this->sanitize($validated, $request);
        }

        $validated = array_merge($validated, $this->reviewFieldsFor($moderation));

        $this->applyAttachment($request, $validated, null);

        try {
            WaMessageTemplate::create($validated);
        } catch (\Throwable $e) {
            return $this->failedSave($e, 'chat.message-templates.create');
        }

        [$flashType, $flashMessage] = $this->flashFor($moderation, 'Template WA berhasil dibuat');

        return redirect()
            ->route('chat.message-templates.index')
            ->with($flashType, $flashMessage);
    }

    public function edit(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $template = WaMessageTemplate::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        return view('chat.message-templates.edit', $this->formData($company) + ['template' => $template]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $template = WaMessageTemplate::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request, $company->id);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-templates.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $this->sanitize($validator->validated(), $request);
        $validated['recipients'] = $this->collectRecipients($request);

        $flashType = 'success';
        $flashMessage = 'Template WA berhasil diperbarui.';

        if ($this->contentFieldsChanged($template, $validated)) {
            $moderation = $this->moderateContent($validated);

            if ($moderation->isCorrected()) {
                $validated = $this->sanitize($validated, $request);
            }

            $validated = array_merge($validated, $this->reviewFieldsFor($moderation));
            [$flashType, $flashMessage] = $this->flashFor($moderation, 'Template WA berhasil diperbarui');
        }

        $this->applyAttachment($request, $validated, $template);

        try {
            $template->update($validated);
        } catch (\Throwable $e) {
            return $this->failedSave($e, 'chat.message-templates.edit', $id);
        }

        return redirect()
            ->route('chat.message-templates.index')
            ->with($flashType, $flashMessage);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $template = WaMessageTemplate::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $template) {
            abort(404);
        }

        Storage::disk('public')->delete((string) $template->attachment_path);
        $template->delete();

        return redirect()
            ->route('chat.message-templates.index')
            ->with('success', 'Template WA berhasil dihapus.');
    }

    /**
     * Turns a raw DB/Eloquent exception into a message the user can
     * actually act on instead of a blank 500 page — and logs the real
     * exception so we can still see exactly what broke. The single most
     * likely cause here is a migration not having been run yet on this
     * environment — every column this controller writes only exists
     * after it, so a fresh deploy that skipped `php artisan migrate`
     * fails here on literally every save with no other symptom.
     */
    private function failedSave(\Throwable $e, string $route, ?string $id = null): RedirectResponse
    {
        report($e);

        $hint = str_contains($e->getMessage(), 'Base table or view not found')
            || str_contains($e->getMessage(), "doesn't exist")
            || str_contains($e->getMessage(), 'Unknown column')
            ? ' Kemungkinan migrasi database belum dijalankan — coba jalankan "php artisan migrate" lalu ulangi.'
            : '';

        return redirect()
            ->route($route, $id ? ['id' => $id] : [])
            ->withInput()
            ->with('error', 'Template gagal disimpan.'.$hint.' (Detail teknis sudah dicatat di log.)');
    }

    /**
     * Categories this company may actually pick from the select — active
     * AND already approved (review_status=approved, via AI moderation —
     * see App\Services\Moderation\TemplateModerationService). Company-
     * scoped in addition to WaCategoryTemplate::usable() since that scope
     * alone doesn't filter by owner.
     */
    private function usableCategories(string $companyId)
    {
        return WaCategoryTemplate::usable()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Runs header/template(body)/footer through App\Services\Moderation\
     * TemplateModerationService, and — only when the AI actually
     * corrected something — writes the corrected text straight back
     * into $validated (by reference) before it's saved. Buttons/link/
     * category/language are deliberately NOT included here (see the
     * class docblock): they still trigger this call via
     * contentFieldsChanged(), but aren't themselves rewritten by it.
     */
    private function moderateContent(array &$validated): ModerationResult
    {
        $fields = [
            'header' => (string) ($validated['header'] ?? ''),
            'body' => (string) ($validated['template'] ?? ''),
            'footer' => (string) ($validated['footer'] ?? ''),
        ];

        $moderation = $this->moderation->moderate($fields);

        if ($moderation->isCorrected()) {
            if (array_key_exists('header', $moderation->fields)) {
                $validated['header'] = $moderation->fields['header'] !== '' ? $moderation->fields['header'] : null;
            }
            if (array_key_exists('body', $moderation->fields)) {
                $validated['template'] = $moderation->fields['body'];
            }
            if (array_key_exists('footer', $moderation->fields)) {
                $validated['footer'] = $moderation->fields['footer'] !== '' ? $moderation->fields['footer'] : null;
            }
        }

        return $moderation;
    }

    private function contentFieldsChanged(WaMessageTemplate $template, array $validated): bool
    {
        foreach (self::REVIEWABLE_FIELDS as $field) {
            $current = $template->{$field};
            $incoming = $validated[$field] ?? null;

            if ($current != $incoming) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strips any HTML tags from free-text fields before they ever reach
     * the database. WhatsApp's own formatting is plain-text markdown
     * (*bold*, _italic_, ~strike~), never HTML — so there is no legitimate
     * reason for a "<" to survive here, and stripping it removes any
     * injection surface for wherever this text later gets echoed (chat
     * bubble, live preview, superadmin review screen, WhatsApp send).
     */
    private function sanitize(array $validated, Request $request): array
    {
        foreach (['header', 'template', 'footer', 'link'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = trim(strip_tags($validated[$field]));
            }
        }

        if (! empty($validated['buttons'])) {
            $validated['buttons'] = array_map(function ($button) {
                $button['label'] = trim(strip_tags($button['label'] ?? ''));
                $button['value'] = trim(strip_tags($button['value'] ?? ''));

                return $button;
            }, $validated['buttons']);
        }

        // Only keep example values for variables that actually appear in
        // the message text — anything else is stale leftover from a
        // previous edit and shouldn't be persisted.
        $haystack = implode(' ', array_filter([
            $validated['header'] ?? null,
            $validated['template'] ?? null,
            $validated['footer'] ?? null,
        ]));
        preg_match_all(WaMessageTemplate::VARIABLE_PATTERN, $haystack, $matches);
        $detected = array_unique($matches[1] ?? []);

        if (! empty($validated['variables_example'])) {
            $validated['variables_example'] = array_filter(
                $validated['variables_example'],
                fn ($value, $key) => in_array($key, $detected, true) && $value !== null && $value !== '',
                ARRAY_FILTER_USE_BOTH
            );

            if (empty($validated['variables_example'])) {
                $validated['variables_example'] = null;
            }
        } else {
            $validated['variables_example'] = null;
        }

        // `link` only makes sense for text_link/text_link_file — drop it
        // (rather than just hiding it client-side) if the user picked
        // 'text', so switching content_type back and forth never leaves
        // a stale link hanging around unseen.
        if (($validated['content_type'] ?? 'text') === 'text') {
            $validated['link'] = null;
        }

        return $validated;
    }

    /**
     * Handles the `attachment` upload for content_type = text_link_file:
     * stores the new file (if one was submitted), deletes whatever file
     * used to be there (on replace, or on explicit "remove_attachment"),
     * and fills attachment_path/type/original_name/size directly onto
     * the $validated array that's about to be saved. No-op (keeps the
     * existing attachment columns untouched) when neither a new file nor
     * a removal request is present — e.g. every other field being edited
     * without touching the attachment at all.
     */
    private function applyAttachment(Request $request, array &$validated, ?WaMessageTemplate $existing): void
    {
        if ($request->boolean('remove_attachment') && ! $request->hasFile('attachment')) {
            if ($existing) {
                Storage::disk('public')->delete((string) $existing->attachment_path);
            }
            $validated['attachment_path'] = null;
            $validated['attachment_type'] = null;
            $validated['attachment_original_name'] = null;
            $validated['attachment_size'] = null;

            return;
        }

        if (! $request->hasFile('attachment')) {
            return;
        }

        $file = $request->file('attachment');
        $extension = strtolower($file->getClientOriginalExtension());
        $category = $this->attachmentCategory($extension);

        if ($existing) {
            Storage::disk('public')->delete((string) $existing->attachment_path);
        }

        $path = $file->store('message-template-attachments', 'public');

        $validated['attachment_path'] = $path;
        $validated['attachment_type'] = $category;
        $validated['attachment_original_name'] = $file->getClientOriginalName();
        $validated['attachment_size'] = $file->getSize();
    }

    private function attachmentCategory(string $extension): ?string
    {
        foreach (self::ATTACHMENT_RULES as $category => $rule) {
            if (in_array($extension, $rule['ext'], true)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Merges phone numbers (split on `;`, `,`, or newline), checked WA
     * groups, and checked company users into the JSON shape
     * WaMessageTemplate::recipients expects — identical logic to
     * MessageScheduleController::collectRecipients(), which this
     * replaces for the "use template" path (that controller now only
     * calls its own copy for manual/non-template messages).
     */
    private function collectRecipients(Request $request): array
    {
        $recipients = [];

        collect(preg_split('/[;,\r\n]+/', (string) $request->input('phone_numbers', '')))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->unique()
            ->each(function (string $number) use (&$recipients) {
                $recipients[] = ['type' => 'phone', 'value' => $number];
            });

        collect((array) $request->input('group_jids', []))
            ->filter()
            ->unique()
            ->each(function (string $jid) use (&$recipients) {
                $recipients[] = ['type' => 'group', 'value' => $jid];
            });

        collect((array) $request->input('user_ids', []))
            ->filter()
            ->unique()
            ->each(function (string $userId) use (&$recipients) {
                $recipients[] = ['type' => 'user', 'value' => $userId];
            });

        return $recipients;
    }

    /**
     * Data shared by create()/edit(): this company's approved categories
     * for the dropdown, plus the branch office / unit / member tree the
     * "Company Users" recipient tab needs — same shape
     * MessageScheduleController::formData() builds, now duplicated here
     * since recipients live on the template instead. WA groups are still
     * loaded client-side per selected device, same as before.
     */
    private function formData(Company $company): array
    {
        return [
            'categories' => $this->usableCategories($company->id),
            'branchOffices' => $company->branchOffices()->with('units')->orderBy('name')->get(),
            'companyMembers' => CompanyToUser::where('company_id', $company->id)
                ->with(['user:id,name,email,handphone', 'branchOffice:id,name', 'branchOfficeUnit:id,name'])
                ->get()
                ->unique('user_id')
                ->values(),
        ];
    }

    private function validator(Request $request, string $companyId): ValidatorContract
    {
        $attachmentExtensions = collect(self::ATTACHMENT_RULES)->flatMap(fn ($r) => $r['ext'])->implode(',');
        $attachmentMaxKb = collect(self::ATTACHMENT_RULES)->max('max');

        $validator = Validator::make($request->all(), [
            'wa_category_template_id' => [
                'nullable',
                'uuid',
                Rule::exists('wa_category_templates', 'id')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'in:id,en'],
            'content_type' => ['required', Rule::in(WaMessageTemplate::CONTENT_TYPES)],
            'header' => ['nullable', 'string', 'max:60'],
            'template' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'link' => ['nullable', 'required_if:content_type,text_link,text_link_file', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', "mimes:{$attachmentExtensions}", "max:{$attachmentMaxKb}"],
            'remove_attachment' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
            'buttons' => ['nullable', 'array', 'max:2'],
            'buttons.*.type' => ['required_with:buttons', 'in:url,phone'],
            'buttons.*.label' => ['required_with:buttons', 'string', 'max:25'],
            'buttons.*.value' => ['required_with:buttons', 'string', 'max:2000'],
            'variables_example' => ['nullable', 'array'],
            'variables_example.*' => ['nullable', 'string', 'max:255'],
            'phone_numbers' => ['nullable', 'string'],
            'group_jids' => ['nullable', 'array'],
            'group_jids.*' => ['string'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid'],
        ], [
            'template.max' => 'Isi pesan maksimal :max karakter.',
            'header.max' => 'Header maksimal :max karakter.',
            'footer.max' => 'Footer maksimal :max karakter.',
            'link.required_if' => 'Link wajib diisi untuk jenis konten ini.',
            'buttons.max' => 'Maksimal 2 tombol per template.',
            'buttons.*.label.max' => 'Label tombol maksimal :max karakter.',
            'attachment.mimes' => 'Format lampiran tidak didukung. Gunakan: '.$attachmentExtensions.'.',
            'attachment.max' => 'Ukuran lampiran terlalu besar.',
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request) {
            foreach ((array) $request->input('buttons', []) as $index => $button) {
                $type = $button['type'] ?? null;
                $value = trim((string) ($button['value'] ?? ''));

                if ($type === 'url' && $value !== '' && ! preg_match('/^https?:\/\/.+/i', $value)) {
                    $validator->errors()->add("buttons.$index.value", 'Link harus diawali http:// atau https://.');
                }

                if ($type === 'phone' && $value !== '' && ! preg_match('/^\+?[0-9\s\-]{6,20}$/', $value)) {
                    $validator->errors()->add("buttons.$index.value", 'Nomor telepon tidak valid.');
                }
            }

            // Attachment's own tighter per-category cap on top of the
            // blanket `max` rule above (which only guards against the
            // largest of the 4 categories) — a 15MB PDF should still be
            // rejected even though it's under the video ceiling.
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $extension = strtolower($file->getClientOriginalExtension());
                $category = collect(self::ATTACHMENT_RULES)
                    ->first(fn ($rule) => in_array($extension, $rule['ext'], true));

                if ($category && $file->getSize() > $category['max'] * 1024) {
                    $validator->errors()->add(
                        'attachment',
                        'Ukuran lampiran maksimal '.round($category['max'] / 1024, 1).'MB untuk file jenis ini.'
                    );
                }
            }
        });

        return $validator;
    }
}
