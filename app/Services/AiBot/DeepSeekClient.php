<?php

namespace App\Services\AiBot;

use App\Services\AiBot\Contracts\AiProviderClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * DeepSeek's Chat Completions REST endpoint — API-compatible with
 * OpenAI's own shape (same request/response structure as
 * App\Services\AiBot\OpenAiClient), just a different base URL and model
 * namespace (e.g. "deepseek-chat", "deepseek-reasoner").
 */
class DeepSeekClient implements AiProviderClient
{
    public function generateReply(string $apiKey, string $model, ?string $systemPrompt, string $userMessage, array $history = []): string
    {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($history as $turn) {
            $messages[] = [
                'role' => ($turn['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                'content' => (string) ($turn['text'] ?? ''),
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.deepseek.com/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 800,
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('DeepSeek API error ('.$response->status().'): '.$response->body());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('DeepSeek API mengembalikan respons kosong: '.$response->body());
        }

        return trim($text);
    }
}
