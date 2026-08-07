<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\JadwalKelasSesiMurid;
use App\Models\JadwalUsulanPerubahan;
use App\Services\Jadwal\JadwalAvailabilityService;
use App\Services\Jadwal\JadwalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * "Murid mengajukan perubahan jadwal, gurunya menjawab iya artinya
 * jadwal terupdate — tapi sistem akan menolak jika jadwal guru tersebut
 * sudah ada [bentrok]." Unlike Jadwal\JadwalKelasSesiController::
 * requestPindah() (which only offers slots inside an ALREADY EXISTING
 * class — no one to ask, that guru already committed to that slot),
 * this is for a genuinely custom, ad-hoc makeup date/time with the
 * SAME guru, which only becomes real once they approve it over WA —
 * see App\Http\Controllers\Api\WaIncomingMessageWebhookController::
 * tryConfirmUsulan() for the approval half.
 */
class JadwalUsulanController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(
        protected JadwalNotificationService $notifier,
        protected JadwalAvailabilityService $availability
    ) {
    }

    public function store(Request $request, string $sesiMuridId): RedirectResponse
    {
        $context = $this->companyContext($request);

        $sesiMurid = JadwalKelasSesiMurid::with('sesi.jadwalKelas.guru', 'jadwalKelasMurid.murid')->findOrFail($sesiMuridId);
        $jadwalKelas = $sesiMurid->sesi->jadwalKelas ?? null;

        abort_unless($jadwalKelas && $jadwalKelas->company_id === $context->company->id, 403);

        if ($context->isLockedToBranch() && $jadwalKelas->branch_office_id !== $context->branchOffice?->id) {
            abort(403);
        }

        if (! $jadwalKelas->guru_user_id) {
            return redirect()
                ->route('jadwal.jadwal-kelas.sesi-murid.alternatif', $sesiMurid->id)
                ->with('error', 'Kelas ini belum punya guru — tidak ada yang bisa diajukan konfirmasinya.');
        }

        $validator = Validator::make($request->all(), [
            'tanggal_usulan' => ['required', 'date', 'after_or_equal:today'],
            'jam_mulai_usulan' => ['required', 'date_format:H:i'],
            'jam_selesai_usulan' => ['required', 'date_format:H:i', 'after:jam_mulai_usulan'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.jadwal-kelas.sesi-murid.alternatif', $sesiMurid->id)
                ->withErrors($validator);
        }

        $validated = $validator->validated();
        $tanggal = Carbon::parse($validated['tanggal_usulan']);

        // Checked up front too — no point sending a WA question to the
        // guru asking about a time they're already visibly busy at.
        // Re-checked again the instant they actually reply (see
        // WaIncomingMessageWebhookController::tryConfirmUsulan()), since
        // something else could get booked into this exact slot in the
        // gap between asking and answering.
        if ($this->availability->isGuruBusyAt($jadwalKelas->company_id, $jadwalKelas->guru_user_id, $tanggal, $validated['jam_mulai_usulan'], $validated['jam_selesai_usulan'], $jadwalKelas->id)) {
            return redirect()
                ->route('jadwal.jadwal-kelas.sesi-murid.alternatif', $sesiMurid->id)
                ->with('error', 'Guru sudah punya jadwal lain di waktu tersebut. Silakan pilih tanggal/jam lain.');
        }

        $usulan = JadwalUsulanPerubahan::create([
            'company_id' => $jadwalKelas->company_id,
            'jadwal_kelas_id' => $jadwalKelas->id,
            'jadwal_kelas_sesi_murid_id' => $sesiMurid->id,
            'guru_user_id' => $jadwalKelas->guru_user_id,
            'murid_user_id' => $sesiMurid->jadwalKelasMurid->murid_user_id ?? null,
            'tanggal_usulan' => $tanggal->toDateString(),
            'jam_mulai_usulan' => $validated['jam_mulai_usulan'],
            'jam_selesai_usulan' => $validated['jam_selesai_usulan'],
            'catatan' => $validated['catatan'] ?? null,
            'reminder_sent_at' => now(),
        ]);

        $this->notifyGuruUsulan($usulan);

        return redirect()
            ->route('jadwal.jadwal-kelas.show', $jadwalKelas->id)
            ->with('success', 'Usulan waktu pengganti sudah dikirim ke guru via WA, menunggu konfirmasi.');
    }

    private function notifyGuruUsulan(JadwalUsulanPerubahan $usulan): void
    {
        $usulan->loadMissing('jadwalKelas.mataPelajaran', 'guru', 'murid');

        $label = $usulan->jadwalKelas->name ?: $usulan->jadwalKelas->mataPelajaran?->name;
        $tanggal = $usulan->tanggal_usulan->translatedFormat('l, d M Y');
        $jam = substr((string) $usulan->jam_mulai_usulan, 0, 5).'-'.substr((string) $usulan->jam_selesai_usulan, 0, 5);
        $muridName = $usulan->murid->name ?? 'murid';

        $this->notifier->send(
            $usulan->jadwalKelas,
            $usulan->guru,
            "Halo {$usulan->guru->name}, murid {$muridName} mengajukan kelas pengganti *{$label}* pada {$tanggal} jam {$jam}. Apakah Anda bisa mengajar di waktu tersebut? Balas *IYA* atau *TIDAK*."
        );
    }
}
