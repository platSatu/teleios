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

        // Disamakan dengan GeminiClient (6 Oktober 2026):
        // - max_completion_tokens (pengganti max_tokens, wajib untuk model
        //   baru seperti gpt-5 / o-series; tetap didukung gpt-4o/4.1);
        // - model reasoning (o1/o3/o4/gpt-5) ikut memakai jatah token untuk
        //   "berpikir", jadi diberi ruang 2x, dan TIDAK menerima temperature
        //   selain bawaan -- dikirim hanya untuk model biasa.
        $isReasoning = (bool) preg_match('/^(o\d|gpt-5)/i', $model);

        $body = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => self::MAX_OUTPUT_TOKENS * ($isReasoning ? 2 : 1),
        ];

        if (! $isReasoning) {
            $body['temperature'] = self::TEMPERATURE;
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', $body);

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
