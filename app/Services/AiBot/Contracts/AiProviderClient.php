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
