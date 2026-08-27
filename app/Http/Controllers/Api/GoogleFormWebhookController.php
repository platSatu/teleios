<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaFormIntegration;
use App\Models\WaFormSubmission;
use App\Models\WaMessageTemplate;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Public receiving endpoint for "Chat > Third Party > Google Form"
 * (App\Models\WaFormIntegration) — the inverse of the old approach on the
 * API Key page, which had Apps Script push straight to
 * WaApiSendMessageController with a hardcoded message. Here Apps Script
 * posts the RAW form answers as one JSON object (built from the form's
 * own question titles as keys), and this controller decides what to do
 * with it server-side:
 *
 *   1. Look up the integration purely by the unguessable `webhook_token`
 *      path segment — no header/session auth, same "the token in the URL
 *      IS the credential" model as most third-party webhook receivers
 *      (Stripe, Duitku's callback, etc. all differ only in HOW they prove
 *      authenticity; this one has no separate signature because the
 *      token itself never appears anywhere else once pasted into the
 *      company's own Apps Script).
 *   2. Pull the recipient's WhatsApp number out of the payload using the
 *      company-configured `target_number_field` key (matched
 *      case-insensitively/trimmed, since Apps Script sends the exact
 *      Google Form question title — easy to fat-finger differently than
 *      what was typed into the integration's settings).
 *   3. Render the configured WaMessageTemplate's body, best-effort
 *      substituting any {{variable}} placeholder whose name matches a
 *      payload key (same case-insensitive matching) — see
 *      renderTemplate() below. A placeholder with no matching answer is
 *      left as-is, same "never silently produce a half-blank message"
 *      intent as the rest of this app's template handling.
 *   4. Send through the SAME path every other outbound message in this
 *      app uses (App\Jobs\SendAutoReplyMessage, WaApiSendMessageController):
 *      mint a short-lived system JWT for the company owner, then
 *      App\Services\Chat\InboxService::send().
 *   5. Log a WaFormSubmission row either way (sent or failed) so the
 *      integration's detail page has real evidence of what happened,
 *      instead of the company having to guess from WhatsApp alone.
 *
 * Always responds 200 with a small JSON body — Apps Script's
 * UrlFetchApp doesn't retry non-2xx responses on its own, and a failed
 * send is already visible via the submissions log, so there's no upside
 * to a 4xx/5xx here beyond an invalid/unknown token.
 */
class GoogleFormWebhookController extends Controller
{
    public function receive(Request $request, string $token, SystemJwtService $jwtService, InboxService $inbox): JsonResponse
    {
        $integration = WaFormIntegration::where('webhook_token', $token)
            ->where('type', 'google_form')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Webhook tidak ditemukan.'], 404);
        }

        if ($integration->status !== 'active') {
            return response()->json(['error' => 'Integrasi ini sedang nonaktif.'], 200);
        }

        // Apps Script always posts a flat JSON object of {question title:
        // answer}. Accept the whole request body as the payload rather
        // than validating specific keys — the point of this feature is
        // that the field set is whatever the company's own form asks,
        // not something this app can know in advance.
        $payload = $request->all();

        $targetNumber = $this->extractTargetNumber($payload, $integration->target_number_field);

        $submission = new WaFormSubmission([
            'wa_form_integration_id' => $integration->id,
            'payload' => $payload,
            'target_number' => $targetNumber,
        ]);

        if (! $targetNumber) {
            $submission->status = 'failed';
            $submission->error_message = "Field \"{$integration->target_number_field}\" tidak ditemukan atau kosong di data yang diterima.";
            $submission->save();

            return response()->json(['error' => $submission->error_message], 200);
        }

        $template = $integration->wa_message_template_id
            ? WaMessageTemplate::where('id', $integration->wa_message_template_id)->first()
            : null;

        if (! $template || ! $template->template) {
            $submission->status = 'failed';
            $submission->error_message = 'WA Template belum dipilih atau isinya kosong.';
            $submission->save();

            return response()->json(['error' => $submission->error_message], 200);
        }

        $message = $this->renderTemplate($template, $payload);
        $submission->message_sent = $message;

        $owner = $integration->company?->user;

        if (! $owner) {
            $submission->status = 'failed';
            $submission->error_message = 'Company pemilik integrasi ini tidak memiliki user pemilik yang valid.';
            $submission->save();

            return response()->json(['error' => $submission->error_message], 200);
        }

        try {
            $jwt = $jwtService->mintFor($owner);
            $targetJid = $this->toIndividualJid($targetNumber);

            if ($template->attachment_path && Storage::disk('public')->exists($template->attachment_path)) {
                // Template has a stored image/document — send it as the
                // media message's caption instead of a separate text
                // message, same as the Inbox template picker's behavior,
                // so the file/image actually goes out instead of being
                // silently dropped.
                $inbox->sendStoredMedia(
                    $jwt,
                    $integration->device_id,
                    $targetJid,
                    Storage::disk('public')->path($template->attachment_path),
                    $template->attachment_original_name ?: basename($template->attachment_path),
                    $template->attachment_type,
                    $message
                );
            } else {
                $inbox->send($jwt, $integration->device_id, $targetJid, $message);
            }

            $submission->status = 'sent';
            $submission->save();

            $integration->forceFill([
                'last_triggered_at' => now(),
                'trigger_count' => $integration->trigger_count + 1,
            ])->save();

            return response()->json(['status' => 'sent']);
        } catch (Throwable $e) {
            Log::warning('GoogleFormWebhookController: send failed', [
                'integration_id' => $integration->id,
                'device_id' => $integration->device_id,
                'to' => $targetNumber,
                'error' => $e->getMessage(),
            ]);

            $submission->status = 'failed';
            $submission->error_message = $this->describeSendFailure($e);
            $submission->save();

            return response()->json(['error' => $submission->error_message], 200);
        }
    }

    /**
     * Turns InboxService::send()'s raw exception into a message the
     * company can actually act on — this used to always be the same
     * generic "Pastikan device masih terhubung." string no matter what
     * really went wrong, which is exactly what made a genuine "device
     * not found" failure indistinguishable from a template problem or a
     * transient network error.
     *
     * App\Services\Chat\InboxService::request() wraps the Go backend's
     * raw JSON error body straight into its RuntimeException message
     * ("...failed: {"error":"..."}"), so this pulls the `error` key back
     * out when present and maps the couple of causes a company can
     * self-serve on to a clear, specific Indonesian sentence.
     */
    private function describeSendFailure(Throwable $e): string
    {
        $reason = null;

        if (preg_match('/\{.*\}\s*$/s', $e->getMessage(), $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && ! empty($decoded['error'])) {
                $reason = (string) $decoded['error'];
            }
        }

        if ($reason === null) {
            return 'Gagal mengirim pesan. Pastikan device masih terhubung.';
        }

        if (str_contains($reason, 'device not found')) {
            return 'Device pengirim untuk integrasi ini tidak ditemukan (mungkin sudah dihapus atau dipindah ke company lain). Buka menu Edit integrasi ini, lalu pilih ulang device pengirimnya.';
        }

        if (str_contains($reason, 'not connected')) {
            return 'Device pengirim untuk integrasi ini sedang tidak terhubung ke WhatsApp. Sambungkan ulang device-nya di menu Connect Device, lalu coba isi form lagi.';
        }

        return "Gagal mengirim pesan: {$reason}.";
    }

    /**
     * Case-insensitive, trimmed key lookup — the field name saved on the
     * integration has to match a Google Form question title exactly by
     * meaning, but "Nomor HP" vs "nomor hp" vs " Nomor HP " shouldn't be
     * treated as three different fields. The match is normalized through
     * App\Support\PhoneNumber::normalize() — same single source of truth
     * every JID builder in this app delegates to — so a form answer typed
     * as "0812..."/"812..." resolves to the same "6281..." number a
     * manually entered Buku Telepon/Kontak contact with the same digits
     * would; anything that normalizes to nothing is treated as missing.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractTargetNumber(array $payload, string $fieldName): ?string
    {
        $needle = mb_strtolower(trim($fieldName));
        $raw = null;

        foreach ($payload as $key => $value) {
            if (mb_strtolower(trim((string) $key)) === $needle) {
                $raw = $value;
                break;
            }
        }

        if ($raw === null) {
            return null;
        }

        $normalized = PhoneNumber::normalize((string) $raw);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Best-effort {{variable}} substitution using whatever the form
     * happened to answer — a variable with no matching payload key (by
     * the same case-insensitive/trimmed matching as the target number)
     * is left exactly as written, since silently blanking it would make
     * a broken mapping harder to notice than a visible {{placeholder}}.
     *
     * Runs against composedMessage() (header+body+link+buttons-as-text+
     * footer) rather than the bare `template` body column — previously
     * this only ever sent the plain body, silently dropping the header,
     * link, and buttons even when the template had them filled in.
     */
    private function renderTemplate(WaMessageTemplate $template, array $payload): string
    {
        $normalizedPayload = [];

        foreach ($payload as $key => $value) {
            $normalizedPayload[mb_strtolower(trim((string) $key))] = is_scalar($value) ? (string) $value : '';
        }

        return preg_replace_callback(
            WaMessageTemplate::VARIABLE_PATTERN,
            function (array $matches) use ($normalizedPayload) {
                $variable = mb_strtolower(trim($matches[1]));

                return array_key_exists($variable, $normalizedPayload) && $normalizedPayload[$variable] !== ''
                    ? $normalizedPayload[$variable]
                    : $matches[0];
            },
            $template->composedMessage()
        );
    }

    /**
     * Same bare-number-to-JID normalization as
     * WaApiSendMessageController::normalizeJid() — target_number here is
     * always already run through App\Support\PhoneNumber::normalize()
     * (see extractTargetNumber() above), so this only ever appends the
     * individual-chat suffix.
     */
    private function toIndividualJid(string $number): string
    {
        return $number.'@s.whatsapp.net';
    }
}
