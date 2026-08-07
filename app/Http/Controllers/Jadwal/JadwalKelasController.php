<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalKelas;
use App\Models\JadwalKelasMurid;
use App\Models\JadwalKelasSesi;
use App\Models\JadwalUsulanPerubahan;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Services\Jadwal\JadwalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for the recurring weekly JadwalKelas "template". show() is where
 * the roster (murid) and dated occurrences (sesi) for one class are
 * managed — see Jadwal\JadwalKelasMuridController and Jadwal\
 * JadwalKelasSesiController, both nested under this same page rather
 * than getting their own top-level index (they only ever make sense in
 * the context of one specific class).
 */
class JadwalKelasController extends Controller
{
    use ResolvesCompanyContext;

    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    public function __construct(protected JadwalNotificationService $notifier)
    {
    }

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = JadwalKelas::where('company_id', $company->id)
            ->with(['mataPelajaran:id,name', 'branchOffice:id,name', 'guru:id,name'])
            ->withCount('murid');

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        if ($request->filled('branch_office_id') && $context->isOwner) {
            $query->where('branch_office_id', $request->string('branch_office_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $jadwalKelas = $query->latest()->paginate(10)->withQueryString();

        return view('jadwal.jadwal-kelas.index', [
            'jadwalKelas' => $jadwalKelas,
            'branchOffices' => $this->branchOfficesFor($company, $context),
            'hariList' => self::HARI,
        ]);
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        // Supports arriving here from the Mata Pelajaran index's "Tambah
        // Jadwal" button (branch_office_id & mata_pelajaran_id passed as
        // query params) — those two fields get locked/disabled in the
        // form so the class is guaranteed to be created under the exact
        // subject the admin clicked from, not silently re-picked.
        $lockedBranchOfficeId = $request->query('branch_office_id');
        $lockedMataPelajaranId = $request->query('mata_pelajaran_id');

        $data = $this->formData(null, $context, $lockedBranchOfficeId);

        // Only honor the lock if both values actually resolve to
        // something this company (and this user, if branch-locked) can
        // legitimately see — a tampered query string should just fall
        // back to the normal, unlocked picker rather than silently
        // submitting a bogus id.
        $validLock = $lockedBranchOfficeId
            && $data['branchOffices']->contains('id', $lockedBranchOfficeId)
            && $lockedMataPelajaranId
            && $data['mataPelajaranList']->contains('id', $lockedMataPelajaranId);

        $data['lockedBranchOfficeId'] = $validLock ? $lockedBranchOfficeId : null;
        $data['lockedMataPelajaranId'] = $validLock ? $lockedMataPelajaranId : null;

        return view('jadwal.jadwal-kelas.create', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.jadwal-kelas.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $jadwalKelas = JadwalKelas::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'],
            'mata_pelajaran_id' => $validated['mata_pelajaran_id'],
            'guru_user_id' => $validated['guru_user_id'] ?? null,
            'device_id' => $validated['device_id'] ?? null,
            'name' => $validated['name'] ?? null,
            'hari' => $validated['hari'],
            'jam_mulai' => $validated['jam_mulai'],
            'jam_selesai' => $validated['jam_selesai'],
            'kapasitas' => $validated['kapasitas'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->notifyClassCreated($jadwalKelas);
        $this->enrollMurid($jadwalKelas, $validated['murid_user_id'] ?? []);

        return redirect()
            ->route('jadwal.jadwal-kelas.index')
            ->with('success', 'Jadwal kelas berhasil ditambahkan.');
    }

    public function show(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $jadwalKelas = $this->findOrFail($context, $id)
            ->load([
                'mataPelajaran:id,name',
                'branchOffice:id,name',
                'guru:id,name,handphone',
                'murid.murid:id,name,handphone',
            ]);

        $sesi = JadwalKelasSesi::where('jadwal_kelas_id', $jadwalKelas->id)
            ->withCount('muridSesi')
            // Eager-loaded (not left to lazy-load per row) since
            // show.blade.php renders every paginated sesi's full
            // per-murid attendance table below the summary list —
            // avoids one query per sesi row for muridSesi and another
            // per muridSesi row for jadwalKelasMurid.murid.
            ->with('muridSesi.jadwalKelasMurid.murid:id,name')
            ->orderByDesc('tanggal')
            ->paginate(10, ['*'], 'sesi_page')
            ->withQueryString();

        $availableMurid = $this->companyTeamMembers($context->company, $jadwalKelas->branch_office_id)
            ->reject(fn ($user) => $jadwalKelas->murid->pluck('murid_user_id')->contains($user->id));

        $usulanPerubahan = JadwalUsulanPerubahan::where('jadwal_kelas_id', $jadwalKelas->id)
            ->with('murid:id,name')
            ->latest()
            ->limit(10)
            ->get();

        return view('jadwal.jadwal-kelas.show', [
            'jadwalKelas' => $jadwalKelas,
            'sesi' => $sesi,
            'availableMurid' => $availableMurid,
            'usulanPerubahan' => $usulanPerubahan,
        ]);
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $item = $this->findOrFail($context, $id);

        return view('jadwal.jadwal-kelas.edit', $this->formData($item, $context));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $item = $this->findOrFail($context, $id);

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.jadwal-kelas.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $before = $item->only(['hari', 'jam_mulai', 'jam_selesai', 'guru_user_id']);

        $item->update([
            'branch_office_id' => $validated['branch_office_id'],
            'mata_pelajaran_id' => $validated['mata_pelajaran_id'],
            'guru_user_id' => $validated['guru_user_id'] ?? null,
            'device_id' => $validated['device_id'] ?? null,
            'name' => $validated['name'] ?? null,
            'hari' => $validated['hari'],
            'jam_mulai' => $validated['jam_mulai'],
            'jam_selesai' => $validated['jam_selesai'],
            'kapasitas' => $validated['kapasitas'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        $scheduleChanged = $before['hari'] !== $item->hari
            || substr((string) $before['jam_mulai'], 0, 5) !== substr((string) $item->jam_mulai, 0, 5)
            || substr((string) $before['jam_selesai'], 0, 5) !== substr((string) $item->jam_selesai, 0, 5);

        if ($scheduleChanged) {
            $this->notifyScheduleChanged($item, $before);
        }

        return redirect()
            ->route('jadwal.jadwal-kelas.index')
            ->with('success', 'Jadwal kelas berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $item = $this->findOrFail($context, $id);
        $item->delete();

        return redirect()
            ->route('jadwal.jadwal-kelas.index')
            ->with('success', 'Jadwal kelas berhasil dihapus.');
    }

    private function formData(?JadwalKelas $item, $context, ?string $prefillBranchOfficeId = null): array
    {
        $branchOffices = $this->branchOfficesFor($context->company, $context);

        $branchOfficeId = $item->branch_office_id
            ?? $prefillBranchOfficeId
            ?? (! $context->isOwner ? $context->branchOffice?->id : null);

        $mataPelajaranQuery = MataPelajaran::where('company_id', $context->company->id)
            ->where('status', 'active');

        if ($branchOfficeId) {
            $mataPelajaranQuery->where('branch_office_id', $branchOfficeId);
        }

        // Same team-members pool as guruList — this app deliberately has
        // no separate "murid" table, a company member is a murid (or
        // guru, or both) purely by the CompanyRole they're assigned, so
        // there's nothing more specific to filter by here.
        $teamMembers = $this->companyTeamMembers($context->company, $branchOfficeId);

        return [
            'item' => $item,
            'branchOffices' => $branchOffices,
            'mataPelajaranList' => $mataPelajaranQuery->orderBy('name')->get(['id', 'name', 'branch_office_id', 'durasi_menit']),
            'guruList' => $teamMembers,
            'muridList' => $teamMembers,
            'hariList' => self::HARI,
        ];
    }

    /**
     * Optional bulk-enroll straight from the "Tambah Jadwal Kelas" form
     * (murid_user_id[] — see resources/views/jadwal/jadwal-kelas/
     * _form.blade.php) — same enrollment rows JadwalKelasMuridController::
     * store() creates one at a time from the class's own detail page,
     * just batched here so an admin doesn't have to create the class
     * first and then revisit it just to add the students who were
     * already decided on at creation time. Every enrolled murid also
     * gets the same "you're in this class" WA notice guru/owner already
     * get in notifyClassCreated() above.
     */
    private function enrollMurid(JadwalKelas $jadwalKelas, array $muridUserIds): void
    {
        if ($muridUserIds === []) {
            return;
        }

        $label = $jadwalKelas->name ?: $jadwalKelas->mataPelajaran?->name;
        $jadwal = "{$jadwalKelas->hari}, ".substr((string) $jadwalKelas->jam_mulai, 0, 5).'-'.substr((string) $jadwalKelas->jam_selesai, 0, 5);

        foreach (array_unique($muridUserIds) as $muridUserId) {
            $enrollment = JadwalKelasMurid::firstOrCreate(
                ['jadwal_kelas_id' => $jadwalKelas->id, 'murid_user_id' => $muridUserId],
                ['status' => 'active']
            );

            if (! $enrollment->wasRecentlyCreated && $enrollment->status !== 'active') {
                $enrollment->update(['status' => 'active', 'joined_at' => now()]);
            }

            $murid = User::find($muridUserId);

            if ($murid) {
                $this->notifier->send(
                    $jadwalKelas,
                    $murid,
                    "Halo {$murid->name}, Anda terdaftar di kelas *{$label}* setiap {$jadwal}. Sampai jumpa di kelas!"
                );
            }
        }
    }

    private function branchOfficesFor(Company $company, $context)
    {
        $query = BranchOffice::where('company_id', $company->id);

        if (! $context->isOwner) {
            $query->where('id', $context->branchOffice?->id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function findOrFail($context, string $id): JadwalKelas
    {
        $query = JadwalKelas::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }

    private function validator(Request $request, Company $company, ?string $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'branch_office_id' => [
                'required', 'uuid', 'exists:branch_offices,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! BranchOffice::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Cabang tidak valid.');
                    }
                },
            ],
            'mata_pelajaran_id' => [
                'required', 'uuid',
                function ($attribute, $value, $fail) use ($request) {
                    $branchOfficeId = $request->input('branch_office_id');

                    if ($value && ! MataPelajaran::where('id', $value)->where('branch_office_id', $branchOfficeId)->exists()) {
                        $fail('Mata pelajaran tidak valid untuk cabang yang dipilih.');
                    }
                },
            ],
            'guru_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'device_id' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:255'],
            'hari' => ['required', 'in:'.implode(',', self::HARI)],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'kapasitas' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'status' => ['nullable', 'in:active,inactive'],
            'murid_user_id' => ['nullable', 'array'],
            'murid_user_id.*' => ['uuid', 'exists:users,id'],
        ]);
    }

    /**
     * "Ketika ada perubahan kelas... harus update di sistem dan
     * memberikan notifikasi ke guru & murid" — the create-time half.
     * Notifies the assigned guru (if any) and the company owner
     * ("admin cabang" for now — there's no separate branch-admin
     * concept in CompanyRole yet beyond the role name itself).
     */
    private function notifyClassCreated(JadwalKelas $jadwalKelas): void
    {
        $jadwalKelas->loadMissing(['mataPelajaran', 'guru', 'company.user']);

        $label = $jadwalKelas->name ?: $jadwalKelas->mataPelajaran?->name;
        $jadwal = "{$jadwalKelas->hari}, ".substr((string) $jadwalKelas->jam_mulai, 0, 5).'-'.substr((string) $jadwalKelas->jam_selesai, 0, 5);

        if ($jadwalKelas->guru) {
            $this->notifier->send(
                $jadwalKelas,
                $jadwalKelas->guru,
                "Halo {$jadwalKelas->guru->name}, Anda dijadwalkan mengajar *{$label}* setiap {$jadwal}. Jadwal ini sudah aktif di sistem."
            );
        }

        $owner = $jadwalKelas->company?->user;

        if ($owner && $owner->id !== $jadwalKelas->guru_user_id) {
            $this->notifier->send(
                $jadwalKelas,
                $owner,
                "Jadwal kelas baru dibuat: *{$label}* ({$jadwal})".($jadwalKelas->guru ? ", guru: {$jadwalKelas->guru->name}." : ', guru belum ditentukan.')
            );
        }
    }

    /**
     * "Ketika ada perubahan kelas / atau jam itu harus update di sistem
     * dan memberikan notifikasi ke guru & murid" — the update-time
     * half. Notifies the (possibly reassigned) guru and every actively
     * enrolled murid that the recurring hari/jam pattern changed —
     * this is a WHOLE-CLASS change, distinct from one murid pindah
     * hari on a single date (see Jadwal\JadwalKelasSesiController::
     * updateStatus(), which only affects that one date/murid).
     */
    private function notifyScheduleChanged(JadwalKelas $jadwalKelas, array $before): void
    {
        $jadwalKelas->loadMissing(['mataPelajaran', 'guru', 'murid.murid']);

        $label = $jadwalKelas->name ?: $jadwalKelas->mataPelajaran?->name;
        $jadwalLama = $before['hari'].', '.substr((string) $before['jam_mulai'], 0, 5).'-'.substr((string) $before['jam_selesai'], 0, 5);
        $jadwalBaru = $jadwalKelas->hari.', '.substr((string) $jadwalKelas->jam_mulai, 0, 5).'-'.substr((string) $jadwalKelas->jam_selesai, 0, 5);

        $message = "Perhatian: jadwal kelas *{$label}* berubah dari {$jadwalLama} menjadi *{$jadwalBaru}*. Mohon dicatat ya.";

        if ($jadwalKelas->guru) {
            $this->notifier->send($jadwalKelas, $jadwalKelas->guru, $message);
        }

        foreach ($jadwalKelas->murid->where('status', 'active') as $enrollment) {
            $this->notifier->send($jadwalKelas, $enrollment->murid, $message);
        }
    }
}
