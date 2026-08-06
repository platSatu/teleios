<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyToUser;
use App\Models\User;
use App\Models\WaMessageSchedule;
use App\Models\WaMessageScheduleLog;
use App\Models\WaMessageScheduleStep;
use App\Models\WaMessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * CRUD + history for WaMessageSchedule — the merged home of what used
 * to be 3 separate menus: "Pesan Terjadwal", "Forward & Campaign
 * Broadcast", and "Balasan Otomatis". A `type` field on the form now
 * picks which of those 3 behaviours a given schedule has:
 *
 *   - 'once'      — single send, one date+time (old Forward & Campaign).
 *   - 'recurring' — fires daily across a date range (the original
 *                   "Pesan Terjadwal").
 *   - 'drip'      — enrolls its recipients on date_start, then sends a
 *                   series of App\Models\WaMessageScheduleStep messages,
 *                   each a fixed number of days later (old "Balasan
 *                   Otomatis" — now supporting the same phone/group/user
 *                   recipient tri-tab as the other two, instead of a
 *                   single hard-coded contact).
 *
 * All 3 types share device, title, the recipient tri-tab, and status —
 * see resources/views/chat/message-schedules/_form.blade.php for how the
 * "Jenis Pengiriman" selector toggles the type-specific fields.
 *
 * create()/edit() are full pages, not modals — same reasoning as
 * User\Profile\CompanyUserController's create/edit.
 *
 * Actual sending is handled by App\Console\Commands\
 * DispatchDueWaMessageSchedules + App\Jobs\SendScheduledWaMessage.
 */
class MessageScheduleController extends Controller
{
    use ResolvesCompanyContext;

