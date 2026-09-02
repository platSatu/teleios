<?php

namespace App\Services\Jadwal;

use App\Models\JadwalKelas;
use App\Models\User;
use App\Models\WaMessageTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menyusun rekap jadwal mengajar untuk SATU pengajar -- dipakai
 * bersama oleh 3 jalur reminder pengajar (Jadwal v2, CLAUDE.md item #15
 * spec poin 9 & 10): App\Console\Commands\
 * DispatchJadwalPengajarDailyReminders (H-1 otomatis harian),
 * App\Http\Controllers\Jadwal\JadwalKelasController::sendPengajarReminder()
 * (manual by admin), dan App\Http\Controllers\Api\
 * WaIncomingMessageWebhookController's penanganan kata kunci request
 * (rekap minggu ini). SATU tempat supaya format pesannya konsisten di
 * ketiga jalur, dan supaya template WA (jadwal_reminder_settings.
 * wa_message_template_id_pengajar) berlaku sama untuk semuanya --
 * tidak ada teks hardcode terpisah per jalur.
 */
class JadwalPengajarRecapService
{
    /**
     * @return Collection<int, JadwalKelas>
     */
    public function sesiForRange(string $companyId, string $pengajarId, Carbon $from, Carbon $to): Collection
    {
        return JadwalKelas::where('company_id', $companyId)
            ->where('pengajar_id', $pengajarId)
            ->where('status', JadwalKelas::STATUS_ACTIVE)
            ->whereBetween('start_time', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['mataPelajaran:id,name', 'student:id,name', 'ruangan:id,name'])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Pesan rekap SATU HARI (dipakai H-1 otomatis & manual admin) --
     * daftar sesi diurutkan jam, format "HH:MM-HH:MM MataPelajaran -
     * Murid (Ruangan)".
     */
    public function composeDailyMessage(User $pengajar, Collection $sesi, Carbon $date, string $companyName, ?WaMessageTemplate $template): string
    {
        $body = $this->templateBodyOrDefault($template, $this->defaultDailyMessage());

        return strtr($body, [
            '{{nama_pengajar}}' => $pengajar->name,
            '{{tanggal}}' => $date->translatedFormat('d F Y'),
            // Admin sets up ONE template text shared by both the daily
            // (H-1/manual) and weekly (WA keyword) recap -- Pengaturan
            // Pengingat advertises all 6 tags together for that single
            // field (jadwal/settings/edit.blade.php), so whichever tag
            // doesn't natively apply to this call path still needs a
            // real value here, or it leaks into the WhatsApp message as
            // literal "{{rentang_tanggal}}" text. A daily recap has no
            // real "range" -- fall back to the same single date.
            '{{rentang_tanggal}}' => $date->translatedFormat('d F Y'),
            '{{jumlah_sesi}}' => (string) $sesi->count(),
            '{{daftar_sesi}}' => $this->formatSesiList($sesi),
            '{{nama_perusahaan}}' => $companyName,
        ]);
    }

    /** Pesan rekap SATU MINGGU (dipakai request manual by WA, dikelompokkan per hari). */
    public function composeWeeklyMessage(User $pengajar, Collection $sesi, Carbon $from, Carbon $to, string $companyName, ?WaMessageTemplate $template): string
    {
        $body = $this->templateBodyOrDefault($template, $this->defaultWeeklyMessage());

        $rentangTanggal = $from->translatedFormat('d M').' - '.$to->translatedFormat('d M Y');

        return strtr($body, [
            '{{nama_pengajar}}' => $pengajar->name,
            // Same reasoning as composeDailyMessage()'s new
            // {{rentang_tanggal}} fallback, mirrored the other way: a
            // weekly recap has no single "tanggal", so a template that
            // uses that tag here falls back to the range instead of
            // leaking "{{tanggal}}" verbatim into the message.
            '{{tanggal}}' => $rentangTanggal,
            '{{rentang_tanggal}}' => $rentangTanggal,
            '{{jumlah_sesi}}' => (string) $sesi->count(),
            '{{daftar_sesi}}' => $this->formatSesiListGroupedByDay($sesi),
            '{{nama_perusahaan}}' => $companyName,
        ]);
    }

    private function templateBodyOrDefault(?WaMessageTemplate $template, string $default): string
    {
        if ($template && $template->status === 'active' && $template->review_status === 'approved') {
            return $template->composedMessage();
        }

        return $default;
    }

    private function formatSesiList(Collection $sesi): string
    {
        if ($sesi->isEmpty()) {
            return '(tidak ada jadwal)';
        }

        return $sesi->map(function (JadwalKelas $kelas) {
            $jam = $kelas->start_time?->format('H:i').'-'.$kelas->end_time?->format('H:i');
            $label = $kelas->mataPelajaran?->name ?? '-';
            $murid = $kelas->student?->name ?? '-';
            $ruangan = $kelas->ruangan?->name;

            return "- {$jam} {$label} - {$murid}".($ruangan ? " ({$ruangan})" : '');
        })->implode("\n");
    }

    private function formatSesiListGroupedByDay(Collection $sesi): string
    {
        if ($sesi->isEmpty()) {
            return '(tidak ada jadwal)';
        }

        return $sesi->groupBy(fn (JadwalKelas $k) => $k->start_time?->toDateString())
            ->map(function (Collection $group, string $date) {
                $header = Carbon::parse($date)->translatedFormat('l, d F Y');
                $lines = $this->formatSesiList($group);

                return "{$header}\n{$lines}";
            })
            ->implode("\n\n");
    }

    private function defaultDailyMessage(): string
    {
        return "Halo {{nama_pengajar}}, berikut jadwal mengajar Anda pada {{tanggal}} ({{jumlah_sesi}} sesi):\n"
            ."{{daftar_sesi}}\n\n"
            .'- {{nama_perusahaan}}';
    }

    private function defaultWeeklyMessage(): string
    {
        return "Halo {{nama_pengajar}}, berikut jadwal mengajar Anda minggu ini ({{rentang_tanggal}}):\n\n"
            ."{{daftar_sesi}}\n\n"
            .'- {{nama_perusahaan}}';
    }
}
