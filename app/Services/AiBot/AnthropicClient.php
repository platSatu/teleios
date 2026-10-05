<?php

namespace App\Services\AiBot;

use App\Services\AiBot\Contracts\AiProviderClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic's Messages API. Unlike OpenAI, the system prompt is its own
 * top-level `system` field, not a message with role=system — mixing
 * those up is the most common mistake integrating this API, so it's
 * called out explicitly here.
 */
class AnthropicClient implements AiProviderClient
{
    public function generateReply(string $apiKey, string $model, ?string $systemPrompt, string $userMessage, array $history = []): string
    {
        $messages = [];

        foreach ($history as $turn) {
            $messages[] = [
                'role' => ($turn['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                'content' => (string) ($turn['text'] ?? ''),
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $body = [
            'model' => $model,
            'max_tokens' => self::MAX_OUTPUT_TOKENS,
            'temperature' => self::TEMPERATURE,
            'messages' => $messages,
        ];

        if ($systemPrompt) {
            $body['system'] = $systemPrompt;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', $body);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error ('.$response->status().'): '.$response->body());
        }

        // Gabungkan semua blok teks (dulu hanya blok pertama yang dibaca).
        $text = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Anthropic API mengembalikan respons kosong: '.$response->body());
        }

        return trim($text);
    }
}
