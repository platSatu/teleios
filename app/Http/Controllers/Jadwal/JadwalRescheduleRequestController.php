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
            ->with(['jadwalStudent:id,name,jadwal_mata_pelajaran_id,pengajar_id', 'jadwalStudent.mataPelajaran:id,name', 'jadwalStudent.pengajar:id,name', 'reviewer:id,name']);

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

        $this->notifyRequester($reschedule, 'Permintaan ubah jadwal Anda sudah kami setujui dan proses. Terima kasih.');

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

        $this->notifyRequester($reschedule, 'Mohon maaf, permintaan ubah jadwal Anda belum bisa kami setujui. '.$reschedule->staff_notes);

        return redirect()->route('jadwal.reschedule-requests.index')->with('success', 'Permintaan reschedule ditolak.');
    }

    /**
     * Best-effort, sama seperti App\Jobs\SendJadwalReminder -- kalau
     * company tidak (lagi) punya package aktif kategori Chat/WhatsApp,
     * device belum diatur, atau pengiriman gagal, approve()/reject()
     * TETAP berhasil (statusnya tetap tersimpan) -- konfirmasi WA
     * cuma bonus, bukan syarat.
     */
    protected function notifyRequester(JadwalKelasRescheduleRequest $reschedule, string $message): void
    {
        if (! $reschedule->requester_phone) {
            return;
        }

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

        try {
            $token = $this->jwtService->mintFor($owner);
            $jid = PhoneNumber::normalize($reschedule->requester_phone).'@s.whatsapp.net';
            $this->inbox->send($token, $setting->device_id, $jid, $message);
        } catch (Throwable $e) {
            Log::warning('JadwalRescheduleRequestController: gagal mengirim konfirmasi WA', [
                'request_id' => $reschedule->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
