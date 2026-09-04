<?php

namespace App\Services\Jadwal;

use App\Models\Company;
use App\Models\JadwalChangeLog;
use App\Models\JadwalKelas;
use App\Models\JadwalReminderSetting;
use App\Models\JadwalRutin;
use App\Models\User;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\PackageLimitService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dipanggil dari App\Http\Controllers\Jadwal\JadwalStudentController
 * waktu jadwal murid (App\Models\JadwalRutin) berubah lewat checklist
 * ketersediaan Pengajar di form Tambah/Edit Student — dua hal yang
 * SEBELUMNYA tidak pernah terjadi sama sekali dari jalur itu (laporan
 * user 4 September 2026, "jadwal student bisa ke-add 2x/3x .. wa nya
 * kadang terkirim kadang tidak"):
 *
 * 1. Sesi (App\Models\JadwalKelas) yang SUDAH ter-generate dari
 *    JadwalRutin yang baru saja dihapus, tapi jamnya MASIH DI DEPAN dan
 *    BELUM diabsen sama sekali (`attendance_status` masih kosong) --
 *    "sesi hantu" ini SEBELUMNYA dibiarkan begitu saja dengan
 *    `status = active`, jadi tetap masuk antrian
 *    `jadwal:dispatch-due-reminders` dan tetap dikirimi pengingat WA
 *    padahal jadwalnya sudah tidak berlaku. rutinRemoved() di bawah
 *    men-nonaktifkan (`status = inactive`) baris-baris itu SEBELUM
 *    JadwalRutin-nya sendiri dihapus caller -- baris JadwalKelas-nya
 *    TETAP ADA (bukan dihapus, aman untuk laporan fee historis), cuma
 *    tidak lagi dianggap "aktif" jadi otomatis hilang dari antrian
 *    pengingat & dari tampilan jadwal aktif. Sesi yang SUDAH LEWAT atau
 *    SUDAH DIABSEN tidak disentuh sama sekali -- sengaja, supaya
 *    histori kehadiran/fee yang sudah tercatat tidak diam-diam berubah.
 * 2. Notifikasi WA ke Pengajar soal jadwalnya berubah -- SEBELUMNYA
 *    cuma jalan lewat popup Edit Jadwal Kelas langsung
 *    (JadwalKelasController::update()) atau approve/reject Reschedule
 *    Request, TIDAK PERNAH jalan lewat form Student. rutinRemoved()/
 *    rutinAdded() di bawah mengirim WA yang setara (guard/pengaturan
 *    SAMA PERSIS: App\Models\JadwalReminderSetting::
 *    reschedule_notify_pengajar + device_id + paket kategori Chat aktif)
 *    supaya Pengajar SELALU dapat kabar dari jalur MANAPUN jadwalnya
 *    berubah, bukan cuma satu jalur.
 *
 * Juga menulis App\Models\JadwalChangeLog ("jadwal sebelum diganti" /
 * "jadwal sesudah diganti", permintaan user) dari KEDUA jalur --
 * JadwalKelasController::update() memanggil logKelasEdited() secara
 * terpisah (lihat pemanggilannya di sana) karena bentuk before/after-nya
 * beda (satu sesi diedit langsung, bukan sepasang JadwalRutin
 * dihapus+dibuat).
 *
 * Semua method di sini best-effort -- gagal kirim WA atau gagal tulis
 * log TIDAK PERNAH melempar balik ke caller (di-log sebagai warning),
 * sama prinsipnya dengan App\Http\Controllers\Jadwal\
 * JadwalKelasController::notifyPengajarScheduleChanged() yang sudah ada
 * sebelumnya -- perubahan jadwal murid itu sendiri tidak boleh gagal
 * cuma karena WA gagal terkirim.
 *
 * Update 4 September 2026 (laporan user: "ketika ada perubahan jadwal
 * wa nya terkirim 1x saja ya tidak tiap ada perubahan ya"): SEBELUMNYA
 * rutinRemoved()/rutinAdded() SELALU langsung kirim WA begitu dipanggil
 * -- kalau satu form Edit Student meng-uncheck 3 slot lama & mencentang
 * 2 slot baru sekaligus, Pengajar yang sama bisa dapat SAMPAI 5 WA
 * terpisah dalam satu kali submit (App\Http\Controllers\Jadwal\
 * JadwalStudentController::update() memanggil kedua method ini di
 * DALAM loop, satu kali per baris App\Models\JadwalRutin yang berubah;
 * deactivate() juga sama, satu kali per baris rutin aktif murid itu).
 * Kedua method sekarang terima parameter `$batch` (default false, BUKAN
 * breaking change ke pemanggil lama) -- true berarti pesannya DITAMPUNG
 * dulu (queueForPengajar()) alih-alih langsung dikirim, dikelompokkan
 * per Pengajar (bukan per baris JadwalRutin). Caller WAJIB memanggil
 * flushPengajarNotifications() SATU KALI setelah SEMUA rutinRemoved()/
 * rutinAdded(batch: true) untuk request itu selesai dipanggil -- lihat
 * docblock method itu untuk detail penting soal instance service ini
 * HARUS sama (constructor-injected) sepanjang satu request, bukan
 * di-resolve ulang lewat app() tiap panggilan (service ini BUKAN
 * singleton, resolve ulang bikin antreannya "hilang"/tidak nyambung
 * antara rutinRemoved()/rutinAdded() dengan flush-nya).
 *
 * JadwalKelasController::update() (popup Edit Jadwal Kelas) TIDAK
 * terpengaruh perubahan ini -- jalur itu SUDAH SEJAK AWAL cuma kirim
 * maksimal 1 WA per submit (satu App\Models\JadwalKelas diedit sekali
 * jalan, bukan multi-baris JadwalRutin seperti form Student), lewat
 * notifyPengajarScheduleChanged() di sana sendiri, tidak lewat
 * rutinRemoved()/rutinAdded() di sini sama sekali.
 */
class JadwalScheduleChangeNotifier
{
    /**
     * Antrean pesan WA per Pengajar, dikumpulkan lewat queueForPengajar()
     * waktu rutinRemoved()/rutinAdded() dipanggil dengan $batch=true --
     * lihat docblock class & flushPengajarNotifications(). Key: gabungan
     * "company_id|pengajar_id" (murni internal, tidak ada makna lain).
     *
     * @var array<string, array{company_id: string, pengajar_id: string, lines: list<string>}>
     */
    private array $pendingByPengajar = [];

    public function __construct(
        private readonly PackageLimitService $packageLimits,
        private readonly SystemJwtService $jwtService,
        private readonly InboxService $inbox,
    ) {
    }

    /**
     * Panggil SEBELUM $rutin benar-benar dihapus (masih butuh
     * pengajar_id/hari/jam dsb-nya) -- lihat docblock class untuk apa
     * yang dilakukan (nonaktifkan sesi masa depan yang belum diabsen,
     * tulis log, kirim WA ke pengajar LAMA).
     *
     * $batch=true (lihat docblock class) -- pesan DITAMPUNG lewat
     * queueForPengajar(), TIDAK langsung dikirim, caller wajib panggil
     * flushPengajarNotifications() sendiri belakangan. Log
     * (App\Models\JadwalChangeLog) & nonaktifkan sesi masa depan TETAP
     * jalan seperti biasa (per baris, TIDAK ikut di-batch) -- yang
     * di-batch cuma pengiriman WA-nya.
     */
    public function rutinRemoved(JadwalRutin $rutin, ?string $changedBy, bool $batch = false): void
    {
        $this->deactivateFutureSessions($rutin);

        $before = $this->snapshotRutin($rutin);

        $this->writeLog(
            companyId: $rutin->company_id,
            branchOfficeId: $rutin->branch_office_id,
            studentId: $rutin->student_id,
            jadwalKelasId: null,
            source: JadwalChangeLog::SOURCE_STUDENT_FORM,
            before: $before,
            after: null,
            changedBy: $changedBy,
        );

        $studentName = $rutin->student?->name ?? '-';

        $message = sprintf(
            "Jadwal mengajar Anda dengan %s (%s) pada %s %s-%s sudah TIDAK BERLAKU LAGI (diubah oleh admin).",
            $studentName,
            $before['kategori_name'] ?? '-',
            $before['hari_label'],
            $before['jam_mulai'],
            $before['jam_selesai'],
        );

        if ($batch) {
            $this->queueForPengajar($rutin->company_id, $rutin->pengajar_id, $message);

            return;
        }

        $this->sendToPengajar($rutin->company_id, $rutin->pengajar_id, $message);
    }

    /**
     * Panggil SETELAH $rutin baru dibuat (butuh id-nya untuk relasi,
     * meski tidak dipakai langsung di sini) -- kirim WA ke pengajar
     * BARU + tulis log 'after'-only. Lihat docblock class.
     *
     * $batch=true -- lihat docblock rutinRemoved() di atas, perilakunya
     * sama persis di sini (cuma pengiriman WA yang ditampung, log tetap
     * per baris seperti biasa).
     */
    public function rutinAdded(JadwalRutin $rutin, ?string $changedBy, bool $batch = false): void
    {
        $after = $this->snapshotRutin($rutin);

        $this->writeLog(
            companyId: $rutin->company_id,
            branchOfficeId: $rutin->branch_office_id,
            studentId: $rutin->student_id,
            jadwalKelasId: null,
            source: JadwalChangeLog::SOURCE_STUDENT_FORM,
            before: null,
            after: $after,
            changedBy: $changedBy,
        );

        $studentName = $rutin->student?->name ?? '-';

        $message = sprintf(
            "Anda mendapat jadwal mengajar BARU dengan %s (%s) pada %s %s-%s.",
            $studentName,
            $after['kategori_name'] ?? '-',
            $after['hari_label'],
            $after['jam_mulai'],
            $after['jam_selesai'],
        );

        if ($batch) {
            $this->queueForPengajar($rutin->company_id, $rutin->pengajar_id, $message);

            return;
        }

        $this->sendToPengajar($rutin->company_id, $rutin->pengajar_id, $message);
    }

    /**
     * Kirim SEMUA notifikasi WA yang ditampung queueForPengajar() lewat
     * rutinRemoved()/rutinAdded() dengan $batch=true -- DIGABUNG jadi
     * SATU pesan per Pengajar (bukan satu pesan per baris JadwalRutin
     * yang berubah), lihat docblock class untuk alasan lengkapnya.
     * Kalau cuma ADA SATU baris perubahan untuk Pengajar itu, pesannya
     * dikirim APA ADANYA (sama persis dengan pesan non-batch sebelumnya)
     * -- header ringkasan ("Jadwal mengajar Anda diperbarui...") CUMA
     * muncul kalau memang lebih dari satu perubahan ditampung untuk
     * Pengajar yang sama.
     *
     * WAJIB dipanggil caller SATU KALI setelah SEMUA pemanggilan
     * rutinRemoved()/rutinAdded(batch: true) untuk request itu selesai
     * -- lihat pemakaiannya di App\Http\Controllers\Jadwal\
     * JadwalStudentController::update()/deactivate(). Instance service
     * ini HARUS SAMA (constructor-injected) sepanjang satu request --
     * resolve ulang lewat app() di tengah jalan bikin antrean yang
     * ditampung method-method di atas "hilang" (service ini bukan
     * singleton). Antrean dikosongkan setelah dikirim -- aman dipanggil
     * lebih dari sekali per request kalau perlu, panggilan berikutnya
     * tidak mengirim apa pun kalau tidak ada antrean baru.
     */
    public function flushPengajarNotifications(): void
    {
        foreach ($this->pendingByPengajar as $entry) {
            $message = count($entry['lines']) === 1
                ? $entry['lines'][0]
                : "Jadwal mengajar Anda diperbarui oleh admin:\n- ".implode("\n- ", $entry['lines']);

            $this->sendToPengajar($entry['company_id'], $entry['pengajar_id'], $message);
        }

        $this->pendingByPengajar = [];
    }

    /**
     * Tampung satu baris pesan untuk Pengajar tertentu -- lihat
     * flushPengajarNotifications(). Diam-diam tidak menampung apa pun
     * kalau companyId/pengajarId kosong (sama seperti guard awal
     * sendToPengajar()) supaya tidak ada entri "sampah" di antrean yang
     * dijamin gagal kirim nanti.
     */
    private function queueForPengajar(?string $companyId, ?string $pengajarId, string $line): void
    {
        if (! $companyId || ! $pengajarId) {
            return;
        }

        $key = $companyId.'|'.$pengajarId;

        $this->pendingByPengajar[$key]['company_id'] = $companyId;
        $this->pendingByPengajar[$key]['pengajar_id'] = $pengajarId;
        $this->pendingByPengajar[$key]['lines'][] = $line;
    }

    /**
     * Dipanggil App\Http\Controllers\Jadwal\JadwalKelasController::
     * update() SETELAH baris $kelas di-update() DAN WA-nya sendiri
     * sudah dikirim lewat notifyPengajarScheduleChanged() yang sudah
     * ada di sana (TIDAK diubah/di-duplikasi di sini) -- method ini
     * CUMA menulis App\Models\JadwalChangeLog-nya (permintaan user soal
     * histori before/after), supaya jalur edit langsung ini juga
     * tercatat di tabel yang sama dengan jalur form Student.
     */
    public function logKelasEdited(
        JadwalKelas $kelas,
        ?\Carbon\Carbon $oldStartTime,
        ?\Carbon\Carbon $oldEndTime,
        ?string $oldPengajarId,
        ?string $changedBy,
    ): void {
        $oldPengajarName = $oldPengajarId ? User::find($oldPengajarId)?->name : null;

        $this->writeLog(
            companyId: $kelas->company_id,
            branchOfficeId: $kelas->branch_office_id,
            studentId: $kelas->student_id,
            jadwalKelasId: $kelas->id,
            source: JadwalChangeLog::SOURCE_JADWAL_KELAS_EDIT,
            before: [
                'pengajar_id' => $oldPengajarId,
                'pengajar_name' => $oldPengajarName,
                'start_time' => $oldStartTime?->toDateTimeString(),
                'end_time' => $oldEndTime?->toDateTimeString(),
            ],
            after: [
                'pengajar_id' => $kelas->pengajar_id,
                'pengajar_name' => $kelas->pengajar?->name,
                'start_time' => $kelas->start_time?->toDateTimeString(),
                'end_time' => $kelas->end_time?->toDateTimeString(),
            ],
            changedBy: $changedBy,
        );
    }

    /**
     * "Sesi hantu" -- lihat docblock class poin 1. Dibatasi ke sesi yang
     * jamnya MASIH DI DEPAN (`start_time > now()`) DAN belum diabsen
     * (`attendance_status` masih kosong); sesi yang sudah lewat atau
     * sudah diabsen TIDAK disentuh sama sekali (histori/fee tetap utuh).
     */
    private function deactivateFutureSessions(JadwalRutin $rutin): void
    {
        JadwalKelas::where('jadwal_rutin_id', $rutin->id)
            ->where('status', JadwalKelas::STATUS_ACTIVE)
            ->where('start_time', '>', now())
            ->whereNull('attendance_status')
            ->update(['status' => JadwalKelas::STATUS_INACTIVE]);
    }

    /**
     * @return array{pengajar_id: ?string, pengajar_name: ?string, jadwal_kategori_id: ?string, kategori_name: ?string, jadwal_ruangan_id: ?string, ruangan_name: ?string, hari: int, hari_label: string, jam_mulai: string, jam_selesai: string, durasi_menit: ?int}
     */
    private function snapshotRutin(JadwalRutin $rutin): array
    {
        return [
            'pengajar_id' => $rutin->pengajar_id,
            'pengajar_name' => $rutin->pengajar?->name,
            'jadwal_kategori_id' => $rutin->jadwal_kategori_id,
            'kategori_name' => $rutin->kategori?->name,
            'jadwal_ruangan_id' => $rutin->jadwal_ruangan_id,
            'ruangan_name' => $rutin->ruangan?->name,
            'hari' => $rutin->hari,
            'hari_label' => $rutin->hariLabel(),
            'jam_mulai' => substr($rutin->jam_mulai, 0, 5),
            'jam_selesai' => $rutin->jamSelesai(),
            'durasi_menit' => $rutin->durasi_menit,
        ];
    }

    private function writeLog(
        string $companyId,
        ?string $branchOfficeId,
        ?string $studentId,
        ?string $jadwalKelasId,
        string $source,
        ?array $before,
        ?array $after,
        ?string $changedBy,
    ): void {
        try {
            JadwalChangeLog::create([
                'company_id' => $companyId,
                'branch_office_id' => $branchOfficeId,
                'student_id' => $studentId,
                'jadwal_kelas_id' => $jadwalKelasId,
                'source' => $source,
                'before' => $before,
                'after' => $after,
                'changed_by' => $changedBy,
            ]);
        } catch (Throwable $e) {
            Log::warning('JadwalScheduleChangeNotifier: gagal menulis JadwalChangeLog', [
                'company_id' => $companyId,
                'student_id' => $studentId,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Guard + kirim WA -- pola PERSIS sama dengan
     * JadwalKelasController::notifyPengajarScheduleChanged() (paket
     * kategori Chat aktif, JadwalReminderSetting::reschedule_notify_
     * pengajar + device_id, nomor pengajar ada, owner company ada).
     * Best-effort, tidak pernah melempar balik ke caller.
     */
    private function sendToPengajar(?string $companyId, ?string $pengajarId, string $message): void
    {
        if (! $companyId || ! $pengajarId) {
            return;
        }

        $company = Company::find($companyId);

        if (! $company || ! $this->packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES)) {
            return;
        }

        $setting = JadwalReminderSetting::where('company_id', $companyId)->first();

        if (! $setting || ! $setting->device_id || ! $setting->reschedule_notify_pengajar) {
            return;
        }

        $pengajar = User::find($pengajarId);
        $owner = $company->user;

        if (! $pengajar || ! $pengajar->handphone || ! $owner) {
            return;
        }

        try {
            $token = $this->jwtService->mintFor($owner);
            $jid = PhoneNumber::normalize($pengajar->handphone).'@s.whatsapp.net';
            $this->inbox->send($token, $setting->device_id, $jid, $message);
        } catch (Throwable $e) {
            Log::warning('JadwalScheduleChangeNotifier: gagal mengirim notifikasi WA ke pengajar', [
                'pengajar_id' => $pengajarId,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
