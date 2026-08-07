<?php

namespace App\Services\Jadwal;

use App\Models\JadwalKelas;
use App\Models\JadwalKelasSesi;
use App\Models\JadwalKelasSesiMurid;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * "Sistem langsung mencari, menolak, serta memberikan pilihan available
 * nya" (murid reschedule) and "gurunya sakit, cari pengganti harinya
 * sesuai by sistem" (substitute guru) — both are the same underlying
 * question asked from two different angles: "given this fixed
 * hari/jam/mata-pelajaran, who/what else in this company could cover
 * it?" This service is the one place both searches live, so the
 * capacity/overlap logic only has to be gotten right once.
 */
class JadwalAvailabilityService
{
    /**
     * Every OTHER active JadwalKelas in the same company teaching the
     * same mata_pelajaran (same branch preferred, but not required —
     * kursus places routinely run the same subject across cabang, and a
     * murid stuck on one date is better offered a cross-branch makeup
     * slot than none at all), with at least one open seat, occurring
     * within the next $lookAheadDays days.
     *
     * Capacity is checked against the CLASS's current active roster
     * count vs kapasitas (not a specific date's generated sesi — future
     * dates often don't have a JadwalKelasSesi row yet, since generate()
     * is a manual per-class action), so this works whether or not the
     * candidate class's sesi have been generated ahead of time.
     *
     * Returns an empty collection when nothing qualifies — the caller
     * (Jadwal\JadwalKelasSesiController::alternatifMurid()) treats that
     * as "menolak": there's nothing to offer, so the murid should be
     * routed to the plain manual tanggal_pindah override instead.
     *
     * @return Collection<int, array{jadwal_kelas: JadwalKelas, tanggal: Carbon, jam_mulai: string, jam_selesai: string, sisa_kapasitas: ?int}>
     */
    public function findAlternativeSlotsForMurid(JadwalKelasSesiMurid $sesiMurid, int $lookAheadDays = 14): Collection
    {
        $sesiMurid->loadMissing('sesi.jadwalKelas', 'jadwalKelasMurid');

        $current = $sesiMurid->sesi->jadwalKelas;
        $muridUserId = $sesiMurid->jadwalKelasMurid->murid_user_id;

        if (! $current) {
            return collect();
        }

        $candidates = JadwalKelas::where('company_id', $current->company_id)
            ->where('mata_pelajaran_id', $current->mata_pelajaran_id)
            ->where('status', 'active')
            ->where('id', '!=', $current->id)
            // Don't offer a class the murid is already enrolled in
            // (they'd just be double-booked with themselves).
            ->whereDoesntHave('murid', function ($q) use ($muridUserId) {
                $q->where('murid_user_id', $muridUserId)->where('status', 'active');
            })
            ->withCount(['murid' => fn ($q) => $q->where('status', 'active')])
            ->with(['mataPelajaran:id,name', 'branchOffice:id,name', 'guru:id,name'])
            ->get();

        $slots = collect();

        foreach ($candidates as $candidate) {
            $sisaKapasitas = $candidate->kapasitas
                ? max(0, $candidate->kapasitas - $candidate->murid_count)
                : null; // null = tak terbatas

            if ($candidate->kapasitas && $sisaKapasitas <= 0) {
                continue;
            }

            foreach ($this->upcomingDatesFor($candidate, $lookAheadDays) as $tanggal) {
                $slots->push([
                    'jadwal_kelas' => $candidate,
                    'tanggal' => $tanggal,
                    'jam_mulai' => $candidate->jam_mulai,
                    'jam_selesai' => $candidate->jam_selesai,
                    'sisa_kapasitas' => $sisaKapasitas,
                ]);
            }
        }

        return $slots->sortBy('tanggal')->values();
    }

    /**
     * Every OTHER company team member who already teaches at least one
     * class in this company (this app has no dedicated "guru" role flag
     * — see Jadwal\JadwalKelasController's own guruList — so "has taught
     * before" is the closest stand-in for "is a guru"), free at the
     * exact hari + jam this sesi needs, sorted with "already teaches
     * this same mata_pelajaran" candidates first (most likely to
     * actually be qualified to cover it).
     *
     * "Free" means: no other JadwalKelas of theirs recurs on the same
     * hari with an overlapping jam_mulai/jam_selesai window, AND they
     * aren't already the guru_pengganti (or original guru, handled by
     * the exclusion below) on some OTHER sesi that same tanggal with an
     * overridden time landing in the same window.
     *
     * @return Collection<int, User>
     */
    public function findSubstituteGuru(JadwalKelasSesi $sesi): Collection
    {
        $sesi->loadMissing('jadwalKelas');
        $jadwalKelas = $sesi->jadwalKelas;

        if (! $jadwalKelas) {
            return collect();
        }

        $jamMulai = $sesi->jam_mulai_override ?: $jadwalKelas->jam_mulai;
        $jamSelesai = $sesi->jam_selesai_override ?: $jadwalKelas->jam_selesai;

        $guruIds = JadwalKelas::where('company_id', $jadwalKelas->company_id)
            ->whereNotNull('guru_user_id')
            ->where('guru_user_id', '!=', $jadwalKelas->guru_user_id)
            ->distinct()
            ->pluck('guru_user_id');

        if ($guruIds->isEmpty()) {
            return collect();
        }

        $qualifiedIds = JadwalKelas::where('company_id', $jadwalKelas->company_id)
            ->where('mata_pelajaran_id', $jadwalKelas->mata_pelajaran_id)
            ->whereIn('guru_user_id', $guruIds)
            ->pluck('guru_user_id')
            ->unique();

        $busyIds = $this->busyGuruIds($jadwalKelas->company_id, $sesi->tanggal, $jadwalKelas->hari, $jamMulai, $jamSelesai, $jadwalKelas->id);

        $availableIds = $guruIds->diff($busyIds)->values();

        if ($availableIds->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $availableIds)
            ->orderBy('name')
            ->get()
            ->sortByDesc(fn (User $user) => $qualifiedIds->contains($user->id))
            ->values()
            ->map(function (User $user) use ($qualifiedIds) {
                $user->setAttribute('mengajar_mata_pelajaran_sama', $qualifiedIds->contains($user->id));

                return $user;
            });
    }

