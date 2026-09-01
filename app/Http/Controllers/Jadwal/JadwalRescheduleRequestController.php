<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\JadwalKelas;
use App\Models\JadwalKelasRescheduleRequest;
use App\Models\JadwalReminderSetting;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\PackageLimitService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

/**
 * Review manual permintaan ubah jadwal dari orang tua/murid (lihat
 * App\Models\JadwalKelasRescheduleRequest & App\Services\Chat\
 * ChatbotFlowService::createJadwalRescheduleRequest()). Baris di sini
 * TIDAK PERNAH mengubah App\Models\JadwalKelas dengan sendirinya --
 * staff yang memilih (approve()) apakah & bagaimana jadwal benar-benar
 * diubah, sesuai hasil diskusi ("wajib approve staff").
 */
class JadwalRescheduleRequestController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(
        protected PackageLimitService $packageLimits,
        protected SystemJwtService $jwtService,
        protected InboxService $inbox,
    ) {
    }

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $status = $request->query('status', JadwalKelasRescheduleRequest::STATUS_PENDING);

        $query = JadwalKelasRescheduleRequest::where('company_id', $company->id)
            ->with([
                'jadwalStudent:id,name,jadwal_mata_pelajaran_id,pengajar_id',
                'jadwalStudent.mataPelajaran:id,name',
                'jadwalStudent.pengajar:id,name',
                'reviewer:id,name',
                // Sekarang lebih sering terisi otomatis oleh flow chat
                // (lihat App\Services\Chat\ChatbotFlowService::
                // createJadwalRescheduleRequest()) -- di-eager-load supaya
                // baris "Terhubung ke Jadwal Kelas" di index.blade.php tidak
                // memicu query N+1 per baris.
                'jadwalKelas:id,start_time,end_time',
            ]);

        if (in_array($status, JadwalKelasRescheduleRequest::STATUSES, true)) {
            $query->where('status', $status);
        }

        $requests = $query->latest()->paginate(15)->withQueryString()->onEachSide(1);

        // Untuk dropdown "hubungkan ke Jadwal Kelas" -- hanya baris milik
        // murid yang berhasil ditebak per permintaan (lihat
        // ChatbotFlowService's docblock soal kenapa tebakannya cuma
        // sampai level murid, bukan baris Jadwal Kelas spesifik).
        $studentIds = $requests->getCollection()->pluck('jadwal_student_id')->filter()->unique()->values();

        $kelasOptions = $studentIds->isEmpty() ? collect() : JadwalKelas::whereIn('student_id', $studentIds)
            ->where('company_id', $company->id)
            ->orderByDesc('start_time')
            ->get(['id', 'student_id', 'start_time', 'end_time'])
            ->groupBy('student_id');

        return view('jadwal.reschedule-requests.index', compact('requests', 'status', 'kelasOptions'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $reschedule = JadwalKelasRescheduleRequest::where('company_id', $company->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'jadwal_kelas_id' => [
                'nullable', 'uuid',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalKelas::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Jadwal Kelas tidak valid.');
                    }
                },
            ],
            'new_start_time' => ['nullable', 'date'],
            'new_end_time' => ['nullable', 'date', 'after:new_start_time'],
            'staff_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('jadwal.reschedule-requests.index')->withErrors($validator);
        }

        $validated = $validator->validated();

        // Jadwal Kelas beneran diubah HANYA kalau staff eksplisit memilih
        // baris + mengisi waktu barunya di sini -- approve() tanpa itu
        // cuma menandai permintaan selesai diproses (mis. sudah diatur
        // manual di halaman Jadwal Kelas terpisah).
        if (! empty($validated['jadwal_kelas_id']) && ! empty($validated['new_start_time'])) {
            JadwalKelas::where('company_id', $company->id)
                ->where('id', $validated['jadwal_kelas_id'])
                ->update(array_filter([
                    'start_time' => $validated['new_start_time'],
                    'end_time' => $validated['new_end_time'] ?? null,
                ], fn ($v) => $v !== null));
        }

        $reschedule->update([
            'jadwal_kelas_id' => $validated['jadwal_kelas_id'] ?? $reschedule->jadwal_kelas_id,
            'status' => JadwalKelasRescheduleRequest::STATUS_APPROVED,
            'staff_notes' => $validated['staff_notes'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        $this->sendRescheduleNotifications($reschedule, JadwalKelasRescheduleRequest::STATUS_APPROVED);

        return redirect()->route('jadwal.reschedule-requests.index')->with('success', 'Permintaan reschedule disetujui.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $reschedule = JadwalKelasRescheduleRequest::where('company_id', $company->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'staff_notes' => ['required', 'string', 'max:1000'],
        ], [
            'staff_notes.required' => 'Alasan penolakan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('jadwal.reschedule-requests.index')->withErrors($validator);
        }

        $reschedule->update([
            'status' => JadwalKelasRescheduleRequest::STATUS_REJECTED,
            'staff_notes' => $validator->validated()['staff_notes'],
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        $this->sendRescheduleNotifications($reschedule, JadwalKelasRescheduleRequest::STATUS_REJECTED);

        return redirect()->route('jadwal.reschedule-requests.index')->with('success', 'Permintaan reschedule ditolak.');
    }

    /**
     * Best-effort, sama seperti App\Jobs\SendJadwalReminder -- kalau
     * company tidak (lagi) punya package aktif kategori Chat/WhatsApp,
     * device belum diatur, atau pengiriman ke SATU penerima gagal,
     * approve()/reject() TETAP berhasil (statusnya tetap tersimpan) dan
     * penerima lain yang di-checklist tetap dicoba -- notifikasi WA
     * cuma bonus, bukan syarat.
     *
     * Penerima ditentukan oleh 3 checklist independen di App\Models\
     * JadwalReminderSetting (reschedule_notify_pengajar/_requester/
     * _admin -- lihat migration-nya untuk kenapa independen, bukan
     * enum tunggal seperti remind_target milik pengingat), diisi admin
     * lewat App\Http\Controllers\Jadwal\JadwalReminderSettingController.
     * Isi pesannya SAMA untuk ketiga penerima (satu template per
     * outcome approve/reject, bukan per-penerima) -- kalau nanti perlu
     * dibedakan per penerima, itu perluasan terpisah.
     */
    protected function sendRescheduleNotifications(JadwalKelasRescheduleRequest $reschedule, string $outcome): void
    {
        $company = $reschedule->company;

        if (! $company || ! $this->packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES)) {
            return;
        }

        $setting = JadwalReminderSetting::where('company_id', $company->id)->first();

        if (! $setting || ! $setting->device_id) {
            return;
        }

        $owner = $company->user;

        if (! $owner) {
            return;
        }

        $recipients = array_unique(array_filter([
            $setting->reschedule_notify_requester ? $reschedule->requester_phone : null,
            $setting->reschedule_notify_pengajar ? $reschedule->jadwalStudent?->pengajar?->handphone : null,
            $setting->reschedule_notify_admin ? $owner->handphone : null,
        ]));

        if (empty($recipients)) {
            return;
        }

        try {
            $token = $this->jwtService->mintFor($owner);
        } catch (Throwable $e) {
            Log::warning('JadwalRescheduleRequestController: gagal membuat token pengirim WA', [
                'request_id' => $reschedule->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $message = $this->composeRescheduleMessage($reschedule, $setting, $outcome);

        foreach ($recipients as $phone) {
            try {
                $jid = PhoneNumber::normalize($phone).'@s.whatsapp.net';
                $this->inbox->send($token, $setting->device_id, $jid, $message);
            } catch (Throwable $e) {
                Log::warning('JadwalRescheduleRequestController: gagal mengirim notifikasi WA reschedule', [
                    'request_id' => $reschedule->id,
                    'outcome' => $outcome,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Susun pesan notifikasi reschedule -- pakai App\Models\
     * WaMessageTemplate milik company (kalau ada, aktif & approved,
     * beda slot untuk approved/rejected -- lihat App\Models\
     * JadwalReminderSetting::waMessageTemplateRescheduleApproved()/
     * waMessageTemplateRescheduleRejected()), fallback ke pesan default
     * kalau tidak diisi. Pola strtr() tag sama seperti App\Jobs\
     * SendJadwalReminder::composeMessage().
     */
    protected function composeRescheduleMessage(JadwalKelasRescheduleRequest $reschedule, JadwalReminderSetting $setting, string $outcome): string
    {
        $template = $outcome === JadwalKelasRescheduleRequest::STATUS_APPROVED
            ? $setting->waMessageTemplateRescheduleApproved
            : $setting->waMessageTemplateRescheduleRejected;

        $body = ($template && $template->status === 'active' && $template->review_status === 'approved')
            ? $template->composedMessage()
            : $this->defaultRescheduleMessage($outcome);

        $student = $reschedule->jadwalStudent;

        $tags = [
            '{{nama_murid}}' => $student?->name ?? '-',
            '{{nama_pengajar}}' => $student?->pengajar?->name ?? '-',
            '{{mata_pelajaran}}' => $student?->mataPelajaran?->name ?? '-',
            '{{catatan_staff}}' => $reschedule->staff_notes ?? '-',
            '{{nama_perusahaan}}' => $reschedule->company?->name ?? '-',
        ];

        return strtr($body, $tags);
    }

    protected function defaultRescheduleMessage(string $outcome): string
    {
        return $outcome === JadwalKelasRescheduleRequest::STATUS_APPROVED
            ? 'Permintaan ubah jadwal {{mata_pelajaran}} untuk {{nama_murid}} sudah disetujui dan diproses. Terima kasih.'
            : 'Mohon maaf, permintaan ubah jadwal {{mata_pelajaran}} untuk {{nama_murid}} belum bisa kami setujui. Alasan: {{catatan_staff}}';
    }
}