    /**
     * Extension whitelist + max size (KB) for the manual (non-template)
     * 'image'/'document' categories — deliberately narrower than
     * MessageTemplateController's own ATTACHMENT_RULES (no video/txt
     * here, per the user's explicit spec: image is jpg/jpeg/png only,
     * document is office/pdf only).
     */
    private const SCHEDULE_ATTACHMENT_RULES = [
        'image' => ['ext' => ['jpg', 'jpeg', 'png'], 'max' => 5120],
        'document' => ['ext' => ['xlsx', 'xls', 'docx', 'doc', 'pdf'], 'max' => 10240],
    ];

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $schedules = WaMessageSchedule::where('company_id', $company->id)
            ->with('waMessageTemplate:id,name')
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('title', 'like', '%'.$request->string('search').'%');
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->withCount([
                // 'sent' rank-and-above (sent/delivered/read all mean the
                // send itself succeeded) — kept broad rather than exactly
                // status='sent' so this count (used below for the
                // "Belum diproses" fallback) doesn't go stale the moment
                // a receipt advances a row past 'sent'.
                'logs as sent_count' => fn ($q) => $q->whereIn('status', ['sent', 'delivered', 'read']),
                // 'delivered' rank-and-above — real WhatsApp delivery
                // receipts forwarded by App\Http\Controllers\Api\
                // WaMessageStatusWebhookController, not just "handed to
                // the Go backend" like the old sent-only count implied.
                'logs as delivered_count' => fn ($q) => $q->whereIn('status', ['delivered', 'read']),
                'logs as read_count' => fn ($q) => $q->where('status', 'read'),
                'logs as failed_count' => fn ($q) => $q->where('status', 'failed'),
                'logs as pending_count' => fn ($q) => $q->where('status', 'pending'),
                'steps',
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Audiens = distinct recipients declared on the schedule itself
        // (JSON column, can't be counted in SQL) — this and sent/failed/
        // pending above are what used to live on the separate "Laporan
        // Broadcast" page (Chat\BroadcastReportController, now removed);
        // folded in here so this one index covers both the operational
        // CRUD list and the send-outcome summary.
        $schedules->getCollection()->transform(function (WaMessageSchedule $schedule) {
            $schedule->audience_count = count($schedule->recipients ?? []);

            return $schedule;
        });

        // Summary cards above the table — deliberately scoped to the
        // WHOLE company (not $request's search/status filters), so they
        // read as a fixed "at a glance" dashboard rather than numbers
        // that confusingly shift while someone's mid-search.
        $stats = [
            'total' => WaMessageSchedule::where('company_id', $company->id)->count(),
            'active' => WaMessageSchedule::where('company_id', $company->id)->where('status', 'active')->count(),
            // "Total Terkirim" = every log that successfully left the
            // device, regardless of whether a delivered/read receipt has
            // come back yet — sent/delivered/read all mean the send
            // itself worked (see WaMessageScheduleLog::STATUS_RANK).
            'delivered' => WaMessageScheduleLog::whereHas('schedule', fn ($q) => $q->where('company_id', $company->id))
                ->whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'failed' => WaMessageScheduleLog::whereHas('schedule', fn ($q) => $q->where('company_id', $company->id))
                ->where('status', 'failed')->count(),
        ];

        return view('chat.message-schedules.index', compact('schedules', 'stats'));
    }

    public function create(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        return view('chat.message-schedules.create', $this->formData($company) + ['schedule' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-schedules.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validatedRaw = $validator->validated();
        $steps = $validatedRaw['steps'] ?? [];

        $validated = $this->finalize($validatedRaw, $request, $company);
        $validated['company_id'] = $company->id;

        $this->applyAttachment($request, $validated, null);

        try {
            $schedule = WaMessageSchedule::create($validated);
        } catch (\Throwable $e) {
            return $this->failedSave($e, 'chat.message-schedules.create');
        }

        if ($schedule->type === 'drip') {
            $this->syncSteps($schedule, $steps);
        }

        return redirect()
            ->route('chat.message-schedules.index')
            ->with('success', 'Pesan terjadwal berhasil dibuat.');
    }

    public function edit(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $schedule = WaMessageSchedule::where('company_id', $company->id)
            ->where('id', $id)
            ->with('steps.waMessageTemplate:id,name')
            ->firstOrFail();

        return view('chat.message-schedules.edit', $this->formData($company) + ['schedule' => $schedule]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $schedule = WaMessageSchedule::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-schedules.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validatedRaw = $validator->validated();
        $steps = $validatedRaw['steps'] ?? [];

        $validated = $this->finalize($validatedRaw, $request, $company);

        $this->applyAttachment($request, $validated, $schedule);

        try {
            $schedule->update($validated);
        } catch (\Throwable $e) {
            return $this->failedSave($e, 'chat.message-schedules.edit', $id);
        }

        if ($schedule->type === 'drip') {
            $this->syncSteps($schedule, $steps);
        } else {
            // Type was changed away from 'drip' — its old steps are no
            // longer reachable from any form, so drop them rather than
            // leaving orphaned rows around.
            $schedule->steps()->delete();
        }

        // Whatever changed (device, message, recipients, dates, time,
        // steps), give today's date a clean slate: drop any not-yet-sent
        // log rows for today so the dispatcher re-evaluates this
        // schedule fresh on its next run instead of e.g. staying skipped
        // because of a 'failed' row from before the edit. Already-'sent'
        // history is never touched.
        $schedule->logs()
            ->where('send_date', now()->toDateString())
            ->whereIn('status', ['pending', 'failed'])
            ->delete();

        return redirect()
            ->route('chat.message-schedules.index')
            ->with('success', 'Pesan terjadwal berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $deleted = WaMessageSchedule::where('company_id', $company->id)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            abort(404);
        }

        return redirect()
            ->route('chat.message-schedules.index')
            ->with('success', 'Pesan terjadwal berhasil dihapus.');
    }

    /**
     * "History" — every (recipient, day, step) send attempt for one
     * schedule, newest first. See App\Models\WaMessageScheduleLog.
     */
    public function history(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $schedule = WaMessageSchedule::where('company_id', $company->id)
            ->where('id', $id)
            ->with('steps.waMessageTemplate:id,name')
            ->firstOrFail();

        $logs = $schedule->logs()
            ->orderByDesc('send_date')
            ->orderBy('recipient_key')
            ->orderBy('step_order')
            ->paginate(25);

        $recipientLabels = $this->recipientLabels($schedule->recipients ?? []);
        $stepLabels = $this->stepLabels($schedule);

        return view('chat.message-schedules.history', compact('schedule', 'logs', 'recipientLabels', 'stepLabels'));
    }

    private function validator(Request $request, Company $company)
    {
        $type = $request->input('type', 'recurring');
        $attachmentExtensions = collect(self::SCHEDULE_ATTACHMENT_RULES)->flatMap(fn ($r) => $r['ext'])->implode(',');
        $attachmentMaxKb = collect(self::SCHEDULE_ATTACHMENT_RULES)->max('max');

        $validator = Validator::make($request->all(), [
            'device_id' => ['required', 'string', 'max:36'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:once,recurring,drip'],
            'use_template' => ['nullable', 'boolean'],
            'wa_message_template_id' => ['nullable', 'uuid'],
            'category_schedule' => ['nullable', 'in:text,location,image,document,button'],
            'message' => ['nullable', 'string'],
            'link' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', "mimes:{$attachmentExtensions}", "max:{$attachmentMaxKb}"],
            'remove_attachment' => ['nullable', 'boolean'],
            'date_start' => ['required', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'schedule_time' => ['required', 'date_format:H:i'],
            'status' => ['required', 'in:active,inactive'],
            'phone_numbers' => ['nullable', 'string'],
            'group_jids' => ['nullable', 'array'],
            'group_jids.*' => ['string'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid'],
            'steps' => ['required_if:type,drip', 'array', 'min:1'],
            'steps.*.delay_days' => ['required_if:type,drip', 'integer', 'min:0'],
            'steps.*.use_template' => ['nullable', 'boolean'],
            'steps.*.wa_message_template_id' => ['nullable', 'uuid'],
            'steps.*.category_schedule' => ['nullable', 'in:text,location,image,document,button'],
            'steps.*.message' => ['nullable', 'string'],
            'steps.*.status' => ['nullable', 'in:active,inactive'],
        ], [
            'attachment.mimes' => 'Format file tidak didukung. Gunakan: '.$attachmentExtensions.'.',
            'attachment.max' => 'Ukuran file terlalu besar.',
        ]);

        $validator->after(function ($validator) use ($request, $company, $type) {
            $useTemplate = $type !== 'drip' && $request->boolean('use_template');

            if ($type === 'drip') {
                foreach ((array) $request->input('steps', []) as $i => $step) {
                    $stepUsesTemplate = ! empty($step['use_template']);

                    if ($stepUsesTemplate) {
                        $templateId = $step['wa_message_template_id'] ?? null;
                        $valid = $templateId && WaMessageTemplate::where('company_id', $company->id)
                            ->where('id', $templateId)
                            ->exists();

                        if (! $valid) {
                            $validator->errors()->add("steps.$i.wa_message_template_id", 'Pilih template WA yang valid untuk langkah ini.');
                        }
                    } elseif (empty($step['message'])) {
                        $validator->errors()->add("steps.$i.message", 'Isi pesan langkah ini wajib diisi (atau aktifkan template).');
                    }
                }
            } elseif ($useTemplate) {
                $templateId = $request->input('wa_message_template_id');
                $valid = $templateId && WaMessageTemplate::where('company_id', $company->id)
                    ->where('id', $templateId)
                    ->exists();

                if (! $valid) {
                    $validator->errors()->add('wa_message_template_id', 'Pilih template WA yang valid.');
                }
            } else {
                // Manual content — which fields are required depends on
                // category_schedule (see MessageTemplateController's
                // sibling content_type logic for the WA Template side of
                // the same idea): text just needs the message body,
                // location needs a name (reusing `message`) + a link,
                // image/document need an uploaded file (or one already
                // saved from a previous edit).
                $category = $request->input('category_schedule', 'text');

                if ($category === 'text' && ! $request->input('message')) {
                    $validator->errors()->add('message', 'Isi pesan wajib diisi (atau aktifkan template).');
                }

                if ($category === 'location') {
                    if (! $request->input('message')) {
                        $validator->errors()->add('message', 'Nama lokasi wajib diisi.');
                    }
                    if (! $request->input('link')) {
                        $validator->errors()->add('link', 'Link lokasi wajib diisi.');
                    }
                }

                if (in_array($category, ['image', 'document'], true)) {
                    $hasExisting = $request->route('id')
                        && WaMessageSchedule::where('id', $request->route('id'))->whereNotNull('attachment_path')->exists();

                    if (! $request->hasFile('attachment') && ! $hasExisting) {
                        $validator->errors()->add('attachment', 'File wajib diupload untuk kategori ini.');
                    }
                }

                if ($request->hasFile('attachment')) {
                    $file = $request->file('attachment');
                    $extension = strtolower($file->getClientOriginalExtension());
                    $rule = collect(self::SCHEDULE_ATTACHMENT_RULES)
                        ->first(fn ($r) => in_array($extension, $r['ext'], true));

                    if ($rule && $file->getSize() > $rule['max'] * 1024) {
                        $validator->errors()->add('attachment', 'Ukuran file maksimal '.round($rule['max'] / 1024, 1).'MB untuk jenis file ini.');
                    }
                }
            }

            // Recipients: for 'drip' and manual (non-template) once/
            // recurring, the tri-tab below is the only source of
            // recipients, so at least one is required. When a template
            // is in use, recipients are pulled from the template itself
            // instead (see finalize()) — the tri-tab isn't even shown on
            // the form in that case, so requiring it here would block
            // submission on fields the user can't see.
            if ($type === 'drip' || ! $useTemplate) {
                $hasPhone = trim((string) $request->input('phone_numbers')) !== '';
                $hasGroup = ! empty(array_filter((array) $request->input('group_jids', [])));
                $hasUser = ! empty(array_filter((array) $request->input('user_ids', [])));

                if (! $hasPhone && ! $hasGroup && ! $hasUser) {
                    $validator->errors()->add('recipients', 'Pilih minimal satu tujuan: nomor WhatsApp, grup, atau user company.');
                }
            }

            // A plain `exists` rule can't scope by company — this makes
            // sure every checked user_id genuinely belongs to the
            // caller's own company, not just to SOME company.
            $userIds = array_unique(array_filter((array) $request->input('user_ids', [])));

            if ($userIds) {
                $validUserIds = CompanyToUser::where('company_id', $company->id)
                    ->whereIn('user_id', $userIds)
                    ->pluck('user_id')
                    ->unique();

                if ($validUserIds->count() < count($userIds)) {
                    $validator->errors()->add('user_ids', 'Salah satu user yang dipilih tidak valid untuk company ini.');
                }
            }
        });

        return $validator;
    }

    /**
     * Post-validation cleanup shared by store()/update(): merges the 3
     * recipient tabs into WaMessageSchedule::recipients, resolves
     * date_end per type ('recurring' can span a range; 'once'/'drip'
     * always collapse to a single date_start), and nulls out whichever
     * of message-vs-template the toggle says isn't in use — or both, for
     * 'drip', since that type's content lives entirely in its steps
     * instead. `steps` itself is stripped here; store()/update() pull it
     * from the raw validated array before calling this, then hand it to
     * syncSteps() separately since it's not a column on this table.
     */
    private function finalize(array $validated, Request $request, Company $company): array
    {
        $type = $validated['type'];
        $useTemplate = $type !== 'drip' && $request->boolean('use_template');

        $validated['use_template'] = $useTemplate;
        $validated['wa_message_template_id'] = $useTemplate ? ($validated['wa_message_template_id'] ?? null) : null;

        if ($type === 'drip' || $useTemplate) {
            $validated['category_schedule'] = null;
            $validated['message'] = null;
            $validated['link'] = null;
        } else {
            // Manual content — `link` only applies to 'location', and
            // (per MessageTemplateController's identical reasoning for
            // content_type) is dropped rather than left stale if the
            // company switches back to 'text'/'image'/'document'.
            $category = $validated['category_schedule'] ?? 'text';
            if ($category !== 'location') {
                $validated['link'] = null;
            }
        }

        $validated['date_end'] = $type === 'recurring'
            ? ($validated['date_end'] ?: $validated['date_start'])
            : $validated['date_start'];

        // Recipients: a template (when in use) now carries its own
        // recipients — see Chat\MessageTemplateController — so a
        // schedule that uses one just takes a snapshot of whatever the
        // template currently has, rather than reading the (hidden, in
        // this case) tri-tab. `drip` never has a single top-level
        // template, so it always falls through to the tri-tab like
        // before.
        if ($useTemplate) {
            $template = WaMessageTemplate::where('company_id', $company->id)
                ->where('id', $validated['wa_message_template_id'])
                ->first();

            $validated['recipients'] = $template->recipients ?? [];
        } else {
            $validated['recipients'] = $this->collectRecipients($request);
        }

        unset($validated['steps']);

        return $validated;
    }

    /**
     * Handles the `attachment` upload for category_schedule = image/
     * document on a manual (non-template) message — identical shape to
     * MessageTemplateController::applyAttachment(), just pointed at
     * WaMessageSchedule's own attachment_* columns and the narrower
     * SCHEDULE_ATTACHMENT_RULES whitelist.
     */
    private function applyAttachment(Request $request, array &$validated, ?WaMessageSchedule $existing): void
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
        $category = collect(self::SCHEDULE_ATTACHMENT_RULES)
            ->filter(fn ($rule) => in_array($extension, $rule['ext'], true))
            ->keys()
            ->first();

        if ($existing) {
            Storage::disk('public')->delete((string) $existing->attachment_path);
        }

        $path = $file->store('message-schedule-attachments', 'public');

        $validated['attachment_path'] = $path;
        $validated['attachment_type'] = $category;
        $validated['attachment_original_name'] = $file->getClientOriginalName();
        $validated['attachment_size'] = $file->getSize();
    }

    /**
     * Same "turn a raw DB exception into an actionable message" pattern
     * as MessageTemplateController::failedSave() — the likely cause here
     * is the same class of problem: a migration (this time the one
     * adding link/attachment_* to wa_message_schedules) not having run
     * yet.
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
            ->with('error', 'Pesan terjadwal gagal disimpan.'.$hint.' (Detail teknis sudah dicatat di log.)');
    }

    /**
     * Replaces every WaMessageScheduleStep on a 'drip' schedule with the
     * submitted set — simplest-correct reconciliation given the form's
     * step rows have no stable id of their own to match against (they're
     * just cloned rows in the browser). Safe for history: WaMessageScheduleLog
     * references a step by `sequence_order` (an integer), not a foreign
     * key, so as long as the resubmitted steps keep the same 1..N
     * ordering, existing log rows still line up with the right step
     * content even though the step *records* themselves are brand new.
     */
    private function syncSteps(WaMessageSchedule $schedule, array $steps): void
    {
        $schedule->steps()->delete();

        foreach (array_values($steps) as $i => $step) {
            $useTemplate = ! empty($step['use_template']);

            WaMessageScheduleStep::create([
                'wa_message_schedule_id' => $schedule->id,
                'sequence_order' => $i + 1,
                'delay_days' => (int) ($step['delay_days'] ?? 0),
                'use_template' => $useTemplate,
                'wa_message_template_id' => $useTemplate ? ($step['wa_message_template_id'] ?? null) : null,
                'category_schedule' => $useTemplate ? null : ($step['category_schedule'] ?? 'text'),
                'message' => $useTemplate ? null : ($step['message'] ?? null),
                'status' => $step['status'] ?? 'active',
            ]);
        }
    }

    /**
     * Merges phone numbers (split on `;`, `,`, or newline — whatever the
     * user typed), checked WA groups, and checked company users into the
     * JSON shape WaMessageSchedule::recipients expects. Deduplicated so
     * the same target checked/typed twice never causes a double send.
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
     * Data shared by create()/edit(): devices are loaded client-side
     * (resources/views/chat/partials/device-select-script.blade.php) and
     * WA groups are loaded client-side per selected device (inbox.chats),
     * so only genuinely server-only data needs to be passed here — this
     * company's active templates (used both by the parent content picker
     * and by each drip step's own template picker), and the branch
     * office / unit / member tree for the "Company Users" tab.
     */
    private function formData(Company $company): array
    {
        return [
            // usable() (not just status=active) — a schedule can only
            // actually pick a template that's cleared superadmin review
            // too, otherwise "Gunakan Template" would let a company
            // select something that can never legally send.
            'templates' => WaMessageTemplate::where('company_id', $company->id)
                ->usable()
                ->orderBy('name')
                ->get(),
            'branchOffices' => $company->branchOffices()->with('units')->orderBy('name')->get(),
            'companyMembers' => CompanyToUser::where('company_id', $company->id)
                ->with(['user:id,name,email,handphone', 'branchOffice:id,name', 'branchOfficeUnit:id,name'])
                ->get()
                ->unique('user_id')
                ->values(),
        ];
    }

    /**
     * Human-readable label per recipient key for the history page — a
     * raw "user:<uuid>" means nothing to whoever's reading the log, so
     * this resolves it back to a name (or "(dihapus)" if the user record
     * is gone by the time history is viewed).
     */
    private function recipientLabels(array $recipients): array
    {
        $userIds = collect($recipients)->where('type', 'user')->pluck('value')->all();
        $users = $userIds ? User::whereIn('id', $userIds)->pluck('name', 'id') : collect();

        $labels = [];

        foreach ($recipients as $r) {
            $key = ($r['type'] ?? '').':'.($r['value'] ?? '');

            $labels[$key] = match ($r['type'] ?? '') {
                'user' => $users->get($r['value']) ? $users->get($r['value']).' (User Company)' : 'User (dihapus)',
                'group' => 'Grup WA: '.$r['value'],
                'phone' => $r['value'] ?? '-',
                default => $r['value'] ?? '-',
            };
        }

        return $labels;
    }

    /**
     * "Langkah 2 (H+3): <preview>" per sequence_order — matches how
     * WaMessageScheduleLog::step_order links a log row back to the step
     * that produced it (see that model's docblock for why it's an
     * integer, not a foreign key). Empty for non-'drip' schedules, which
     * only ever log step_order = 0.
     */
    private function stepLabels(WaMessageSchedule $schedule): array
    {
        return $schedule->steps->mapWithKeys(function (WaMessageScheduleStep $step) {
            $preview = $step->use_template
                ? 'Template: '.($step->waMessageTemplate->name ?? 'dihapus')
                : Str::limit((string) $step->message, 40);

            return [$step->sequence_order => "Langkah {$step->sequence_order} (H+{$step->delay_days}): {$preview}"];
        })->all();
    }

}
