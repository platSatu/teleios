<?php

namespace App\Services\AiBot;

use App\Services\AiBot\Contracts\AiProviderClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI's Chat Completions REST endpoint — the same shape every
 * OpenAI-compatible provider follows, so this client would also work
 * unmodified against a self-hosted/compatible endpoint if that's ever
 * needed, just by pointing `model` at a different name.
 */
class OpenAiClient implements AiProviderClient
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
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 800,
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI API error ('.$response->status().'): '.$response->body());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('OpenAI API mengembalikan respons kosong: '.$response->body());
        }

        return trim($text);
    }
}
