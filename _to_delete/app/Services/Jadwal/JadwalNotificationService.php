<?php

namespace App\Services\Jadwal;

use App\Jobs\Concerns\NormalizesWhatsAppJid;
use App\Models\JadwalKelas;
use App\Models\User;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one plain-text WhatsApp notification tied to a JadwalKelas —
 * used by both the reminder command (App\Console\Commands\
 * ProcessJadwalKelasReminders) and the create/update triggers in
 * Jadwal\JadwalKelasController. Deliberately synchronous+swallowed
 * (returns bool, never throws) rather than a queued Job: every call
 * site here already runs inside a request/command that's already
 * doing several of these in a loop (one per guru/murid), so one Job
 * per recipient would be a lot of queue churn for what's a single fast
 * HTTP call to the Go backend — same tradeoff DepositController makes
 * for its own single-recipient sends, vs. SendScheduledWaMessage which
 * IS a queued Job because it's dispatched per (schedule, recipient) at
 * a specific future time.
 *
 * Mirrors App\Jobs\SendScheduledWaMessage's device/JWT/JID pattern
 * exactly: the device is whichever one was explicitly picked on the
 * JadwalKelas itself (device_id), and the JWT is minted for the
 * company's owner (the same user who ultimately owns/authorized that
 * device) — never the recipient, who has no Golang session of their
 * own here.
 */
class JadwalNotificationService
{
    use NormalizesWhatsAppJid;

    public function __construct(
        protected SystemJwtService $jwtService,
        protected InboxService $inbox,
    ) {
    }

    /**
     * @return bool true if the message was actually sent.
     */
    public function send(JadwalKelas $jadwalKelas, ?User $recipient, string $message): bool
    {
        if (! $recipient) {
            return false;
        }

        if (! $jadwalKelas->device_id) {
            Log::info('jadwal-notification: skipped, no device configured on jadwal_kelas', [
                'jadwal_kelas_id' => $jadwalKelas->id,
            ]);

            return false;
        }

        if (! $recipient->handphone) {
            Log::info('jadwal-notification: skipped, recipient has no handphone', [
                'jadwal_kelas_id' => $jadwalKelas->id,
                'user_id' => $recipient->id,
            ]);

            return false;
        }

        $jadwalKelas->loadMissing('company.user');
        $owner = $jadwalKelas->company?->user;

        if (! $owner) {
            Log::warning('jadwal-notification: skipped, jadwal_kelas has no company owner to sign the request', [
                'jadwal_kelas_id' => $jadwalKelas->id,
            ]);

            return false;
        }

        $jid = $this->toIndividualJid($recipient->handphone);

        if (! $jid) {
            return false;
        }

        try {
            $token = $this->jwtService->mintFor($owner);
            $this->inbox->send($token, $jadwalKelas->device_id, $jid, $message);

            return true;
        } catch (Throwable $e) {
            Log::warning('jadwal-notification: send failed', [
                'jadwal_kelas_id' => $jadwalKelas->id,
                'user_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
