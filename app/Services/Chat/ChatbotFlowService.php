<?php

namespace App\Services\Chat;

use App\Models\Company;
use App\Models\JadwalKelas;
use App\Models\JadwalKelasRescheduleRequest;
use App\Models\JadwalStudent;
use App\Models\WaChatbotFlow;
use App\Models\WaChatbotFlowStep;
use App\Models\WaChatbotState;
use App\Models\WaChatLabelAssignment;
use App\Models\WaConversation;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Executes App\Models\WaChatbotFlow trees — Fitur #6's engine. Called
 * from App\Http\Controllers\Api\WaIncomingMessageWebhookController for
 * every incoming message, BEFORE the ordinary keyword auto-reply chain
 * (see that controller's docblock for the full precedence order): a
 * customer either continues a flow session they're already inside
 * (App\Models\WaChatbotState), or their message triggers a brand new one.
 * Either way, this is deliberately synchronous (unlike the actual WhatsApp
 * send, which is queued via App\Jobs\SendChatbotFlowMessages) — the
 * webhook needs to know RIGHT NOW whether a customer's reply belongs to a
 * flow, so it can either short-circuit or fall through to the keyword
 * chain accordingly.
 *
 * The four step types (see WaChatbotFlowStep::TYPE_*) are walked in
 * walk() below: 'action' steps execute immediately and chain straight
 * into whatever comes next with no waiting, while 'message'/'choice'
 * steps stop and wait for the customer's next reply. This is what lets
 * one flow definition mix pure automation ("tag this chat, assign it to
 * Budi") with actual back-and-forth conversation in any order a company
 * wants. A few actions (currently just create_jadwal_reschedule_request)
 * can instead abort the whole session with their own message -- see
 * executeAction()'s return value.
 *
 * A 'choice' step's options are normally static, written by the admin
 * in the flow builder (WaChatbotFlowStep::$options) -- but a step can
 * opt into DYNAMIC options instead (WaChatbotFlowStep::options_source),
 * generated at reply-time from the sending customer's own Jadwal data
 * (see resolveOptions()). Both kinds flow through the exact same
 * render/match code below; only where the option list comes from
 * differs.
 */
class ChatbotFlowService
{
    /**
     * Safety cap on how many steps a single call to walk() will chain
     * through automatically (i.e. consecutive 'action' steps with no
     * waiting in between). A company misconfiguring a flow with a
     * circular action-only chain must never turn one incoming webhook
     * request into an infinite loop — this is far more hops than any
     * legitimate flow (a handful of automated steps in a row) needs.
     */
    private const MAX_AUTO_ADVANCE_STEPS = 25;

    public function __construct(
        protected ConversationService $conversations,
        protected AutoReplyTagResolver $tagResolver,
    ) {
    }

    /**
     * Entry point for the webhook. Returns null when this message has
     * nothing to do with any flow (no active session, no trigger
     * matched) — the caller should fall through to the ordinary
     * keyword/AI-bot chain in that case, exactly as if this service
     * didn't exist at all.
     *
     * $senderPhone is the sender's real phone number as resolved by Go
     * (webhook's `sender_phone` field) — passed through to start() and
     * stored on the new WaChatbotState so senderPhone() below doesn't
     * have to guess it from $chatJid, which is NOT reliably a phone
     * number (WhatsApp addresses some chats via an opaque "...@lid" id
     * with no digits in it at all — see App\Http\Controllers\Api     * WaIncomingMessageWebhookController's `sender_phone` validation
     * rule docblock for the production case that taught this). Optional
     * & nullable throughout for backward compatibility with an older Go
     * build that hasn't been redeployed with this field yet.
     *
     * @return array{messages: array<int, string>, ended: bool}|null
     */
    public function handleIncoming(string $deviceId, string $chatJid, string $body, ?string $senderPhone = null): ?array
    {
        $state = $this->activeState($deviceId, $chatJid);

        if ($state) {
            return $this->exitIfRequested($state, $body) ?? $this->continueFlow($state, $body);
        }

        $flow = $this->findTriggeredFlow($deviceId, $body);

        if (! $flow) {
            return null;
        }

        return $this->start($flow, $deviceId, $chatJid, $senderPhone);
    }

    /**
     * The customer's currently in-progress session for this chat, if any
     * — self-expiring: a session whose flow was deleted/deactivated, or
     * that's been idle longer than its flow's session_timeout_minutes, is
     * cleaned up here rather than left to accumulate forever, and treated
     * as "no active session" either way.
     */
    public function activeState(string $deviceId, string $chatJid): ?WaChatbotState
    {
        $state = WaChatbotState::where('device_id', $deviceId)->where('chat_jid', $chatJid)->first();

        if (! $state) {
            return null;
        }

        $flow = $state->flow;

        if (! $flow || $flow->status !== WaChatbotFlow::STATUS_ACTIVE) {
            $state->delete();

            return null;
        }

        $timeoutMinutes = $flow->session_timeout_minutes ?: WaChatbotFlow::DEFAULT_SESSION_TIMEOUT_MINUTES;

        if ($state->last_interaction_at && $state->last_interaction_at->lt(now()->subMinutes($timeoutMinutes))) {
            Log::info('chatbot-flow: session timed out, cleared', [
                'flow_id' => $flow->id,
                'device_id' => $deviceId,
                'chat_jid' => $chatJid,
            ]);

            $state->delete();

            return null;
        }

        return $state;
    }

    /**
     * Kata kunci keluar paksa OPSIONAL milik flow yang sedang aktif --
     * lihat App\Models\WaChatbotFlow::matchesExit(). Dicek SEBELUM
     * pesan diproses sebagai jawaban step biasa, supaya customer yang
     * macet (mis. berkali-kali salah jawab pilihan) punya jalan keluar
     * pasti, tanpa perlu menunggu timeout atau staff turun tangan
     * manual lewat database. Null-safe by design: flow tanpa
     * exit_keyword (default semua flow yang sudah ada) selalu balik
     * null di sini, jadi handleIncoming() lanjut ke continueFlow()
     * seperti biasa -- tidak ada perubahan perilaku untuk flow yang
     * belum di-opt-in.
     *
     * @return array{messages: array<int, string>, ended: bool}|null
     */
    private function exitIfRequested(WaChatbotState $state, string $body): ?array
    {
        $flow = $state->flow;

        if (! $flow || ! $flow->matchesExit($body)) {
            return null;
        }

        $state->delete();

        Log::info('chatbot-flow: sesi diakhiri paksa via exit_keyword', [
            'flow_id' => $flow->id,
            'device_id' => $state->device_id,
            'chat_jid' => $state->chat_jid,
        ]);

        return ['messages' => ['Baik, sesi dibatalkan. Ketik ulang kata kunci untuk memulai lagi.'], 'ended' => true];
    }

    /**
     * The first active flow (oldest-created first, same convention as
     * WaMessageAutoReply's rule ordering) whose trigger_keyword matches
     * this message, if any.
     */
    public function findTriggeredFlow(string $deviceId, string $body): ?WaChatbotFlow
    {
        return WaChatbotFlow::where('device_id', $deviceId)
            ->where('status', WaChatbotFlow::STATUS_ACTIVE)
            ->oldest()
            ->get()
            ->first(fn (WaChatbotFlow $flow) => $flow->matchesTrigger($body));
    }

    /**
     * @return array{messages: array<int, string>, ended: bool}
     */
    public function start(WaChatbotFlow $flow, string $deviceId, string $chatJid, ?string $senderPhone = null): array
    {
        $startStep = $flow->steps()->where('is_start', true)->first();

        if (! $startStep) {
            Log::warning('chatbot-flow: flow has no start step configured, cannot start', ['flow_id' => $flow->id]);

            return ['messages' => [], 'ended' => true];
        }

        // Same create-race guard as App\Services\Chat\ConversationService
        // ::recordInbound() — two near-simultaneous webhook deliveries
        // for this same chat (e.g. a customer double-sending the trigger
        // keyword) could otherwise both pass findTriggeredFlow() before
        // either has written its wa_chatbot_states row, racing
        // updateOrCreate() against the table's own unique(device_id,
        // chat_jid) constraint.
        $lockKey = "chatbot-flow:start-lock:{$deviceId}:{$chatJid}";
        $normalizedPhone = $senderPhone ? PhoneNumber::normalize($senderPhone) : null;

        return Cache::lock($lockKey, 10)->block(5, function () use ($flow, $deviceId, $chatJid, $startStep, $normalizedPhone) {
            return DB::transaction(function () use ($flow, $deviceId, $chatJid, $startStep, $normalizedPhone) {
                $state = WaChatbotState::updateOrCreate(
                    ['device_id' => $deviceId, 'chat_jid' => $chatJid],
                    [
                        'sender_phone' => $normalizedPhone ?: null,
                        'wa_chatbot_flow_id' => $flow->id,
                        'current_step_id' => $startStep->id,
                        'variables' => [],
                        'started_at' => now(),
                        'last_interaction_at' => now(),
                    ]
                );
                $state->setRelation('flow', $flow);

                Log::info('chatbot-flow: session started', ['flow_id' => $flow->id, 'device_id' => $deviceId, 'chat_jid' => $chatJid]);

                return $this->walk($state, $startStep);
            });
        });
    }

    /**
     * Evaluates the customer's reply against whatever step they were
     * waiting at, records it, and advances — see walk() for what happens
     * next. A 'choice' step whose reply matches none of its options
     * REPROMPTS the same step (with a short "not recognized" prefix)
     * rather than advancing on a guess or silently dropping the message.
     *
     * @return array{messages: array<int, string>, ended: bool}
     */
    public function continueFlow(WaChatbotState $state, string $body): array
    {
        return DB::transaction(function () use ($state, $body) {
            $locked = WaChatbotState::whereKey($state->id)->lockForUpdate()->first();

            if (! $locked) {
                return ['messages' => [], 'ended' => true];
            }

            $currentStep = $locked->currentStep;

            if (! $currentStep) {
                $locked->delete();

                return ['messages' => [], 'ended' => true];
            }

            $variables = $locked->variables ?? [];
            $nextStepId = $currentStep->default_next_step_id;

            if ($currentStep->step_type === WaChatbotFlowStep::TYPE_CHOICE) {
                $options = $this->resolveOptions($currentStep, $locked);

                // Opsi dinamis (lihat resolveOptions()) yang datanya sudah
                // habis di tengah sesi -- mis. jadwal yang mau dijawab
                // sudah dihapus/diubah di antara render & balasan ini --
                // tidak boleh dibiarkan reprompt selamanya karena tidak
                // akan pernah ada angka yang cocok. Sama seperti di walk(),
                // langsung akhiri sesi dengan pesan yang jelas.
                if ($currentStep->options_source && $options === []) {
                    $locked->delete();

                    return ['messages' => [$this->emptyDynamicOptionsMessage($currentStep)], 'ended' => true];
                }

                $matched = $this->matchChoiceOption($options, $body);

                if ($matched === null) {
                    // Sengaja TIDAK meng-update last_interaction_at di sini --
                    // jawaban yang tidak dikenali bukan progress, jadi tidak
                    // boleh memperpanjang timeout sesi (lihat activeState()'s
                    // docblock). Tanpa ini, customer yang terus-terusan salah
                    // jawab bisa membuat sesinya sendiri tidak pernah timeout,
                    // padahal itu justru tanda dia sedang macet, bukan sedang
                    // aktif menjawab -- timer cuma boleh mundur lagi kalau ada
                    // progress beneran (lihat walk() di bawah).
                    $reprompt = trim(
                        'Maaf, pilihan tidak dikenali. '
                        .$this->renderStepMessage($currentStep, $currentStep->flow?->company)
                        ."\n".$this->renderChoiceOptions($options)
                    );

                    return ['messages' => [$reprompt], 'ended' => false];
                }

                $variables[$currentStep->id] = $matched['label'] ?? ($matched['value'] ?? null);

                // Nilai mentah opsi yang dipilih (mis. jadwal_kelas_id, atau
                // rentang waktu slot kosong) -- dibaca lagi lewat
                // findChosenValue() oleh step 'choice' dinamis berikutnya
                // dan createJadwalRescheduleRequest(). Key terpisah
                // (bukan menimpa $variables[$currentStep->id]) supaya
                // buildTranscript() -- yang cuma baca key persis
                // $s->id -- tidak ikut menampilkan value mentah ini ke
                // transkrip yang dibaca staff.
                $variables[$currentStep->id.'_value'] = $matched['value'] ?? null;

                $nextStepId = $matched['next_step_id'] ?? $currentStep->default_next_step_id;
            } else {
                // 'message' step — free-text reply, stored as-is.
                $variables[$currentStep->id] = trim($body);
            }

            $locked->variables = $variables;
            $locked->save();

            $nextStep = $nextStepId ? WaChatbotFlowStep::find($nextStepId) : null;

            return $this->walk($locked, $nextStep);
        });
    }

    /**
     * Walks forward from $step, executing every 'action' step it passes
     * through immediately (with no waiting) and stopping at the first
     * 'message'/'choice' step (which needs a reply) or 'end' step (which
     * terminates the session). Every step's own `message` (if any) is
     * collected along the way — a chain of several action steps in a row
     * can therefore produce several messages to send, in order.
     *
     * @return array{messages: array<int, string>, ended: bool}
     */
    private function walk(WaChatbotState $state, ?WaChatbotFlowStep $step): array
    {
        $flow = $step?->flow ?? $state->flow;
        $company = $flow?->company;
        $messages = [];
        $hops = 0;

        while ($step !== null) {
            if (++$hops > self::MAX_AUTO_ADVANCE_STEPS) {
                Log::warning('chatbot-flow: step chain exceeded safety limit, session ended', [
                    'flow_id' => $flow?->id,
                    'state_id' => $state->id,
                ]);
                $step = null;
                break;
            }

            // 'choice' steps: the step's own body text and its numbered
            // options list are combined into ONE WhatsApp message (joined
            // with "\n", then trimmed) instead of two separate messages —
            // matches the reprompt path in continueFlow() above, which
            // already combines them the same way. Handled before the
            // generic renderStepMessage() push below so the body text
            // isn't sent twice.
            if ($step->step_type === WaChatbotFlowStep::TYPE_CHOICE) {
                $options = $this->resolveOptions($step, $state);

                // Step 'choice' dengan opsi dinamis (lihat resolveOptions())
                // yang ternyata tidak ada apa-apa untuk ditampilkan -- mis.
                // nomor pengirim tidak terdaftar sebagai murid manapun, atau
                // muridnya tidak punya jadwal akan datang. Menunggu balasan
                // di sini percuma karena tidak akan pernah ada angka yang
                // cocok dengan daftar kosong, jadi langsung akhiri sesi
                // dengan pesan yang jelas alih-alih membiarkan customer
                // menjawab ke pertanyaan yang tidak punya pilihan.
                if ($step->options_source && $options === []) {
                    $messages[] = $this->emptyDynamicOptionsMessage($step);
                    $state->delete();

                    return ['messages' => $messages, 'ended' => true];
                }

                $combined = trim(
                    $this->renderStepMessage($step, $company)
                    ."\n".$this->renderChoiceOptions($options)
                );

                if ($combined !== '') {
                    $messages[] = $combined;
                }

                $state->current_step_id = $step->id;
                $state->last_interaction_at = now();
                $state->save();

                return ['messages' => $messages, 'ended' => false];
            }

            $rendered = $this->renderStepMessage($step, $company);
            if ($rendered !== '') {
                $messages[] = $rendered;
            }

            if ($step->step_type === WaChatbotFlowStep::TYPE_END) {
                $step = null;
                break;
            }

            if ($step->step_type === WaChatbotFlowStep::TYPE_ACTION) {
                $abortMessage = $this->executeAction($state, $step);

                // Sebagian aksi (mis. create_jadwal_reschedule_request saat
                // nomor pengirim tidak terdaftar) perlu menolak & mengakhiri
                // sesi dengan pesan sendiri, bukan diam-diam lanjut seperti
                // aksi otomatis lain (tugaskan percakapan, tambah label,
                // dst). executeAction() balik string non-null hanya untuk
                // kasus itu -- setiap aksi lain tetap balik null seperti
                // sebelumnya, jadi ini tidak mengubah perilaku aksi manapun
                // yang sudah ada.
                if ($abortMessage !== null) {
                    $messages[] = $abortMessage;
                    $state->delete();

                    return ['messages' => $messages, 'ended' => true];
                }

                if ($step->action === WaChatbotFlowStep::ACTION_HANDOFF_HUMAN) {
                    $step = null; // handing off to a human always ends the bot session
                    break;
                }

                $step = $step->default_next_step_id ? WaChatbotFlowStep::find($step->default_next_step_id) : null;
                continue;
            }

            // 'message' — this step waits for a reply.
            $state->current_step_id = $step->id;
            $state->last_interaction_at = now();
            $state->save();

            return ['messages' => $messages, 'ended' => false];
        }

        // Ran off the end of the chain — nothing left to wait for.
        $state->delete();

        return ['messages' => $messages, 'ended' => true];
    }

    private function renderStepMessage(WaChatbotFlowStep $step, ?Company $company): string
    {
        if (! $step->message) {
            return '';
        }

        return $company ? $this->tagResolver->resolve($step->message, $company) : $step->message;
    }

    /**
     * Numbered list built from a 'choice' step's options (array shape:
     * list<array{label: string, value?: ?string, next_step_id?: ?string}>,
     * either $step->options as-is or generated by resolveOptions()),
     * matching what matchChoiceOption() below accepts as a reply
     * ("1", "2", ...).
     */
    private function renderChoiceOptions(array $options): string
    {
        if (empty($options)) {
            return '';
        }

        return collect($options)->values()
            ->map(fn ($opt, int $i) => ($i + 1).'. '.($opt['label'] ?? ''))
            ->implode("\n");
    }

    /**
     * Matches a reply against a 'choice' step's options (same shape as
     * renderChoiceOptions() above) either by number (the primary
     * expected way, matching renderChoiceOptions()'s numbered list) or
     * by the option's own label typed out verbatim (case-insensitive) —
     * some customers reply with the word instead of the number. Returns
     * null when neither matches.
     *
     * @return array{label?: string, value?: string, next_step_id?: ?string}|null
     */
    private function matchChoiceOption(array $options, string $body): ?array
    {
        $options = collect($options)->values();
        $normalized = mb_strtolower(trim($body));

        if (ctype_digit($normalized)) {
            $index = ((int) $normalized) - 1;
            if ($options->has($index)) {
                return $options->get($index);
            }
        }

        return $options->first(
            fn ($opt) => mb_strtolower(trim($opt['label'] ?? '')) === $normalized
        );
    }

    /**
     * Daftar opsi untuk step 'choice' -- statis (kolom `options`, ditulis
     * admin di flow builder, default) atau DINAMIS (diambil dari data
     * Jadwal murid yang sedang chat, lihat App\Models\WaChatbotFlowStep::
     * OPTIONS_SOURCE_*) kalau step-nya diset `options_source`. Sengaja
     * dipisah dari renderChoiceOptions()/matchChoiceOption() di atas
     * supaya keduanya tidak perlu tahu asal opsinya statis atau dinamis
     * -- mereka cuma menerima array opsi jadi apa adanya.
     *
     * @return list<array{label: string, value?: ?string, next_step_id?: ?string}>
     */
    private function resolveOptions(WaChatbotFlowStep $step, WaChatbotState $state): array
    {
        return match ($step->options_source) {
            WaChatbotFlowStep::OPTIONS_SOURCE_MY_JADWAL => $this->myJadwalOptions($state),
            WaChatbotFlowStep::OPTIONS_SOURCE_OPEN_SLOTS_SAME_PENGAJAR => $this->openSlotOptions($step, $state),
            default => collect($step->options ?? [])->values()->all(),
        };
    }

    /**
     * Pesan saat step 'choice' dinamis tidak punya apa-apa untuk
     * ditampilkan -- lihat walk()/continueFlow()'s pemakaiannya. Per
     * sumber, supaya customer tahu kenapa (nomor belum terdaftar, vs.
     * memang tidak ada jam kosong) alih-alih pesan generik yang
     * membingungkan.
     */
    private function emptyDynamicOptionsMessage(WaChatbotFlowStep $step): string
    {
        return match ($step->options_source) {
            WaChatbotFlowStep::OPTIONS_SOURCE_MY_JADWAL => 'Maaf, kami tidak menemukan data jadwal untuk nomor Anda. Pastikan nomor ini terdaftar sebagai orang tua/murid, atau hubungi admin kami.',
            WaChatbotFlowStep::OPTIONS_SOURCE_OPEN_SLOTS_SAME_PENGAJAR => 'Maaf, tidak ada jam kosong yang bisa kami tawarkan dalam waktu dekat. Silakan hubungi admin kami secara langsung.',
            default => 'Maaf, tidak ada pilihan yang bisa ditampilkan saat ini.',
        };
    }

    /**
     * Opsi dinamis untuk WaChatbotFlowStep::OPTIONS_SOURCE_MY_JADWAL --
     * jadwal kelas milik murid yang cocok dengan nomor HP pengirim,
     * maksimal 5 yang akan datang, terdekat dulu. `value` diisi
     * jadwal_kelas_id-nya -- dibaca lagi lewat findChosenValue() oleh
     * openSlotOptions() & createJadwalRescheduleRequest(). Kosong kalau
     * nomor tidak cocok murid manapun ATAU muridnya tidak punya jadwal
     * akan datang -- ditangani sebagai alasan mengakhiri sesi di
     * walk()/continueFlow() (lihat emptyDynamicOptionsMessage()).
     *
     * @return list<array{label: string, value: string}>
     */
    private function myJadwalOptions(WaChatbotState $state): array
    {
        $company = $state->flow?->company;

        if (! $company) {
            return [];
        }

        $student = $this->findStudentByPhone($company->id, $this->senderPhone($state));

        if (! $student) {
            return [];
        }

        return JadwalKelas::where('student_id', $student->id)
            ->where('status', JadwalKelas::STATUS_ACTIVE)
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->limit(5)
            ->get()
            ->map(fn (JadwalKelas $k) => [
                'label' => trim(($k->mataPelajaran?->name ?? 'Kelas').' - '.$k->start_time->translatedFormat('l, d M').' '.$k->start_time->format('H:i').'-'.$k->end_time->format('H:i')),
                'value' => $k->id,
            ])
            ->all();
    }

    /**
     * Opsi dinamis untuk WaChatbotFlowStep::OPTIONS_SOURCE_OPEN_SLOTS_
     * SAME_PENGAJAR -- jam-jam lain milik PENGAJAR YANG SAMA dengan
     * jadwal lama yang dipilih murid di step OPTIONS_SOURCE_MY_JADWAL
     * sebelumnya (lihat findChosenValue()), yang belum bentrok dengan
     * jadwal murid lain manapun milik pengajar itu. `value` diisi
     * "<mulai ISO8601>|<selesai ISO8601>" -- dipecah lagi oleh
     * parseSlotValue() saat createJadwalRescheduleRequest() menyimpan
     * request-nya. Kosong kalau flow-nya tidak melalui step
     * OPTIONS_SOURCE_MY_JADWAL dulu (jadi tidak tahu jadwal lama mana
     * yang dimaksud), atau memang tidak ada jam kosong ditemukan.
     *
     * @return list<array{label: string, value: string}>
     */
    private function openSlotOptions(WaChatbotFlowStep $step, WaChatbotState $state): array
    {
        $flow = $step->flow;

        if (! $flow) {
            return [];
        }

        $originalId = $this->findChosenValue($flow, WaChatbotFlowStep::OPTIONS_SOURCE_MY_JADWAL, $state->variables ?? []);
        $original = $originalId ? JadwalKelas::find($originalId) : null;

        if (! $original) {
            return [];
        }

        return $this->findOpenSlots($original)
            ->map(fn (array $slot) => [
                'label' => $slot['start']->translatedFormat('l, d M').' '.$slot['start']->format('H:i').'-'.$slot['end']->format('H:i'),
                'value' => $slot['start']->toIso8601String().'|'.$slot['end']->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Heuristik sederhana untuk "jam kosong pengajar yang sama" -- coba
     * jam & durasi yang SAMA dengan $original, mundur maju sampai 14
     * hari ke depan, berhenti begitu dapat $limit kandidat yang tidak
     * bentrok dengan JadwalKelas manapun milik pengajar itu (murid
     * manapun, company yang sama). BUKAN sistem ketersediaan pengajar
     * yang sesungguhnya (sistem ini belum punya konsep "jam kerja
     * pengajar" tersendiri) -- cukup untuk menyodorkan beberapa
     * kandidat wajar tanpa perlu tabel/konsep baru.
     *
     * @return \Illuminate\Support\Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function findOpenSlots(JadwalKelas $original, int $limit = 5): \Illuminate\Support\Collection
    {
        $durationMinutes = $original->start_time->diffInMinutes($original->end_time);
        $candidates = collect();

        for ($daysAhead = 1; $daysAhead <= 14 && $candidates->count() < $limit; $daysAhead++) {
            $start = $original->start_time->copy()->addDays($daysAhead);
            $end = $start->copy()->addMinutes($durationMinutes);

            $conflict = JadwalKelas::where('company_id', $original->company_id)
                ->where('pengajar_id', $original->pengajar_id)
                ->where('status', JadwalKelas::STATUS_ACTIVE)
                ->where('start_time', '<', $end)
                ->where('end_time', '>', $start)
                ->exists();

            if (! $conflict) {
                $candidates->push(['start' => $start, 'end' => $end]);
            }
        }

        return $candidates;
    }

    /**
     * Nilai (kolom `value` opsi) yang dipilih customer di step choice
     * SEBELUMNYA dalam flow yang sama yang `options_source`-nya
     * $optionsSource -- dicari lewat step id-nya (bukan diasumsikan
     * urutan tetap), lalu dibaca dari $variables[$stepId.'_value']
     * (lihat continueFlow()). Dipakai openSlotOptions() untuk tahu
     * jadwal_kelas_id mana yang dipilih di step "jadwal saya", dan
     * createJadwalRescheduleRequest() untuk membaca keduanya (jadwal
     * lama & slot baru) sekaligus.
     */
    private function findChosenValue(WaChatbotFlow $flow, string $optionsSource, array $variables): ?string
    {
        $step = $flow->steps()->where('options_source', $optionsSource)->first();

        if (! $step) {
            return null;
        }

        return $variables[$step->id.'_value'] ?? null;
    }

    /**
     * Pecah nilai opsi WaChatbotFlowStep::OPTIONS_SOURCE_OPEN_SLOTS_
     * SAME_PENGAJAR ("<mulai ISO8601>|<selesai ISO8601>", lihat
     * openSlotOptions()) jadi dua Carbon, atau [null, null] kalau tidak
     * ada/tidak valid (mis. flow-nya tidak pakai step itu).
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function parseSlotValue(?string $value): array
    {
        if (! $value || ! str_contains($value, '|')) {
            return [null, null];
        }

        [$start, $end] = explode('|', $value, 2);

        try {
            return [Carbon::parse($start), Carbon::parse($end)];
        } catch (Throwable) {
            return [null, null];
        }
    }

    /**
     * Nomor HP pengirim pesan ini, dinormalisasi. Diutamakan dari
     * $state->sender_phone (diisi start() dari `sender_phone` Go, lihat
     * migration 2026_09_08_090000_add_sender_phone_to_wa_chatbot_states_
     * table.php) -- JATUH ke potongan sebelum "@" pada chat_jid hanya
     * untuk sesi lama yang dibuat sebelum kolom ini ada, atau kalau Go
     * kebetulan tidak mengirim sender_phone. chat_jid sendiri TIDAK BOLEH
     * dipakai sebagai sumber utama -- WhatsApp mengalamatkan sebagian
     * chat lewat "...@lid" (id internal), yang sama sekali tidak berisi
     * digit nomor HP, ditemukan langsung di produksi.
     */
    private function senderPhone(WaChatbotState $state): string
    {
        return $state->sender_phone !== null && $state->sender_phone !== ''
            ? $state->sender_phone
            : PhoneNumber::normalize(Str::before($state->chat_jid, '@'));
    }

    /**
     * Tebakan terbaik siapa pengirim pesan ini, dicocokkan ke nomor HP
     * orang tua ATAU murid manapun milik company ini (lihat App\Models\
     * JadwalStudent's docblock). Satu sumber kebenaran dipakai bareng
     * oleh myJadwalOptions() & createJadwalRescheduleRequest() supaya
     * keduanya selalu menebak murid yang sama untuk nomor yang sama.
     * Dibandingkan lewat App\Support\PhoneNumber::normalize() di PHP
     * (bukan raw SQL) -- jumlah Student per company biasanya kecil,
     * jadi loop di PHP aman.
     */
    private function findStudentByPhone(string $companyId, string $phone): ?JadwalStudent
    {
        if ($phone === '') {
            return null;
        }

        return JadwalStudent::where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNotNull('parent_phone_number')->orWhereNotNull('student_phone_number');
            })
            ->get(['id', 'parent_phone_number', 'student_phone_number'])
            ->first(fn (JadwalStudent $s) => ($s->parent_phone_number && PhoneNumber::normalize($s->parent_phone_number) === $phone)
                || ($s->student_phone_number && PhoneNumber::normalize($s->student_phone_number) === $phone));
    }

    /**
     * Runs one 'action' step's automated effect against this chat's
     * App\Models\WaConversation row. Best-effort: if no conversation row
     * exists yet for this (device, chat) — e.g. App\Services\Chat\
     * ConversationService::recordInbound() failed earlier in the same
     * webhook request — the action is skipped rather than throwing, since
     * that must never break the flow itself from advancing.
     *
     * @return string|null Non-null aborts the flow with this message
     *                      instead of continuing (see walk()) -- only
     *                      create_jadwal_reschedule_request ever does
     *                      this today (nomor tidak terdaftar); every
     *                      other action still always returns null, same
     *                      as before this method had a return value.
     */
    private function executeAction(WaChatbotState $state, WaChatbotFlowStep $step): ?string
    {
        $conversation = WaConversation::where('device_id', $state->device_id)
            ->where('chat_jid', $state->chat_jid)
            ->first();

        if (! $conversation) {
            Log::info('chatbot-flow: action step skipped, no conversation row for this chat yet', [
                'step_id' => $step->id,
                'action' => $step->action,
            ]);

            return null;
        }

        return match ($step->action) {
            WaChatbotFlowStep::ACTION_ASSIGN_CONVERSATION => $step->action_value
                ? $this->conversations->reassign($conversation, $step->action_value)
                : $this->conversations->autoAssign($conversation),
            WaChatbotFlowStep::ACTION_SET_STATUS_PENDING => $this->conversations->setStatus($conversation, WaConversation::STATUS_PENDING),
            WaChatbotFlowStep::ACTION_SET_STATUS_RESOLVED => $this->conversations->setStatus($conversation, WaConversation::STATUS_RESOLVED),
            WaChatbotFlowStep::ACTION_ADD_LABEL => $this->addLabel($conversation, $step->action_value),
            WaChatbotFlowStep::ACTION_HANDOFF_HUMAN => $this->conversations->setStatus($conversation, WaConversation::STATUS_PENDING),
            WaChatbotFlowStep::ACTION_CREATE_JADWAL_RESCHEDULE_REQUEST => $this->createJadwalRescheduleRequest($state, $step),
            default => null,
        };
    }

    /**
     * Tahap 3 integrasi Chat<->Jadwal -- mencatat App\Models\
     * JadwalKelasRescheduleRequest dari sesi flow ini, murni tambahan,
     * tidak mengubah action lain di atas. Diproses manual oleh staff di
     * App\Http\Controllers\Jadwal\JadwalRescheduleRequestController --
     * baris ini sendiri TIDAK PERNAH mengubah App\Models\JadwalKelas.
     *
     * Nomor pengirim WAJIB cocok dengan Student manapun di company ini
     * (lihat findStudentByPhone()) -- kalau tidak, request TIDAK
     * dibuat sama sekali, flow diakhiri dengan pesan penolakan (lihat
     * executeAction()/walk() untuk bagaimana return non-null di sini
     * mengakhiri sesi). Beda dari perilaku lama (nomor tidak dikenal
     * tetap tersimpan dengan jadwal_student_id kosong) -- sengaja
     * diperketat supaya staff tidak perlu menyaring permintaan dari
     * nomor yang tidak dikenal sama sekali.
     *
     * jadwal_kelas_id & requested_new_start_time/end_time diisi
     * OTOMATIS kalau flow-nya melalui step 'choice' dinamis
     * OPTIONS_SOURCE_MY_JADWAL / OPTIONS_SOURCE_OPEN_SLOTS_SAME_PENGAJAR
     * sebelumnya (lihat findChosenValue()) -- tetap null seperti
     * perilaku lama kalau flow-nya tidak pakai step itu (mis. cuma
     * tanya bebas lewat teks), staff yang isi manual saat approve().
     */
    private function createJadwalRescheduleRequest(WaChatbotState $state, WaChatbotFlowStep $step): ?string
    {
        $flow = $step->flow;
        $company = $flow?->company;

        if (! $company) {
            Log::warning('chatbot-flow: create_jadwal_reschedule_request skipped, flow/company invalid', ['step_id' => $step->id]);

            return null;
        }

        $phone = $this->senderPhone($state);
        $student = $this->findStudentByPhone($company->id, $phone);

        if (! $student) {
            Log::info('chatbot-flow: create_jadwal_reschedule_request ditolak, nomor tidak terdaftar', [
                'flow_id' => $flow->id,
                'company_id' => $company->id,
            ]);

            return 'Maaf, nomor Anda belum terdaftar sebagai orang tua/murid di sini. Silakan hubungi admin kami untuk mendaftarkan nomor Anda terlebih dahulu.';
        }

        $variables = $state->variables ?? [];
        $jadwalKelasId = $this->findChosenValue($flow, WaChatbotFlowStep::OPTIONS_SOURCE_MY_JADWAL, $variables);
        [$newStart, $newEnd] = $this->parseSlotValue(
            $this->findChosenValue($flow, WaChatbotFlowStep::OPTIONS_SOURCE_OPEN_SLOTS_SAME_PENGAJAR, $variables)
        );

        JadwalKelasRescheduleRequest::create([
            'company_id' => $company->id,
            'jadwal_student_id' => $student->id,
            'jadwal_kelas_id' => $jadwalKelasId,
            'device_id' => $state->device_id,
            'chat_jid' => $state->chat_jid,
            'requester_phone' => $phone !== '' ? $phone : null,
            'detail_request' => $this->buildTranscript($state, $flow),
            'requested_new_start_time' => $newStart,
            'requested_new_end_time' => $newEnd,
            'status' => JadwalKelasRescheduleRequest::STATUS_PENDING,
        ]);

        Log::info('chatbot-flow: Jadwal reschedule request dicatat', [
            'flow_id' => $flow->id,
            'company_id' => $company->id,
            'matched_student_id' => $student->id,
            'jadwal_kelas_id' => $jadwalKelasId,
        ]);

        return null;
    }

    /**
     * Transkrip pertanyaan+jawaban sejauh ini dalam sesi -- setiap step
     * message/choice yang punya jawaban tersimpan di $state->variables
     * (keyed by step id, lihat continueFlow()), dirender jadi "pesan
     * step: jawaban" per baris, diurutkan sesuai posisi step. Generik,
     * tidak berasumsi step mana artinya "jadwal yang mana" vs "waktu
     * baru" -- staff yang membaca & menafsirkan teksnya saat review.
     */
    private function buildTranscript(WaChatbotState $state, ?WaChatbotFlow $flow): string
    {
        if (! $flow) {
            return '';
        }

        $variables = $state->variables ?? [];

        return $flow->steps()
            ->whereIn('step_type', [WaChatbotFlowStep::TYPE_MESSAGE, WaChatbotFlowStep::TYPE_CHOICE])
            ->get()
            ->filter(fn (WaChatbotFlowStep $s) => array_key_exists($s->id, $variables))
            ->map(fn (WaChatbotFlowStep $s) => trim(($s->message ? $s->message.': ' : '').$variables[$s->id]))
            ->implode("
");
    }

    private function addLabel(WaConversation $conversation, ?string $labelId): void
    {
        if (! $labelId) {
            return;
        }

        WaChatLabelAssignment::firstOrCreate([
            'wa_chat_label_id' => $labelId,
            'company_id' => $conversation->company_id,
            'device_id' => $conversation->device_id,
            'chat_jid' => $conversation->chat_jid,
        ]);
    }
}