    /** Reverse of JadwalKelasSesiController::HARI_TO_CARBON_DOW — Carbon::dayOfWeek (0=Minggu..6=Sabtu) keyed back to this app's Indonesian day names. */
    private const CARBON_DOW_TO_HARI = [
        0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
    ];

    /**
     * "Sistem akan menolak jika jadwal guru tersebut sudah ada [bentrok]"
     * — the exact same recurring + per-date-override overlap check
     * busyGuruIds() runs for a whole pool of candidates, just for ONE
     * specific guru at one specific proposed date/time. Used twice per
     * proposal's lifecycle: once up front before even asking the guru
     * (Jadwal\JadwalUsulanController::store()), and again the instant
     * their WA reply arrives (App\Http\Controllers\Api\
     * WaIncomingMessageWebhookController::tryConfirmUsulan()) — the gap
     * between those two moments is exactly where something else could
     * have been booked into the same slot, so "iya" alone is never
     * enough on its own.
     *
     * $excludeJadwalKelasId lets the guru's OWN regular class (the one
     * this makeup session belongs to) be ignored — a makeup date is
     * expected to fall on a different weekday/time than their normal
     * recurring slot for that same class, so it should never
     * self-conflict.
     */
    public function isGuruBusyAt(string $companyId, string $guruUserId, Carbon $tanggal, string $jamMulai, string $jamSelesai, ?string $excludeJadwalKelasId = null): bool
    {
        $hari = self::CARBON_DOW_TO_HARI[(int) $tanggal->dayOfWeek] ?? null;

        $recurringBusy = JadwalKelas::where('company_id', $companyId)
            ->where('guru_user_id', $guruUserId)
            ->where('hari', $hari)
            ->when($excludeJadwalKelasId, fn ($q) => $q->where('id', '!=', $excludeJadwalKelasId))
            ->where(function ($q) use ($jamMulai, $jamSelesai) {
                $q->where('jam_mulai', '<', $jamSelesai)->where('jam_selesai', '>', $jamMulai);
            })
            ->exists();

        if ($recurringBusy) {
            return true;
        }

        return JadwalKelasSesi::where('tanggal', $tanggal->toDateString())
            ->whereNotNull('jam_mulai_override')
            ->where('jam_mulai_override', '<', $jamSelesai)
            ->where('jam_selesai_override', '>', $jamMulai)
            ->whereHas('jadwalKelas', function ($q) use ($companyId, $guruUserId, $excludeJadwalKelasId) {
                $q->where('company_id', $companyId)->where('guru_user_id', $guruUserId);

                if ($excludeJadwalKelasId) {
                    $q->where('id', '!=', $excludeJadwalKelasId);
                }
            })
            ->exists();
    }

    /**
     * Which of $guruIds are unavailable at $tanggal/$jamMulai-$jamSelesai
     * — either via their own recurring JadwalKelas on the same weekday
     * with an overlapping window, or a one-off jam override on that
     * exact date that happens to land in the window.
     */
    private function busyGuruIds(string $companyId, Carbon $tanggal, string $hari, string $jamMulai, string $jamSelesai, string $excludeJadwalKelasId): Collection
    {
        $recurringBusy = JadwalKelas::where('company_id', $companyId)
            ->where('hari', $hari)
            ->where('id', '!=', $excludeJadwalKelasId)
            ->whereNotNull('guru_user_id')
            ->where(function ($q) use ($jamMulai, $jamSelesai) {
                $q->where('jam_mulai', '<', $jamSelesai)
                    ->where('jam_selesai', '>', $jamMulai);
            })
            ->pluck('guru_user_id');

        $overrideBusy = JadwalKelasSesi::where('tanggal', $tanggal->toDateString())
            ->whereHas('jadwalKelas', fn ($q) => $q->where('company_id', $companyId)->where('id', '!=', $excludeJadwalKelasId))
            ->where(function ($q) use ($jamMulai, $jamSelesai) {
                $q->where(function ($q2) use ($jamMulai, $jamSelesai) {
                    $q2->whereNotNull('jam_mulai_override')
                        ->where('jam_mulai_override', '<', $jamSelesai)
                        ->where('jam_selesai_override', '>', $jamMulai);
                });
            })
            ->with('jadwalKelas:id,guru_user_id')
            ->get()
            ->pluck('jadwalKelas.guru_user_id')
            ->filter();

        return $recurringBusy->merge($overrideBusy)->unique();
    }

    /**
     * Every date matching $jadwalKelas->hari over the next $days days
     * (starting tomorrow — offering "today" as a makeup slot for a
     * class that already happened or is about to start isn't useful).
     *
     * @return Collection<int, Carbon>
     */
    private function upcomingDatesFor(JadwalKelas $jadwalKelas, int $days): Collection
    {
        $targetDow = [
            'Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3,
            'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6,
        ][$jadwalKelas->hari] ?? null;

        if ($targetDow === null) {
            return collect();
        }

        $dates = collect();
        $cursor = now()->addDay()->startOfDay();
        $end = now()->addDays($days)->startOfDay();

        while ($cursor->lte($end)) {
            if ((int) $cursor->dayOfWeek === $targetDow) {
                $dates->push($cursor->copy());
            }

            $cursor->addDay();
        }

        return $dates;
    }
}
