<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\JadwalKelas;
use App\Models\JadwalKelasMurid;
use App\Models\JadwalKelasSesi;
use App\Models\JadwalKelasSesiMurid;
use App\Services\Jadwal\JadwalAvailabilityService;
use App\Services\Jadwal\JadwalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Generates dated JadwalKelasSesi occurrences (+ one JadwalKelasSesiMurid
 * row per actively-enrolled murid) from a JadwalKelas's recurring
 * hari/jam pattern, and lets an admin manually override one murid's
 * attendance/reschedule status for a given date — the same status
 * column the WA-reply auto-confirm flow (App\Http\Controllers\Api\
 * WaIncomingMessageWebhookController) updates automatically, just
 * reached from the dashboard instead of a WhatsApp reply. Both paths
 * write to the same row, so "an admin fixed this by hand" and "murid
 * replied on WA" are both visible via confirmation_channel.
 */
class JadwalKelasSesiController extends Controller
{
    use ResolvesCompanyContext;

    /** Carbon::dayOfWeek (0=Minggu..6=Sabtu) keyed by this app's Indonesian day names. */
    private const HARI_TO_CARBON_DOW = [
        'Minggu' => 0,
        'Senin' => 1,
        'Selasa' => 2,
        'Rabu' => 3,
        'Kamis' => 4,
        'Jumat' => 5,
        'Sabtu' => 6,
    ];

    public function __construct(
        protected JadwalNotificationService $notifier,
        protected JadwalAvailabilityService $availability
    ) {
    }

    /**
     * Creates one JadwalKelasSesi per date matching the class's `hari`
     * within [tanggal_mulai, tanggal_selesai] (inclusive, max 90 days
     * per call — generating a year at once isn't a realistic single
     * request and risks timing out), skipping any date that already has
     * a sesi (unique(jadwal_kelas_id, tanggal) also guards this at the
     * DB level). Every newly created sesi gets one JadwalKelasSesiMurid
     * row per currently-active enrolled murid.
     */
    public function generate(Request $request, string $jadwalKelasId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $jadwalKelas = $this->findJadwalKelasOrFail($context, $jadwalKelasId);

        $validator = Validator::make($request->all(), [
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.jadwal-kelas.show', $jadwalKelas->id)
                ->withErrors($validator);
        }

        $validated = $validator->validated();

        $start = Carbon::parse($validated['tanggal_mulai'])->startOfDay();
        $end = Carbon::parse($validated['tanggal_selesai'])->startOfDay();

        if ($start->diffInDays($end) > 90) {
            return redirect()
                ->route('jadwal.jadwal-kelas.show', $jadwalKelas->id)
                ->with('error', 'Rentang tanggal maksimal 90 hari per generate.');
        }

        $targetDow = self::HARI_TO_CARBON_DOW[$jadwalKelas->hari] ?? null;

        if ($targetDow === null) {
            return redirect()
                ->route('jadwal.jadwal-kelas.show', $jadwalKelas->id)
                ->with('error', 'Hari pada jadwal kelas ini tidak valid.');
        }

        $activeMuridIds = $jadwalKelas->murid()->where('status', 'active')->pluck('id');

        $created = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if ((int) $cursor->dayOfWeek === $targetDow) {
                $created += $this->createSesiForDate($jadwalKelas, $cursor->copy(), $activeMuridIds);
            }

            $cursor->addDay();
        }

        return redirect()
            ->route('jadwal.jadwal-kelas.show', $jadwalKelas->id)
            ->with('success', "{$created} sesi berhasil dibuat.");
    }

    /**
     * Admin manual override of one murid's status for one sesi —
     * confirmation_channel is stamped 'admin_manual' so this is
     * distinguishable later from a genuine WA-reply confirmation.
     */
    public function updateStatus(Request $request, string $sesiMuridId): RedirectResponse
    {
        $context = $this->companyContext($request);

        $sesiMurid = JadwalKelasSesiMurid::with('sesi.jadwalKelas')->findOrFail($sesiMuridId);
        $jadwalKelas = $sesiMurid->sesi->jadwalKelas;

        abort_unless($jadwalKelas && $jadwalKelas->company_id === $context->company->id, 403);

        if ($context->isLockedToBranch() && $jadwalKelas->branch_office_id !== $context->branchOffice?->id) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:terjadwal,hadir,izin,pindah_hari,tidak_ada_kabar'],
            'tanggal_pindah' => ['nullable', 'required_if:status,pindah_hari', 'date'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.jadwal-kelas.show', $jadwalKelas->id)
                ->withErrors($validator);
        }

        $validated = $validator->validated();

        $sesiMurid->update([
            'status' => $validated['status'],
            'tanggal_pindah' => $validated['status'] === 'pindah_hari' ? ($validated['tanggal_pindah'] ?? null) : null,
            'catatan' => $validated['catatan'] ?? null,
            'confirmed_at' => now(),
            'confirmation_channel' => 'admin_manual',
        ]);

        return redirect()
            ->route('jadwal.jadwal-kelas.show', $jadwalKelas->id)
            ->with('success', 'Status kehadiran murid berhasil diperbarui.');
    }

