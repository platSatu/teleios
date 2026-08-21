<?php

namespace App\Console\Commands;

use App\Models\JadwalKelasSesi;
use App\Models\JadwalKelasSesiMurid;
use App\Services\Jadwal\JadwalMessageTemplateService;
use App\Services\Jadwal\JadwalNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs once a day (see bootstrap/app.php's ->withSchedule()), the
 * evening before — reminds every guru and murid with a `terjadwal`
 * JadwalKelasSesi(Murid) happening TOMORROW to expect their class, and
 * asks them to reply to confirm. That reply is what
 * App\Http\Controllers\Api\WaIncomingMessageWebhookController's new
 * jadwal-confirmation check picks up and turns straight into a DB
 * update (JadwalKelasSesi::guru_confirmed_at / JadwalKelasSesiMurid::
 * confirmed_at) — closing the exact gap described as the recurring
 * field problem: "di WA konfirmasi tapi di Excel tidak terupdate
 * sehingga kelupaan dan bentrok".
 *
 * Idempotent per (sesi, guru) and per (sesi, murid) via
 * guru_reminder_sent_at / reminder_sent_at — once stamped, that
 * specific reminder is never re-sent even if this command runs again
 * the same day.
 *
 * Guru and murid are reminded independently of each other (two
 * separate loops below) since a class can have a guru assigned but no
 * murid yet, or vice versa, and either side replying only ever
 * confirms their OWN row.
 */
class ProcessJadwalKelasReminders extends Command
{
    protected $signature = 'jadwal:process-reminders';

    protected $description = 'Send H-1 WhatsApp reminders for tomorrow\'s jadwal_kelas_sesi, to both guru and murid';

    public function __construct(
        protected JadwalNotificationService $notifier,
        protected JadwalMessageTemplateService $templates
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $guruReminded = $this->remindGuru($tomorrow);
        $muridReminded = $this->remindMurid($tomorrow);

        $this->info("Reminder guru terkirim: {$guruReminded}. Reminder murid terkirim: {$muridReminded}.");

        return self::SUCCESS;
    }

    private function remindGuru(string $tanggal): int
    {
        $sesiList = JadwalKelasSesi::where('status', 'terjadwal')
            ->where('tanggal', $tanggal)
            ->whereNull('guru_reminder_sent_at')
            ->with(['jadwalKelas.guru', 'jadwalKelas.mataPelajaran'])
            ->get()
            ->filter(fn ($sesi) => $sesi->jadwalKelas && $sesi->jadwalKelas->guru_user_id);

        $sent = 0;

        foreach ($sesiList as $sesi) {
            try {
                DB::transaction(function () use ($sesi, &$sent) {
                    $locked = JadwalKelasSesi::whereKey($sesi->id)->lockForUpdate()->first();

                    if (! $locked || $locked->guru_reminder_sent_at !== null || $locked->status !== 'terjadwal') {
                        return;
                    }

                    $locked->forceFill(['guru_reminder_sent_at' => now()])->save();

                    $jadwalKelas = $sesi->jadwalKelas;
                    $label = $jadwalKelas->name ?: $jadwalKelas->mataPelajaran?->name;
                    $jamStr = substr((string) $jadwalKelas->jam_mulai, 0, 5).'-'.substr((string) $jadwalKelas->jam_selesai, 0, 5);
                    $tanggalStr = Carbon::parse($sesi->tanggal)->translatedFormat('l, d M Y');

                    $message = $this->templates->render($jadwalKelas->company_id, 'reminder_guru', [
                        'nama_guru' => $jadwalKelas->guru->name,
                        'label_kelas' => $label,
                        'tanggal' => $tanggalStr,
                        'jam' => $jamStr,
                    ]);

                    $ok = $this->notifier->send($jadwalKelas, $jadwalKelas->guru, $message);

                    if ($ok) {
                        $sent++;
                    }
                });
            } catch (Throwable $e) {
                Log::error('jadwal:process-reminders: failed to remind guru', [
                    'sesi_id' => $sesi->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    private function remindMurid(string $tanggal): int
    {
        $sesiMuridList = JadwalKelasSesiMurid::whereHas('sesi', function ($q) use ($tanggal) {
                $q->where('status', 'terjadwal')->where('tanggal', $tanggal);
            })
            ->where('status', 'terjadwal')
            ->whereNull('reminder_sent_at')
            ->with(['sesi.jadwalKelas.mataPelajaran', 'jadwalKelasMurid.murid'])
            ->get()
            ->filter(fn ($sm) => $sm->jadwalKelasMurid?->status === 'active' && $sm->jadwalKelasMurid?->murid);

        $sent = 0;

        foreach ($sesiMuridList as $sesiMurid) {
            try {
                DB::transaction(function () use ($sesiMurid, &$sent) {
                    $locked = JadwalKelasSesiMurid::whereKey($sesiMurid->id)->lockForUpdate()->first();

                    if (! $locked || $locked->reminder_sent_at !== null || $locked->status !== 'terjadwal') {
                        return;
                    }

                    $locked->forceFill(['reminder_sent_at' => now()])->save();

                    $sesi = $sesiMurid->sesi;
                    $jadwalKelas = $sesi->jadwalKelas;
                    $murid = $sesiMurid->jadwalKelasMurid->murid;
                    $label = $jadwalKelas->name ?: $jadwalKelas->mataPelajaran?->name;
                    $jamStr = substr((string) $jadwalKelas->jam_mulai, 0, 5).'-'.substr((string) $jadwalKelas->jam_selesai, 0, 5);
                    $tanggalStr = Carbon::parse($sesi->tanggal)->translatedFormat('l, d M Y');

                    $message = $this->templates->render($jadwalKelas->company_id, 'reminder_murid', [
                        'nama_murid' => $murid->name,
                        'label_kelas' => $label,
                        'tanggal' => $tanggalStr,
                        'jam' => $jamStr,
                    ]);

                    $ok = $this->notifier->send($jadwalKelas, $murid, $message);

                    if ($ok) {
                        $sent++;
                    }
                });
            } catch (Throwable $e) {
                Log::error('jadwal:process-reminders: failed to remind murid', [
                    'sesi_murid_id' => $sesiMurid->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }
}
