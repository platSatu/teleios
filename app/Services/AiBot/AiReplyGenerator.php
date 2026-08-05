<?php

namespace App\Services\AiBot;

use App\Models\WaAiBot;
use RuntimeException;

/**
 * Single entry point the rest of the app calls to get an AI reply for
 * one WaAiBot config — picks the right App\Services\AiBot\Contracts\
 * AiProviderClient based on the bot's provider->driver
 * (App\Models\WaAiBotProvider::DRIVERS) and forwards the call. This is
 * the only class that needs to change if a new provider is ever added:
 * add the driver key to WaAiBotProvider::DRIVERS, write one more
 * *Client implementing AiProviderClient, wire it into the match() below.
 */
class AiReplyGenerator
{
    public function __construct(
        protected GeminiClient $gemini,
        protected OpenAiClient $openAi,
        protected AnthropicClient $anthropic,
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

        $client = match ($driver) {
            'gemini' => $this->gemini,
            'openai' => $this->openAi,
            'anthropic' => $this->anthropic,
            default => throw new RuntimeException("Driver AI '{$driver}' belum didukung oleh mesin."),
        };

        return $client->generateReply($apiKey, $model, $bot->ai_behaviour_prompt, $userMessage, $history);
    }
}