    /**
     * "Guru nya memajukan jadwalnya, biasanya ngajar dari jam 13.00
     * tiba-tiba dimajukan start ngajar nya dari jam 12.00" — a one-off
     * time change for THIS date only (jam_mulai_override/
     * jam_selesai_override), deliberately separate from
     * JadwalKelasController::update() which changes the recurring
     * pattern for every future date. Notifies the guru (or whoever's
     * actually teaching this date, if already reassigned via
     * assignPengganti() below) and every murid scheduled this date.
     */
    public function rescheduleTime(Request $request, string $sesiId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $sesi = $this->findSesiOrFail($context, $sesiId);

        $validator = Validator::make($request->all(), [
            'jam_mulai_override' => ['required', 'date_format:H:i'],
            'jam_selesai_override' => ['required', 'date_format:H:i', 'after:jam_mulai_override'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.jadwal-kelas.show', $sesi->jadwal_kelas_id)
                ->withErrors($validator);
        }

        $validated = $validator->validated();

        $jadwalKelas = $sesi->jadwalKelas;
        $oldJamMulai = substr((string) ($sesi->jam_mulai_override ?: $jadwalKelas->jam_mulai), 0, 5);
        $oldJamSelesai = substr((string) ($sesi->jam_selesai_override ?: $jadwalKelas->jam_selesai), 0, 5);

        $sesi->update([
            'jam_mulai_override' => $validated['jam_mulai_override'],
            'jam_selesai_override' => $validated['jam_selesai_override'],
            'catatan' => trim(($sesi->catatan ? $sesi->catatan."\n" : '').($validated['catatan'] ?? "Jam diubah dari admin: {$oldJamMulai}-{$oldJamSelesai} -> {$validated['jam_mulai_override']}-{$validated['jam_selesai_override']}.")),
        ]);

        $this->notifySesiTimeChanged($sesi, $oldJamMulai, $oldJamSelesai, $validated['jam_mulai_override'], $validated['jam_selesai_override']);

        return redirect()
            ->route('jadwal.jadwal-kelas.show', $sesi->jadwal_kelas_id)
            ->with('success', 'Jam sesi berhasil diubah dan notifikasi sudah dikirim.');
    }

    /**
     * "Gurunya sakit dan tidak bisa mengajar" — flags this one sesi,
     * step one of two (see penggantiForm()/assignPengganti() below for
     * the actual substitute pick). Doesn't touch JadwalKelas.guru_user_id
     * itself — the regular guru is still the regular guru for every
     * OTHER date, this only concerns the one sesi row.
     */
    public function markSakit(Request $request, string $sesiId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $sesi = $this->findSesiOrFail($context, $sesiId);

        $validator = Validator::make($request->all(), [
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.jadwal-kelas.show', $sesi->jadwal_kelas_id)
                ->withErrors($validator);
        }

        $sesi->update([
            'guru_status' => 'sakit',
            'guru_pengganti_user_id' => null,
            'catatan' => trim(($sesi->catatan ? $sesi->catatan."\n" : '').($request->string('catatan')->value() ?: 'Guru berhalangan (sakit).')),
        ]);

        return redirect()
            ->route('jadwal.jadwal-kelas.sesi.pengganti', $sesi->id)
            ->with('success', 'Sesi ditandai guru berhalangan. Berikut kandidat pengganti yang tersedia.');
    }

    /**
     * "Cari pengganti harinya sesuai by sistem, baru bisa adjust lagi" —
     * shows App\Services\Jadwal\JadwalAvailabilityService::
     * findSubstituteGuru()'s suggestions (guru yang sudah pernah mengajar
     * mata pelajaran yang sama diprioritaskan) PLUS the full company
     * roster as a manual fallback, since the system's guess is a
     * starting point, not the final word — admin can always override to
     * anyone.
     */
    public function penggantiForm(Request $request, string $sesiId): View
    {
        $context = $this->companyContext($request);
        $sesi = $this->findSesiOrFail($context, $sesiId);
        $sesi->load('jadwalKelas.mataPelajaran', 'jadwalKelas.guru', 'jadwalKelas.branchOffice');

        $suggestions = $this->availability->findSubstituteGuru($sesi);
        $suggestedIds = $suggestions->pluck('id');

        $allTeamMembers = $this->companyTeamMembers($context->company, $sesi->jadwalKelas->branch_office_id)
            ->reject(fn ($user) => $user->id === $sesi->jadwalKelas->guru_user_id || $suggestedIds->contains($user->id));

        return view('jadwal.jadwal-kelas.pengganti', [
            'sesi' => $sesi,
            'suggestions' => $suggestions,
            'allTeamMembers' => $allTeamMembers,
        ]);
    }

    public function assignPengganti(Request $request, string $sesiId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $sesi = $this->findSesiOrFail($context, $sesiId);
        $sesi->load('jadwalKelas.mataPelajaran', 'jadwalKelas.guru', 'jadwalKelas.murid.murid');

        $validator = Validator::make($request->all(), [
            'guru_pengganti_user_id' => [
                'required', 'uuid', 'exists:users,id',
                function ($attribute, $value, $fail) use ($context) {
                    if (! $this->companyTeamMembers($context->company)->contains('id', $value)) {
                        $fail('Guru pengganti harus anggota tim di company ini.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.jadwal-kelas.sesi.pengganti', $sesi->id)
                ->withErrors($validator);
        }

        $penggantiId = $validator->validated()['guru_pengganti_user_id'];

        $sesi->update([
            'guru_status' => 'diganti',
            'guru_pengganti_user_id' => $penggantiId,
        ]);

        $this->notifyGuruPengganti($sesi, $penggantiId);

        return redirect()
            ->route('jadwal.jadwal-kelas.show', $sesi->jadwal_kelas_id)
            ->with('success', 'Guru pengganti berhasil ditugaskan dan notifikasi sudah dikirim.');
    }

    /**
     * "Ada murid tidak masuk dan pengen geser jadwal, sistem langsung
     * mencari... memberikan pilihan available nya" — step one: show what
     * the system found. An empty $slots is the "menolak" outcome — the
     * view tells the admin there's nothing to offer and points at the
     * plain manual tanggal_pindah override (already on the class's show
     * page) instead.
     */
    public function alternatifMurid(Request $request, string $sesiMuridId): View
    {
        $sesiMurid = $this->findSesiMuridOrFail($request, $sesiMuridId);

        $slots = $this->availability->findAlternativeSlotsForMurid($sesiMurid);

        return view('jadwal.jadwal-kelas.alternatif-murid', [
            'sesiMurid' => $sesiMurid,
            'slots' => $slots,
        ]);
    }

    /**
     * Step two: admin picks one of the offered slots. Re-runs the exact
     * same availability search server-side before committing to
     * anything — the list shown in alternatifMurid() could be stale by
     * the time this is submitted (someone else filled the last seat),
     * so a tampered or outdated selection just bounces back with an
     * error instead of silently overbooking a class.
     *
     * Moving the murid doesn't touch their ORIGINAL enrollment/roster —
     * it enrolls them into the target class too (reactivating if they'd
     * left before) and creates a normal JadwalKelasSesiMurid row there
     * with reminder_sent_at already stamped, so the EXISTING WA
     * reminder-confirm flow (App\Http\Controllers\Api\
     * WaIncomingMessageWebhookController::tryConfirmAsMurid()) picks up
     * their YA/TIDAK reply exactly like any other pending confirmation —
     * "apakah berminat" doesn't need its own separate WA state machine.
     */
    public function requestPindah(Request $request, string $sesiMuridId): RedirectResponse
    {
        $sesiMurid = $this->findSesiMuridOrFail($request, $sesiMuridId);

        $validator = Validator::make($request->all(), [
            'jadwal_kelas_id' => ['required', 'uuid'],
            'tanggal' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.jadwal-kelas.sesi-murid.alternatif', $sesiMurid->id)
                ->withErrors($validator);
        }

        $validated = $validator->validated();

        $freshSlots = $this->availability->findAlternativeSlotsForMurid($sesiMurid);
        $chosen = $freshSlots->first(fn ($slot) => $slot['jadwal_kelas']->id === $validated['jadwal_kelas_id']
            && $slot['tanggal']->toDateString() === $validated['tanggal']);

        if (! $chosen) {
            return redirect()
                ->route('jadwal.jadwal-kelas.sesi-murid.alternatif', $sesiMurid->id)
                ->with('error', 'Slot yang dipilih sudah tidak tersedia (mungkin sudah penuh). Silakan pilih slot lain.');
        }

        $targetJadwalKelas = $chosen['jadwal_kelas'];
        $targetTanggal = $chosen['tanggal'];

        $result = DB::transaction(function () use ($sesiMurid, $targetJadwalKelas, $targetTanggal) {
            $locked = JadwalKelasSesiMurid::whereKey($sesiMurid->id)->lockForUpdate()->first();

            if (! $locked || $locked->status === 'pindah_hari') {
                return null;
            }

            $muridUserId = $sesiMurid->jadwalKelasMurid->murid_user_id;

            $enrollment = JadwalKelasMurid::firstOrCreate(
                ['jadwal_kelas_id' => $targetJadwalKelas->id, 'murid_user_id' => $muridUserId],
                ['status' => 'active']
            );

            if (! $enrollment->wasRecentlyCreated && $enrollment->status !== 'active') {
                $enrollment->update(['status' => 'active', 'joined_at' => now()]);
            }

            $targetSesi = JadwalKelasSesi::firstOrCreate(
                ['jadwal_kelas_id' => $targetJadwalKelas->id, 'tanggal' => $targetTanggal->toDateString()],
                ['status' => 'terjadwal']
            );

            $targetSesiMurid = JadwalKelasSesiMurid::firstOrCreate(
                ['jadwal_kelas_sesi_id' => $targetSesi->id, 'jadwal_kelas_murid_id' => $enrollment->id],
                ['status' => 'terjadwal', 'reminder_sent_at' => now()]
            );

            if (! $targetSesiMurid->wasRecentlyCreated && $targetSesiMurid->confirmed_at === null) {
                $targetSesiMurid->update(['reminder_sent_at' => now()]);
            }

            $locked->update([
                'status' => 'pindah_hari',
                'tanggal_pindah' => $targetTanggal->toDateString(),
                'pindah_ke_sesi_id' => $targetSesi->id,
                'confirmed_at' => now(),
                'confirmation_channel' => 'admin_manual',
            ]);

            return $targetSesiMurid;
        });

        if (! $result) {
            return redirect()
                ->route('jadwal.jadwal-kelas.show', $sesiMurid->sesi->jadwal_kelas_id)
                ->with('error', 'Murid ini sudah diproses sebelumnya (mungkin oleh permintaan ganda).');
        }

        $this->notifyMuridPindah($sesiMurid, $targetJadwalKelas, $targetTanggal);

        return redirect()
            ->route('jadwal.jadwal-kelas.show', $sesiMurid->sesi->jadwal_kelas_id)
            ->with('success', 'Murid dipindahkan ke jadwal pengganti. Konfirmasi via WA sudah dikirim.');
    }

    private function findSesiOrFail($context, string $id): JadwalKelasSesi
    {
        $sesi = JadwalKelasSesi::with('jadwalKelas')->findOrFail($id);
        $jadwalKelas = $sesi->jadwalKelas;

        abort_unless($jadwalKelas && $jadwalKelas->company_id === $context->company->id, 403);

        if ($context->isLockedToBranch() && $jadwalKelas->branch_office_id !== $context->branchOffice?->id) {
            abort(403);
        }

        return $sesi;
    }

    private function findSesiMuridOrFail(Request $request, string $sesiMuridId): JadwalKelasSesiMurid
    {
        $context = $this->companyContext($request);

        $sesiMurid = JadwalKelasSesiMurid::with('sesi.jadwalKelas', 'jadwalKelasMurid.murid')->findOrFail($sesiMuridId);
        $jadwalKelas = $sesiMurid->sesi->jadwalKelas ?? null;

        abort_unless($jadwalKelas && $jadwalKelas->company_id === $context->company->id, 403);

        if ($context->isLockedToBranch() && $jadwalKelas->branch_office_id !== $context->branchOffice?->id) {
            abort(403);
        }

        return $sesiMurid;
    }

    private function notifySesiTimeChanged(JadwalKelasSesi $sesi, string $oldMulai, string $oldSelesai, string $newMulai, string $newSelesai): void
    {
        $sesi->load('jadwalKelas.mataPelajaran', 'jadwalKelas.guru', 'muridSesi.jadwalKelasMurid.murid');
        $jadwalKelas = $sesi->jadwalKelas;
        $label = $jadwalKelas->name ?: $jadwalKelas->mataPelajaran?->name;
        $tanggal = Carbon::parse($sesi->tanggal)->translatedFormat('l, d M Y');
        $arah = $newMulai < $oldMulai ? 'DIMAJUKAN' : 'DIUNDUR';

        $message = "Perhatian: kelas *{$label}* pada {$tanggal} {$arah} — dari jam {$oldMulai}-{$oldSelesai} menjadi *{$newMulai}-{$newSelesai}* (khusus tanggal ini saja). Mohon dicatat ya.";

        $guru = $sesi->guru_pengganti_user_id ? $sesi->guruPengganti : $jadwalKelas->guru;

        if ($guru) {
            $this->notifier->send($jadwalKelas, $guru, $message);
        }

        foreach ($sesi->muridSesi as $sm) {
            if ($sm->jadwalKelasMurid?->murid) {
                $this->notifier->send($jadwalKelas, $sm->jadwalKelasMurid->murid, $message);
            }
        }
    }

    private function notifyGuruPengganti(JadwalKelasSesi $sesi, string $penggantiId): void
    {
        $jadwalKelas = $sesi->jadwalKelas;
        $label = $jadwalKelas->name ?: $jadwalKelas->mataPelajaran?->name;
        $tanggal = Carbon::parse($sesi->tanggal)->translatedFormat('l, d M Y');
        $jam = substr((string) ($sesi->jam_mulai_override ?: $jadwalKelas->jam_mulai), 0, 5).'-'.substr((string) ($sesi->jam_selesai_override ?: $jadwalKelas->jam_selesai), 0, 5);

        $penggantiUser = \App\Models\User::find($penggantiId);

        if ($penggantiUser) {
            $this->notifier->send(
                $jadwalKelas,
                $penggantiUser,
                "Halo {$penggantiUser->name}, Anda ditugaskan menggantikan mengajar kelas *{$label}* pada {$tanggal}, jam {$jam}, karena guru biasa berhalangan. Mohon konfirmasi kesediaannya."
            );
        }

        if ($jadwalKelas->guru) {
            $this->notifier->send(
                $jadwalKelas,
                $jadwalKelas->guru,
                "Halo {$jadwalKelas->guru->name}, kelas *{$label}* Anda pada {$tanggal} sudah digantikan oleh ".($penggantiUser->name ?? '-').". Semoga lekas sehat."
            );
        }

        foreach ($jadwalKelas->murid->where('status', 'active') as $enrollment) {
            if ($enrollment->murid) {
                $this->notifier->send(
                    $jadwalKelas,
                    $enrollment->murid,
                    "Info: kelas *{$label}* pada {$tanggal} jam {$jam} akan diajar oleh guru pengganti ".($penggantiUser->name ?? '-').", karena guru biasa berhalangan."
                );
            }
        }
    }

    private function notifyMuridPindah(JadwalKelasSesiMurid $sesiMurid, JadwalKelas $targetJadwalKelas, Carbon $targetTanggal): void
    {
        $murid = $sesiMurid->jadwalKelasMurid?->murid;

        if (! $murid) {
            return;
        }

        $targetJadwalKelas->loadMissing('mataPelajaran');
        $label = $targetJadwalKelas->name ?: $targetJadwalKelas->mataPelajaran?->name;
        $tanggal = $targetTanggal->translatedFormat('l, d M Y');
        $jam = substr((string) $targetJadwalKelas->jam_mulai, 0, 5).'-'.substr((string) $targetJadwalKelas->jam_selesai, 0, 5);

        $this->notifier->send(
            $targetJadwalKelas,
            $murid,
            "Halo {$murid->name}, Anda dijadwalkan pindah ke kelas *{$label}* pada {$tanggal}, jam {$jam}. Balas *YA* untuk konfirmasi berminat hadir, atau *TIDAK* jika tidak berminat."
        );
    }

    private function createSesiForDate(JadwalKelas $jadwalKelas, Carbon $date, $activeMuridIds): int
    {
        return DB::transaction(function () use ($jadwalKelas, $date, $activeMuridIds) {
            $sesi = JadwalKelasSesi::firstOrCreate(
                ['jadwal_kelas_id' => $jadwalKelas->id, 'tanggal' => $date->toDateString()],
                ['status' => 'terjadwal']
            );

            if (! $sesi->wasRecentlyCreated) {
                return 0;
            }

            foreach ($activeMuridIds as $jadwalKelasMuridId) {
                JadwalKelasSesiMurid::firstOrCreate([
                    'jadwal_kelas_sesi_id' => $sesi->id,
                    'jadwal_kelas_murid_id' => $jadwalKelasMuridId,
                ]);
            }

            return 1;
        });
    }

    private function findJadwalKelasOrFail($context, string $id): JadwalKelas
    {
        $query = JadwalKelas::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }
}
