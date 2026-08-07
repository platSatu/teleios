<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendAiBotReply;
use App\Jobs\SendAutoReplyMessage;
use App\Models\JadwalKelasSesi;
use App\Models\JadwalKelasSesiMurid;
use App\Models\JadwalUsulanPerubahan;
use App\Models\User;
use App\Models\WaAiBot;
use App\Models\WaMessageAutoReply;
use App\Services\Jadwal\JadwalAvailabilityService;
use App\Services\Jadwal\JadwalMessageTemplateService;
use App\Services\Jadwal\JadwalNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives "a WhatsApp message just arrived" from the Go backend (see
 * g_backend's WaInboxService.notifyIncomingMessageWebhook) and drives
 * "Auto Reply (Kata Kunci)": every active WaMessageAutoReply row for the
 * message's device_id is checked against the message body, and the
 * first one that matches gets its reply_message sent back — see
 * App\Jobs\SendAutoReplyMessage for the actual send.
 *
 * Only the FIRST matching rule fires per incoming message (ordered
 * oldest-created first) rather than every match, so one message can't
 * make the bot fire off a burst of replies if a company configured
 * several overlapping keywords.
 *
 * If no keyword rule matches, this falls back to the device's
 * `is_default` rule (if one's configured) — that's what makes a
 * numbered menu reachable at all: someone who messages with no idea
 * what to type gets the default rule's menu text ("1. Jadwal, 2.
 * Pembayaran, 3. Daftar User, ketik salah satu nomor"), and "1"/"2"/"3"
 * are then just ordinary keyword rules a level down.
 *
 * If THAT also doesn't exist, this falls back one level further to the
 * device's AI Bot config (App\Models\WaAiBot), if one is set up and
 * currently active — see App\Jobs\SendAiBotReply. Keyword rules always
 * take priority over the AI bot: a company that configured both gets
 * predictable, free, instant answers for known keywords, and the AI
 * bot only ever has to handle the open-ended long tail.
 *
 * Also drives Jadwal Kelas's WA-reply auto-confirm (see
 * tryJadwalConfirmation() below): checked FIRST, before keyword rules
 * or the AI bot, and short-circuits the rest of this method when it
 * fires — a jadwal reminder reply is always more specific/contextual
 * than a company's generic auto-reply keywords, so it should never be
 * shadowed by e.g. a keyword rule that also happens to match "ya".
 */
class WaIncomingMessageWebhookController extends Controller
{
    /** Case-insensitive, matched against the trimmed message body. */
    private const CONFIRM_WORDS = ['ya', 'y', 'ok', 'oke', 'okay', 'iya', 'siap', 'hadir', 'setuju', 'konfirmasi'];

    private const DECLINE_WORDS = ['tidak', 'ga', 'gak', 'nggak', 'engga', 'enggak', 'izin', 'absen', 'gabisa', 'tidak bisa', 'ga bisa'];

    public function __construct(
        protected JadwalNotificationService $notifier,
        protected JadwalAvailabilityService $availability,
        protected JadwalMessageTemplateService $templates
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        // Logged unconditionally, before validation even runs, so a
        // request that never makes it past validation (bad payload,
        // wrong field names from a Go-side change, etc.) still leaves a
        // trace — that case previously left zero evidence anywhere.
        Log::info('wa-auto-reply: webhook received', ['payload' => $request->all()]);

        $validated = $request->validate([
            'device_id' => ['required', 'string'],
            'user_id' => ['nullable', 'string'],
            'chat_jid' => ['required', 'string'],
            'message_id' => ['required', 'string'],
            'body' => ['required', 'string'],
            'sent_at' => ['nullable', 'date'],
        ]);

        // Go's own network retry (or the outer HTTP client timing out
        // after the request already succeeded) could deliver the same
        // message_id twice — a short, cheap lock keyed on it makes sure
        // that never sends two replies for one incoming message. TTL
        // only needs to outlive how long a retry could plausibly take.
        $lockKey = 'wa-auto-reply:message:'.$validated['message_id'];

        if (! Cache::add($lockKey, true, now()->addMinutes(10))) {
            Log::info('wa-auto-reply: duplicate message_id, ignored', ['message_id' => $validated['message_id']]);

            return response()->json(['status' => 'duplicate, ignored']);
        }

        // WhatsApp Channels/Newsletters (chat_jid ending in @newsletter)
        // are one-way broadcasts — WhatsApp's own servers reject any
        // attempt to send a normal message back into one (confirmed via
        // production logs: "wa: failed to send message: server returned
        // error 401"). A device subscribed to a channel will still get
        // this webhook fired for every post that lands in it, so this is
        // skipped up front rather than letting a keyword/AI match run
        // all the way to a doomed send attempt every time.
        if (str_ends_with($validated['chat_jid'], '@newsletter')) {
            Log::info('wa-auto-reply: chat_jid is a channel/newsletter, skipped (cannot reply to broadcasts)', [
                'device_id' => $validated['device_id'],
                'chat_jid' => $validated['chat_jid'],
            ]);

            return response()->json(['status' => 'skipped (newsletter/channel)']);
        }

        $jadwalResponse = $this->tryJadwalConfirmation($validated);

        if ($jadwalResponse) {
            return $jadwalResponse;
        }

        $activeRules = WaMessageAutoReply::query()
            ->where('device_id', $validated['device_id'])
            ->where('status', 'active')
            ->oldest()
            ->get();

        Log::info('wa-auto-reply: checking rules for device', [
            'device_id' => $validated['device_id'],
            'body' => $validated['body'],
            'active_rule_count' => $activeRules->count(),
            'active_rule_keywords' => $activeRules->pluck('keyword', 'id'),
        ]);

        $rule = $activeRules->first(fn (WaMessageAutoReply $rule) => $rule->matches($validated['body']));
        $matchedDefault = false;

        if (! $rule) {
            $rule = $activeRules->firstWhere('is_default', true);
            $matchedDefault = (bool) $rule;
        }

        if (! $rule) {
            return $this->tryAiBotFallback($validated);
        }

        Log::info($matchedDefault ? 'wa-auto-reply: no keyword matched, falling back to default rule' : 'wa-auto-reply: rule matched, dispatching reply job', [
            'rule_id' => $rule->id,
            'keyword' => $rule->keyword,
            'is_default' => $matchedDefault,
            'chat_jid' => $validated['chat_jid'],
        ]);

        SendAutoReplyMessage::dispatch($rule->id, $validated['chat_jid']);

        return response()->json([
            'status' => $matchedDefault ? 'matched (default)' : 'matched',
            'rule_id' => $rule->id,
        ]);
    }

    /**
     * Last resort when no keyword rule (and no default rule) matched:
     * hand the incoming message to the device's AI Bot, if one is
     * configured and currently switched on (see
     * App\Models\WaAiBot::isCurrentlyActive). If there's no bot, or it's
     * off, this is exactly the old "no match, do nothing" behaviour.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function tryAiBotFallback(array $validated): JsonResponse
    {
        $bot = WaAiBot::with(['provider', 'model'])
            ->where('device_id', $validated['device_id'])
            ->first();

        if (! $bot || ! $bot->isCurrentlyActive()) {
            Log::info('wa-auto-reply: no rule matched and no active AI bot configured', [
                'device_id' => $validated['device_id'],
                'body' => $validated['body'],
            ]);

            return response()->json(['status' => 'no match']);
        }

        Log::info('wa-auto-reply: no keyword matched, falling back to AI bot', [
            'ai_bot_id' => $bot->id,
            'device_id' => $validated['device_id'],
            'chat_jid' => $validated['chat_jid'],
        ]);

        SendAiBotReply::dispatch($bot->id, $validated['chat_jid'], $validated['body']);

        return response()->json([
            'status' => 'matched (ai bot)',
            'ai_bot_id' => $bot->id,
        ]);
    }

    /**
     * "Kendala yang sering terjadi itu, di WA konfirmasi tapi di Excel
     * tidak terupdate sehingga kelupaan dan bentrok" — this is the fix:
     * a reply that matches an unambiguous ya/tidak-type word AND whose
     * sender has a genuinely pending Jadwal Kelas reminder waiting
     * updates that row directly, no manual step in between.
     *
     * Returns a JsonResponse (short-circuiting the rest of handle())
     * only when it actually resolved something — a message from a
     * number with no pending confirmation, or with an ambiguous body
     * that matches neither CONFIRM_WORDS nor DECLINE_WORDS, falls
     * through to the normal keyword/AI-bot chain untouched (never
     * silently swallowed).
     */
    protected function tryJadwalConfirmation(array $validated): ?JsonResponse
    {
        $digits = preg_replace('/\D+/', '', explode('@', $validated['chat_jid'])[0] ?? '');

        if ($digits === '') {
            return null;
        }

        $user = User::where('handphone', $digits)->first();

        if (! $user) {
            return null;
        }

        $intent = $this->parseConfirmationIntent($validated['body']);

        if ($intent === null) {
            // A pending reminder might still exist for this user, but
            // the reply itself doesn't clearly say yes/no — leave it
            // for the normal auto-reply/AI-bot chain rather than
            // guessing.
            return null;
        }

        $muridResponse = $this->tryConfirmAsMurid($user, $validated['device_id'], $intent);

        if ($muridResponse) {
            return $muridResponse;
        }

        $usulanResponse = $this->tryConfirmUsulan($user, $validated['device_id'], $intent);

        if ($usulanResponse) {
            return $usulanResponse;
        }

        return $this->tryConfirmAsGuru($user, $validated['device_id'], $intent);
    }

    /**
     * 'ya'/'ok'/... -> true (confirm/hadir), 'tidak'/'izin'/... -> false
     * (decline/izin), anything else -> null (ambiguous, don't guess).
     * Checked as a whole-message match (trimmed, case-insensitive) —
     * deliberately NOT a substring match, so a longer unrelated message
     * that merely contains "ok" somewhere doesn't get misread as a
     * confirmation.
     */
    protected function parseConfirmationIntent(string $body): ?bool
    {
        $normalized = mb_strtolower(trim($body));
        $normalized = trim($normalized, ".!? \t\n\r");

        if (in_array($normalized, self::CONFIRM_WORDS, true)) {
            return true;
        }

        if (in_array($normalized, self::DECLINE_WORDS, true)) {
            return false;
        }

        return null;
    }

    /**
     * Earliest still-pending JadwalKelasSesiMurid for this murid on
     * this exact device — reminder already sent, not yet confirmed,
     * session still 'terjadwal'. Locked the same way ProcessDepositExpiry
     * / DuitkuCallbackController lock their own rows, so a duplicate
     * webhook delivery for the same reply can't double-process it.
     */
    protected function tryConfirmAsMurid(User $user, string $deviceId, bool $confirmed): ?JsonResponse
    {
        $pending = JadwalKelasSesiMurid::query()
            ->whereHas('jadwalKelasMurid', function ($q) use ($user, $deviceId) {
                $q->where('murid_user_id', $user->id)
                    ->where('status', 'active')
                    ->whereHas('jadwalKelas', fn ($q2) => $q2->where('device_id', $deviceId));
            })
            ->where('status', 'terjadwal')
            ->whereNotNull('reminder_sent_at')
            ->whereNull('confirmed_at')
            ->with('sesi.jadwalKelas.mataPelajaran')
            ->orderBy('created_at')
            ->first();

        if (! $pending) {
            return null;
        }

        $newStatus = $confirmed ? 'hadir' : 'izin';

        // The write happens INSIDE the same lockForUpdate transaction
        // as the guard check — a duplicate webhook delivery for this
        // same reply that arrives while this is still running blocks on
        // the lock, then (once granted) sees confirmed_at already set
        // and bails via the null check, instead of racing to also pass
        // the check before either commits.
        $confirmedRow = DB::transaction(function () use ($pending, $newStatus) {
            $locked = JadwalKelasSesiMurid::whereKey($pending->id)->lockForUpdate()->first();

            if (! $locked || $locked->confirmed_at !== null || $locked->status !== 'terjadwal') {
                return null;
            }

            $locked->update([
                'status' => $newStatus,
                'confirmed_at' => now(),
                'confirmation_channel' => 'wa_reply',
            ]);

            return $locked;
        });

        if (! $confirmedRow) {
            return null;
        }

        Log::info('wa-jadwal-confirm: murid confirmed via WA reply', [
            'jadwal_kelas_sesi_murid_id' => $pending->id,
            'user_id' => $user->id,
            'status' => $newStatus,
        ]);

        $jadwalKelas = $pending->sesi->jadwalKelas;
        $label = $jadwalKelas->name ?: $jadwalKelas->mataPelajaran?->name;

        $ack = $this->templates->render(
            $jadwalKelas->company_id,
            $confirmed ? 'ack_murid_hadir' : 'ack_murid_izin',
            ['nama_murid' => $user->name, 'label_kelas' => $label]
        );

        try {
            $this->notifier->send($jadwalKelas, $user, $ack);
        } catch (Throwable $e) {
            Log::warning('wa-jadwal-confirm: failed to send murid ack', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'jadwal confirmation (murid)', 'jadwal_kelas_sesi_murid_id' => $pending->id]);
    }

    /**
     * "Murid mengajukan perubahan jadwal, gurunya menjawab iya artinya
     * jadwal terupdate — tapi sistem akan menolak jika jadwal guru
     * tersebut sudah ada [bentrok]." Earliest still-pending
     * JadwalUsulanPerubahan for this guru on this device. On IYA, the
     * conflict check (App\Services\Jadwal\JadwalAvailabilityService::
     * isGuruBusyAt()) runs a SECOND time here — something else could
     * have been booked into that exact slot in the time between
     * Jadwal\JadwalUsulanController::store() asking and this reply
     * arriving — so approval alone never directly writes the schedule
     * without being re-verified first.
     */
    protected function tryConfirmUsulan(User $user, string $deviceId, bool $confirmed): ?JsonResponse
    {
        $pending = JadwalUsulanPerubahan::query()
            ->where('guru_user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('jadwalKelas', fn ($q) => $q->where('device_id', $deviceId))
            ->whereNotNull('reminder_sent_at')
            ->whereNull('responded_at')
            ->with('jadwalKelas.mataPelajaran', 'jadwalKelas.company.user', 'sesiMurid.jadwalKelasMurid', 'murid')
            ->orderBy('created_at')
            ->first();

        if (! $pending) {
            return null;
        }

        // Same "write inside the lock, not after it" idempotency
        // pattern as tryConfirmAsMurid()/tryConfirmAsGuru() — a
        // duplicate webhook delivery for this same reply blocks on the
        // lock, then sees responded_at already set and bails, instead
        // of racing to also pass the guard before either commits.
        $result = DB::transaction(function () use ($pending, $confirmed) {
            $locked = JadwalUsulanPerubahan::whereKey($pending->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'pending' || $locked->responded_at !== null) {
                return null;
            }

            if (! $confirmed) {
                $locked->update(['status' => 'ditolak', 'responded_at' => now()]);

                return ['usulan' => $locked, 'outcome' => 'ditolak'];
            }

            $bentrok = $this->availability->isGuruBusyAt(
                $locked->company_id,
                $locked->guru_user_id,
                Carbon::parse($locked->tanggal_usulan),
                substr((string) $locked->jam_mulai_usulan, 0, 5),
                substr((string) $locked->jam_selesai_usulan, 0, 5),
                $locked->jadwal_kelas_id
            );

            if ($bentrok) {
                $locked->update(['status' => 'bentrok', 'responded_at' => now()]);

                return ['usulan' => $locked, 'outcome' => 'bentrok'];
            }

            // Applied under the same lock as the conflict re-check above
            // — nothing else can slot in between "confirmed clear" and
            // "actually written" for this exact guru/tanggal/jam.
            $sesiMurid = $locked->sesiMurid;
            $jadwalKelasMuridId = $sesiMurid?->jadwal_kelas_murid_id;

            $targetSesi = JadwalKelasSesi::firstOrCreate(
                ['jadwal_kelas_id' => $locked->jadwal_kelas_id, 'tanggal' => $locked->tanggal_usulan->toDateString()],
                ['status' => 'terjadwal', 'jam_mulai_override' => $locked->jam_mulai_usulan, 'jam_selesai_override' => $locked->jam_selesai_usulan]
            );

            if (! $targetSesi->wasRecentlyCreated && ! $targetSesi->jam_mulai_override) {
                $targetSesi->update(['jam_mulai_override' => $locked->jam_mulai_usulan, 'jam_selesai_override' => $locked->jam_selesai_usulan]);
            }

            if ($jadwalKelasMuridId) {
                JadwalKelasSesiMurid::firstOrCreate(
                    ['jadwal_kelas_sesi_id' => $targetSesi->id, 'jadwal_kelas_murid_id' => $jadwalKelasMuridId],
                    ['status' => 'terjadwal']
                );

                if ($sesiMurid) {
                    $sesiMurid->update([
                        'status' => 'pindah_hari',
                        'tanggal_pindah' => $locked->tanggal_usulan->toDateString(),
                        'pindah_ke_sesi_id' => $targetSesi->id,
                        'confirmed_at' => now(),
                        'confirmation_channel' => 'wa_reply',
                    ]);
                }
            }

            $locked->update(['status' => 'disetujui', 'responded_at' => now()]);

            return ['usulan' => $locked, 'outcome' => 'disetujui', 'target_sesi_id' => $targetSesi->id];
        });

        if (! $result) {
            return null;
        }

        Log::info('wa-jadwal-confirm: usulan perubahan direspons via WA', [
            'jadwal_usulan_perubahan_id' => $pending->id,
            'guru_user_id' => $user->id,
            'outcome' => $result['outcome'],
        ]);

        $this->notifyUsulanOutcome($pending, $user, $result['outcome']);

        return response()->json(['status' => 'jadwal usulan '.$result['outcome'], 'jadwal_usulan_perubahan_id' => $pending->id]);
    }

    /**
     * @param  array{usulan: JadwalUsulanPerubahan, outcome: string}  $result
     */
    protected function notifyUsulanOutcome(JadwalUsulanPerubahan $pending, User $guru, string $outcome): void
    {
        $jadwalKelas = $pending->jadwalKelas;
        $label = $jadwalKelas->name ?: $jadwalKelas->mataPelajaran?->name;
        $tanggal = $pending->tanggal_usulan->translatedFormat('l, d M Y');
        $jam = substr((string) $pending->jam_mulai_usulan, 0, 5).'-'.substr((string) $pending->jam_selesai_usulan, 0, 5);
        $owner = $jadwalKelas->company?->user;

        try {
            if ($outcome === 'disetujui') {
                $this->notifier->send($jadwalKelas, $guru, "Terima kasih {$guru->name}, kelas pengganti *{$label}* pada {$tanggal} jam {$jam} sudah dikonfirmasi.");

                if ($pending->murid) {
                    $this->notifier->send($jadwalKelas, $pending->murid, "Kabar baik! Kelas pengganti *{$label}* Anda dikonfirmasi guru pada {$tanggal} jam {$jam}.");
                }
            } elseif ($outcome === 'bentrok') {
                $this->notifier->send($jadwalKelas, $guru, "Maaf {$guru->name}, ternyata jadwal Anda sudah terisi di {$tanggal} jam {$jam} — usulan ini otomatis dibatalkan. Mohon ajukan waktu lain.");

                if ($owner) {
                    $this->notifier->send($jadwalKelas, $owner, "Usulan jadwal pengganti *{$label}* pada {$tanggal} jam {$jam} otomatis ditolak sistem (guru {$guru->name} bentrok jadwal lain). Mohon carikan waktu lain.");
                }
            } else {
                $this->notifier->send($jadwalKelas, $guru, "Baik {$guru->name}, sudah dicatat Anda tidak bisa mengajar *{$label}* pada {$tanggal} jam {$jam}.");

                if ($owner) {
                    $this->notifier->send($jadwalKelas, $owner, "Guru {$guru->name} tidak bisa memenuhi usulan jadwal pengganti *{$label}* pada {$tanggal} jam {$jam}. Mohon carikan waktu lain.");
                }
            }
        } catch (Throwable $e) {
            Log::warning('wa-jadwal-confirm: failed to notify usulan outcome', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Same idea as tryConfirmAsMurid(), for the assigned guru's own
     * pending JadwalKelasSesi. A DECLINE_WORDS reply here is
     * deliberately NOT auto-applied as a class cancellation — that's a
     * higher-consequence action best left to an admin via the
     * dashboard's manual override (see Jadwal\JadwalKelasSesiController::
     * updateStatus()) — this only records that the guru said they
     * can't make it (catatan) and pings the company owner to follow up.
     */
    protected function tryConfirmAsGuru(User $user, string $deviceId, bool $confirmed): ?JsonResponse
    {
        $pending = JadwalKelasSesi::query()
            ->whereHas('jadwalKelas', function ($q) use ($user, $deviceId) {
                $q->where('guru_user_id', $user->id)->where('device_id', $deviceId);
            })
            ->where('status', 'terjadwal')
            ->whereNotNull('guru_reminder_sent_at')
            ->whereNull('guru_confirmed_at')
            ->with('jadwalKelas.mataPelajaran', 'jadwalKelas.company.user')
            ->orderBy('tanggal')
            ->first();

        if (! $pending) {
            return null;
        }

        // Same "write inside the lock, not after it" fix as
        // tryConfirmAsMurid() above — see that method's comment.
        $confirmedRow = DB::transaction(function () use ($pending, $confirmed) {
            $locked = JadwalKelasSesi::whereKey($pending->id)->lockForUpdate()->first();

            if (! $locked || $locked->guru_confirmed_at !== null || $locked->status !== 'terjadwal') {
                return null;
            }

            if ($confirmed) {
                $locked->update(['guru_confirmed_at' => now()]);
            } else {
                // Not auto-cancelled — just recorded + owner pinged
                // outside this transaction, see this method's docblock.
                $locked->update([
                    'catatan' => trim(($locked->catatan ? $locked->catatan."\n" : '')."Guru melaporkan tidak bisa hadir via WA pada ".now()->format('d M Y H:i').'.'),
                ]);
            }

            return $locked;
        });

        if (! $confirmedRow) {
            return null;
        }

        $jadwalKelas = $pending->jadwalKelas;
        $label = $jadwalKelas->name ?: $jadwalKelas->mataPelajaran?->name;

        if ($confirmed) {
            Log::info('wa-jadwal-confirm: guru confirmed via WA reply', [
                'jadwal_kelas_sesi_id' => $pending->id,
                'user_id' => $user->id,
            ]);

            $ack = $this->templates->render($jadwalKelas->company_id, 'ack_guru_confirm', ['nama_guru' => $user->name, 'label_kelas' => $label]);
        } else {
            Log::info('wa-jadwal-confirm: guru declined via WA reply', [
                'jadwal_kelas_sesi_id' => $pending->id,
                'user_id' => $user->id,
            ]);

            $ack = $this->templates->render($jadwalKelas->company_id, 'ack_guru_decline', ['nama_guru' => $user->name, 'label_kelas' => $label]);

            $owner = $jadwalKelas->company?->user;

            if ($owner && $owner->id !== $user->id) {
                try {
                    $this->notifier->send(
                        $jadwalKelas,
                        $owner,
                        "Perhatian: guru {$user->name} melaporkan TIDAK BISA mengajar *{$label}* pada ".\Illuminate\Support\Carbon::parse($pending->tanggal)->translatedFormat('l, d M Y').'. Mohon dicek dan dicarikan pengganti/jadwal ulang.'
                    );
                } catch (Throwable $e) {
                    Log::warning('wa-jadwal-confirm: failed to notify owner of guru decline', ['error' => $e->getMessage()]);
                }
            }
        }

        try {
            $this->notifier->send($jadwalKelas, $user, $ack);
        } catch (Throwable $e) {
            Log::warning('wa-jadwal-confirm: failed to send guru ack', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'jadwal confirmation (guru)', 'jadwal_kelas_sesi_id' => $pending->id]);
    }
}
