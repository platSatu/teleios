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

    /**
     * Combines the free-text "Perilaku AI" instructions with the
     * extracted text of the optional "Lampiran Knowledge Base" upload
     * (wa_ai_bots.knowledge_base_text — see
     * App\Services\AiBot\KnowledgeBaseExtractor and
     * App\Http\Controllers\Chat\AiBotController::attachFile()), so a bot
     * with a catalog/FAQ document attached actually uses it when
     * answering instead of the file just sitting in storage unused.
     */
    private function buildSystemPrompt(WaAiBot $bot): ?string
    {
        $behaviour = trim((string) $bot->ai_behaviour_prompt);
        $knowledge = trim((string) $bot->knowledge_base_text);

        if ($knowledge === '') {
            return $behaviour !== '' ? $behaviour : null;
        }

        $knowledgeBlock = "Berikut informasi referensi (knowledge base) dari dokumen yang diunggah — gunakan ini sebagai sumber jawaban jika relevan dengan pertanyaan pelanggan, dan jangan mengarang informasi yang tidak ada di sini atau di instruksi di atas:\n\n".$knowledge;

        return $behaviour !== '' ? $behaviour."\n\n---\n\n".$knowledgeBlock : $knowledgeBlock;
    }
}
