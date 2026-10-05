<?php

namespace App\Services\AiBot;

use App\Services\AiBot\Contracts\AiProviderClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini's `generateContent` REST endpoint (confirmed shape as of
 * Aug 2026 — free-tier models currently offered: gemini-3.5-flash,
 * gemini-3.5-flash-lite, see the free API key from aistudio.google.com).
 * The API key travels as a `?key=` query param, per Google's own docs —
 * not a header, unlike OpenAI/Anthropic below.
 */
class GeminiClient implements AiProviderClient
{
    public function generateReply(string $apiKey, string $model, ?string $systemPrompt, string $userMessage, array $history = []): string
    {
        $contents = [];

        foreach ($history as $turn) {
            $contents[] = [
                // Gemini calls the assistant's own turns 'model', not
                // 'assistant' — everything else maps 1:1.
                'role' => ($turn['role'] ?? 'user') === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) ($turn['text'] ?? '')]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                // Model Gemini 2.5+ ikut memakai jatah ini untuk "berpikir",
                // jadi diberi ruang 2x supaya jawabannya tidak habis duluan.
                'maxOutputTokens' => self::MAX_OUTPUT_TOKENS * 2,
                'temperature' => self::TEMPERATURE,
            ],
        ];

        if ($systemPrompt) {
            $body['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }

        $response = Http::timeout(30)->post(
            'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent?key='.urlencode($apiKey),
            $body
        );

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error ('.$response->status().'): '.$response->body());
        }

        // Jawaban bisa datang dalam beberapa "part" (dulu hanya part
        // pertama yang dibaca); part berisi proses berpikir (thought) dibuang.
        $text = collect($response->json('candidates.0.content.parts', []))
            ->reject(fn ($part) => ! empty($part['thought']))
            ->pluck('text')
            ->filter(fn ($chunk) => is_string($chunk))
            ->implode('');

        if (! is_string($text) || trim($text) === '') {
            // A common non-error cause: candidates.0.finishReason ==
            // "SAFETY" or "MAX_TOKENS" with no parts at all.
            $reason = $response->json('candidates.0.finishReason', 'unknown');
            throw new RuntimeException("Gemini API mengembalikan respons kosong (finishReason: {$reason}).");
        }

        return trim($text);
    }
}
