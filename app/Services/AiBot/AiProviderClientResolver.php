<?php

namespace App\Services\AiBot;

use App\Services\AiBot\Contracts\AiProviderClient;
use RuntimeException;

/**
 * Maps a `driver` string (App\Models\WaAiBotProvider::DRIVERS) to the
 * App\Services\AiBot\Contracts\AiProviderClient implementation that
 * actually knows how to call it. Extracted out of
 * App\Services\AiBot\AiReplyGenerator (its original, only caller) once
 * App\Services\Moderation\TemplateModerationService needed the exact
 * same driver->client lookup for a completely different purpose
 * (moderating template text rather than generating a chat reply) —
 * this is the one place that needs to change when a new provider is
 * ever added: add the driver key to WaAiBotProvider::DRIVERS, write one
 * more *Client implementing AiProviderClient, wire it into the match()
 * below.
 */
class AiProviderClientResolver
{
    public function __construct(
        protected GeminiClient $gemini,
        protected OpenAiClient $openAi,
        protected AnthropicClient $anthropic,
        protected DeepSeekClient $deepSeek,
    ) {
    }

    public function resolve(?string $driver): AiProviderClient
    {
        return match ($driver) {
            'gemini' => $this->gemini,
            'openai' => $this->openAi,
            'anthropic' => $this->anthropic,
            'deepseek' => $this->deepSeek,
            default => throw new RuntimeException("Driver AI '{$driver}' belum didukung oleh mesin."),
        };
    }
}
