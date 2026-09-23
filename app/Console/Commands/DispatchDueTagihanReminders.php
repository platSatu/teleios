<?php

namespace App\Console\Commands;

use App\Jobs\SendTagihanReminder;
use App\Models\Tagihan;
use App\Models\TagihanPenerima;
use App\Models\TagihanReminderLog;
use App\Models\TagihanReminderRule;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Tagihan-only equivalent of App\Console\Commands\
 * DispatchDueJadwalReminders -- pola & struktur disamakan persis
 * (klaim race-safe lewat App\Models\TagihanReminderLog, App\Jobs\
 * SendTagihanReminder yang benar-benar mengirim). Berjalan terpisah
 * total dari App\Console\Commands\ProcessTagihanExpiry (beda tabel,
 * beda tujuan -- itu MENGUBAH status invoice yang jendela Duitku-nya
 * lewat, ini cuma MENGINGATKAN sebelum jatuh tempo).
 *
 * 23 September 2026 (audit kesiapan launch): sebelumnya
 * App\Models\TagihanReminderRule/TagihanReminderLog sengaja belum
 * disambungkan ke WhatsApp ("jangan sambungkan dulu ya dengan
 * whatsapp" -- lihat migration create_tagihan_reminder_rule_table.php's
 * docblock), instruksi itu sudah dicabut user, jadi command ini yang
 * melengkapinya.
 *
 * "Due" di sini artinya App\Models\Tagihan::due_date sebuah rule sudah
 * masuk jendela (App\Models\TagihanReminderRule::minutesBefore()) tapi
 * belum lewat -- BEDA dari Jadwal Kelas yang setting-nya per company
 * (App\Models\JadwalReminderSetting), rule Tagihan disimpan langsung
 * per Tagihan individual (App\Models\Tagihan::reminderRules(), lihat
 * migration create_tagihan_reminder_rule_table.php's docblock: "scope
 * per TAGIHAN individual, bukan per category"). Karena itu loop-nya
 * lewat TagihanReminderRule langsung, bukan lewat setting company dulu
 * seperti Jadwal.
 */
class DispatchDueTagihanReminders extends Command
{
    protected $signature = 'tagihan:dispatch-due-reminders';

    protected $description = 'Enqueue due Tagihan (invoice) reminders (WA) berdasarkan tagihan_reminder_rule tiap Tagihan';

    public function handle(): int
    {
        $now = now();
        $count = 0;

        TagihanReminderRule::query()
            ->with('tagihan')
            ->chunkById(100, function ($rules) use ($now, &$count) {
                foreach ($rules as $rule) {
                    $tagihan = $rule->tagihan;

                    // Tagihan sudah dihapus/dibatalkan, atau belum (lagi)
                    // punya due_date -- tidak ada yang bisa diingatkan.
                    if (! $tagihan || $tagihan->status !== Tagihan::STATUS_ACTIVE || ! $tagihan->due_date) {
                        continue;
                    }

                    $dueDate = $tagihan->due_date;
                    $dueBefore = $now->copy()->addMinutes($rule->minutesBefore());

                    // Belum masuk jendela (due_date masih terlalu jauh),
                    // atau sudah lewat (due_date <= now, biarkan
                    // ProcessTagihanExpiry yang urus invoice yang sudah
                    // lewat, bukan tugas pengingat ini lagi) -- sama
                    // logika "start_time > now && <= dueBefore" milik
                    // DispatchDueJadwalReminders, cuma pembandingnya
                    // due_date, bukan start_time.
                    if (! ($dueDate->gt($now) && $dueDate->lte($dueBefore))) {
                        continue;
                    }

                    $due = TagihanPenerima::query()
                        ->where('tagihan_id', $tagihan->id)
                        ->where('status', TagihanPenerima::STATUS_BELUM_BAYAR)
                        ->whereDoesntHave('reminderLogs', function ($q) use ($rule) {
                            $q->where('tagihan_reminder_rule_id', $rule->id);
                        })
                        ->get(['id', 'company_id']);

                    foreach ($due as $penerima) {
                        $count += $this->claimAndDispatch($penerima->id, $penerima->company_id, $rule->id);
                    }
                }
            });

        $this->info("Dispatched {$count} due Tagihan reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Race-safe claim -- pola identik App\Console\Commands\
     * DispatchDueJadwalReminders::claimAndDispatch() (lihat docblock-nya
     * untuk penjelasan lengkap kenapa lockForUpdate() + catch
     * QueryException, bukan sekadar firstOrCreate()).
     */
    private function claimAndDispatch(string $tagihanPenerimaId, string $companyId, string $reminderRuleId): int
    {
        $claimed = DB::transaction(function () use ($tagihanPenerimaId, $companyId, $reminderRuleId) {
            $find = fn () => TagihanReminderLog::where('tagihan_penerima_id', $tagihanPenerimaId)
                ->where('tagihan_reminder_rule_id', $reminderRuleId)
                ->lockForUpdate()
                ->first();

            if ($find()) {
                return false;
            }

            try {
                TagihanReminderLog::create([
                    'tagihan_penerima_id' => $tagihanPenerimaId,
                    'tagihan_reminder_rule_id' => $reminderRuleId,
                    'company_id' => $companyId,
                    'status' => TagihanReminderLog::STATUS_PENDING,
                ]);

                return true;
            } catch (QueryException $e) {
                if ($find()) {
                    return false;
                }

                throw $e;
            }
        });

        if (! $claimed) {
            return 0;
        }

        SendTagihanReminder::dispatch($tagihanPenerimaId, $reminderRuleId);

        return 1;
    }
}
