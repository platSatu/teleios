<?php

namespace App\Services\AiBot;

use App\Models\WaAiBot;
use RuntimeException;

/**
 * Single entry point the rest of the app calls to get an AI reply for
 * one WaAiBot config — validates the bot's driver/model/api key are all
 * present, then delegates the actual App\Services\AiBot\Contracts\
 * AiProviderClient lookup to App\Services\AiBot\AiProviderClientResolver
 * (shared with App\Services\Moderation\TemplateModerationService, the
 * other caller that needs "driver string -> working client").
 */
class AiReplyGenerator
{
    public function __construct(
        protected AiProviderClientResolver $clients,
    ) {
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history
     */
    public function generate(WaAiBot $bot, string $userMessage, array $history = []): string
    {
        $driver = $bot->provider?->driver;
        $model = $bot->model?->name;
        // `api_configuration` is cast `encrypted` on the model (see
        // App\Models\WaAiBot) — this already reads back the plain-text
        // API key a company pasted into the form, decrypted
        // transparently by Eloquent.
        $apiKey = $bot->api_configuration;

        if (! $driver) {
            throw new RuntimeException('Provider AI Bot ini belum punya driver terdaftar. Atur di Superadmin > AI Bot > Provider.');
        }

        if (! $model) {
            throw new RuntimeException('AI Bot ini belum memilih model.');
        }

        if (! $apiKey) {
            throw new RuntimeException('AI Bot ini belum diisi API key.');
        }

        $client = $this->clients->resolve($driver);

        return $client->generateReply($apiKey, $model, $this->buildSystemPrompt($bot), $userMessage, $history);
    }

    /** Aturan gaya balasan WA (5 Oktober 2026), selalu ditambahkan di akhir instruksi. */
    private const CHAT_STYLE = 'Kamu membalas lewat chat WhatsApp: jawab singkat, jelas, dan ramah (paling banyak sekitar 3 paragraf pendek), tanpa format markdown seperti ** atau #. Ucapkan salam (halo / selamat pagi, siang, sore, malam) HANYA di balasan pertama percakapan; balasan berikutnya langsung ke inti tanpa salam pembuka. Kalau informasinya tidak ada, katakan terus terang dan tawarkan untuk dihubungkan ke admin. Jangan mengarang harga, promo, atau janji. Kamu TIDAK bisa melihat atau mengecek data transaksi, saldo, invoice, pesanan, atau akun pelanggan: jangan pernah mengaku sudah mengecek. Kalau pelanggan memberi data seperti itu, ucapkan terima kasih, sampaikan bahwa datanya akan diteruskan ke tim admin untuk dicek, dan tawarkan untuk dihubungkan ke admin.';

    /**
     * Combines the free-text "Perilaku AI" instructions with the
     * extracted text of the optional "Lampiran Knowledge Base" upload
     * (wa_ai_bots.knowledge_base_text — see
     * App\Services\AiBot\KnowledgeBaseExtractor and
     * App\Http\Controllers\Chat\AiBotController::attachFile()), so a bot
     * with a catalog/FAQ document attached actually uses it when
     * answering instead of the file just sitting in storage unused.
     * CHAT_STYLE selalu ditambahkan paling akhir.
     */
    /**
     * Error sementara dari provider AI (server sibuk / rate limit per menit /
     * koneksi putus) yang layak dicoba ulang. Kuota HARIAN / billing habis
     * (429 "PerDay" Gemini, "insufficient_quota" OpenAI) TIDAK dicoba ulang
     * -- tidak akan pulih dalam hitungan detik, hanya membuang permintaan.
     * Client melempar "... API error (503): ...".
     */
    public static function isTransient(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return $e instanceof \Illuminate\Http\Client\ConnectionException
            || (preg_match('/API error \((429|500|502|503|504)\)/', $message) && ! self::isQuotaExhausted($message));
    }

    /** Pesan error AI yang mudah dipahami pemilik toko (disimpan di AI Bot > last_error). */
    public static function friendlyError(\Throwable $e): string
    {
        $message = $e->getMessage();
        preg_match('/API error \((\d{3})\)/', $message, $match);

        return match (true) {
            self::isQuotaExhausted($message) => 'Kuota API AI habis (batas harian / billing). Aktifkan billing di akun provider AI atau ganti API key.',
            in_array($match[1] ?? null, ['401', '403'], true) => 'API key AI ditolak (salah / tidak punya akses). Periksa API key di pengaturan AI Bot.',
            ($match[1] ?? null) === '404' => 'Model AI tidak ditemukan / tidak tersedia untuk API key ini. Pilih model lain.',
            ($match[1] ?? null) === '429' => 'Terlalu banyak permintaan ke AI dalam waktu singkat (rate limit). Coba lagi sebentar.',
            in_array($match[1] ?? null, ['500', '502', '503', '504'], true) || $e instanceof \Illuminate\Http\Client\ConnectionException => 'Server AI sedang sibuk / tidak bisa dihubungi. Coba lagi beberapa saat.',
            default => mb_substr($message, 0, 300),
        };
    }

    private static function isQuotaExhausted(string $message): bool
    {
        return str_contains($message, '(429)') && (bool) preg_match('/PerDay|insufficient_quota/i', $message);
    }

    private function buildSystemPrompt(WaAiBot $bot): string
    {
        $behaviour = trim((string) $bot->ai_behaviour_prompt);
        $knowledge = trim((string) $bot->knowledge_base_text);

        $knowledgeBlock = $knowledge !== ''
            ? "Berikut informasi referensi (knowledge base) dari dokumen yang diunggah — gunakan ini sebagai sumber jawaban jika relevan dengan pertanyaan pelanggan, dan jangan mengarang informasi yang tidak ada di sini atau di instruksi di atas:\n\n".$knowledge
            : '';

        return implode("\n\n---\n\n", array_filter([$behaviour, $knowledgeBlock, self::CHAT_STYLE]));
    }
}
