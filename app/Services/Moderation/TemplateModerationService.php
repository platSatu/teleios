<?php

namespace App\Services\Moderation;

use App\Models\AiModerationSetting;
use App\Services\AiBot\AiProviderClientResolver;
use Throwable;

/**
 * Runs Kategori Template (App\Models\WaCategoryTemplate) and WA
 * Template (App\Models\WaMessageTemplate) content through the
 * superadmin-configured App\Models\AiModerationSetting — this REPLACES
 * the old manual superadmin approve/reject queue (Superadmin\
 * WaTemplateReviewController) for both resources. See
 * App\Http\Controllers\Chat\CategoryTemplateController and
 * Chat\MessageTemplateController for the two callers.
 *
 * Two layers, cheapest first:
 *   1. A deterministic keyword pre-check (`blocked_keywords`) — instant,
 *      no API call, catches known bad words dependably.
 *   2. The configured AI provider, asked to judge (and where possible,
 *      fix) the text against whichever of the four category toggles are
 *      switched on, plus any free-text `custom_instructions`.
 *
 * Never throws for an ordinary moderation failure (network error,
 * missing config, unparseable AI response) — those all come back as
 * ModerationResult::unavailable() so the caller can hold the content as
 * pending instead of crashing the save request.
 */
class TemplateModerationService
{
    public function __construct(protected AiProviderClientResolver $clients)
    {
    }

    /**
     * @param  array<string, string>  $fields  e.g. ['name' => 'Promo Akhir Tahun']
     *                                          or ['header' => ..., 'body' => ..., 'footer' => ...]
     */
    public function moderate(array $fields): ModerationResult
    {
        $settings = AiModerationSetting::current();

        if (! $settings->isUsable()) {
            return ModerationResult::unavailable(
                'AI moderasi belum dikonfigurasi atau belum diaktifkan superadmin (Superadmin > AI Bot > Moderasi AI).'
            );
        }

        if ($hit = $this->matchBlockedKeyword($settings->blocked_keywords, $fields)) {
            return ModerationResult::rejected("Mengandung kata yang dilarang platform: \"{$hit}\".");
        }

        try {
            $client = $this->clients->resolve($settings->provider?->driver);

            $raw = $client->generateReply(
                (string) $settings->api_key,
                (string) ($settings->model?->name),
                $this->buildSystemPrompt($settings, array_keys($fields)),
                json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable $e) {
            report($e);

            return ModerationResult::unavailable('AI moderasi gagal dijalankan: '.$e->getMessage());
        }

        return $this->parseResponse($raw, $fields);
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function matchBlockedKeyword(?string $blockedKeywords, array $fields): ?string
    {
        if (! $blockedKeywords) {
            return null;
        }

        $keywords = collect(preg_split('/[,\r\n]+/', $blockedKeywords))
            ->map(fn ($k) => trim($k))
            ->filter();

        if ($keywords->isEmpty()) {
            return null;
        }

        $haystack = mb_strtolower(implode(' ', array_map('strval', $fields)));

        foreach ($keywords as $keyword) {
            if (str_contains($haystack, mb_strtolower($keyword))) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $fieldNames
     */
    private function buildSystemPrompt(AiModerationSetting $settings, array $fieldNames): string
    {
        $checks = [];

        if ($settings->block_pornography) {
            $checks[] = 'konten pornografi atau seksual eksplisit';
        }
        if ($settings->block_gambling) {
            $checks[] = 'promosi atau ajakan judi';
        }
        if ($settings->block_drugs) {
            $checks[] = 'promosi atau ajakan narkoba/zat terlarang';
        }
        if ($settings->block_negative_language) {
            $checks[] = 'kata kasar, ujaran kebencian, atau kalimat negatif/merendahkan lainnya';
        }

        $checklist = $checks === []
            ? 'tidak ada kategori spesifik yang diaktifkan — cukup periksa kewajaran umum sebagai pesan bisnis WhatsApp'
            : implode(', ', $checks);

        $fieldList = implode(', ', $fieldNames);
        $customInstructions = trim((string) $settings->custom_instructions);

        $prompt = "Anda adalah AI moderator konten untuk Konexa, platform WhatsApp Business. "
            ."Tugas Anda memeriksa teks yang akan dipakai perusahaan pengguna platform untuk mengirim pesan WhatsApp ke pelanggan mereka.\n\n"
            ."Periksa apakah teks berikut mengandung: {$checklist}.\n\n"
            ."Aturan keputusan:\n"
            ."- Jika teks sudah bersih dari semua hal di atas, kembalikan status \"approved\" dan field-field apa adanya (tidak diubah).\n"
            ."- Jika ada pelanggaran RINGAN yang bisa diperbaiki tanpa mengubah maksud/informasi inti pesan (mis. kata kasar diganti kata netral), PERBAIKI teksnya, kembalikan status \"corrected\" dengan versi yang sudah diperbaiki di setiap field.\n"
            ."- Jika pelanggaran terlalu berat atau seluruh isi memang tentang topik terlarang sehingga tidak bisa diperbaiki tanpa mengubah makna, kembalikan status \"rejected\" dan jelaskan alasannya secara singkat dan jelas dalam Bahasa Indonesia.\n\n";

        if ($customInstructions !== '') {
            $prompt .= "Aturan tambahan dari superadmin platform: {$customInstructions}\n\n";
        }

        $prompt .= "Field yang dikirim (dalam JSON): {$fieldList}.\n\n"
            ."Balas HANYA dalam format JSON valid, TANPA teks lain, TANPA markdown code block, persis struktur berikut:\n"
            .'{"status": "approved|corrected|rejected", "fields": {"nama_field": "isi final field ini"}, "reason": "alasan singkat jika rejected, string kosong jika tidak"}'."\n\n"
            .'"fields" harus memuat SEMUA field yang dikirim, dengan isi sama seperti input jika status approved, dan versi hasil perbaikan jika status corrected.';

        return $prompt;
    }

    /**
     * @param  array<string, string>  $originalFields
     */
    private function parseResponse(string $raw, array $originalFields): ModerationResult
    {
        $json = $this->extractJson($raw);

        if ($json === null || ! isset($json['status'])) {
            return ModerationResult::unavailable('AI moderasi memberi respons yang tidak bisa dibaca sistem.');
        }

        $status = $json['status'];
        $fields = is_array($json['fields'] ?? null) ? $json['fields'] : [];
        $reason = is_string($json['reason'] ?? null) ? trim($json['reason']) : '';

        return match ($status) {
            'approved' => ModerationResult::approved(),
            'corrected' => ModerationResult::corrected(
                array_intersect_key($fields, $originalFields) ?: $originalFields
            ),
            'rejected' => ModerationResult::rejected(
                $reason !== '' ? $reason : 'Konten ditolak oleh AI moderasi tanpa alasan spesifik.'
            ),
            default => ModerationResult::unavailable('AI moderasi memberi status yang tidak dikenali: '.$status),
        };
    }

    /**
     * Defensive JSON extraction — strips a ```json fence if the model
     * wrapped its answer in one despite instructions not to, then falls
     * back to grabbing the first {...} block if there's stray prose
     * around the JSON. Returns null (never throws) if nothing usable
     * can be parsed out.
     *
     * @return array<string, mixed>|null
     */
    private function extractJson(string $raw): ?array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
