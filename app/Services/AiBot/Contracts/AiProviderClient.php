<?php

namespace App\Services\AiBot\Contracts;

/**
 * One implementation per `driver` in App\Models\WaAiBotProvider::DRIVERS
 * (see GeminiClient, OpenAiClient, AnthropicClient) — each wraps that
 * provider's own REST API shape behind this one method, so
 * App\Services\AiBot\AiReplyGenerator never has to know the difference.
 */
interface AiProviderClient
{
    /**
     * Batas panjang jawaban (5 Oktober 2026) -- dulu 800 token di tiap
     * client, terlalu kecil sampai jawaban terpotong di tengah. Panjang
     * jawaban sehari-hari tetap dijaga singkat lewat instruksi gaya chat
     * di AiReplyGenerator, ini hanya plafon.
     */
    public const MAX_OUTPUT_TOKENS = 2048;

    /** Rendah supaya jawaban CS konsisten & tidak melantur (dulu 0.7). */
    public const TEMPERATURE = 0.3;

    /**
     * @param  array<int, array{role: string, text: string}>  $history  Prior
     *                                                                   turns, oldest first, role is 'user' or 'assistant'. Optional —
     *                                                                   an empty array still produces a single-turn reply.
     * @return string the AI's reply text, ready to send back on WhatsApp
     *
     * @throws \RuntimeException on any API/network/empty-response failure
     */
    public function generateReply(
        string $apiKey,
        string $model,
        ?string $systemPrompt,
        string $userMessage,
        array $history = []
    ): string;
}
