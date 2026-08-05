<?php

namespace App\Services\AiBot;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Turns an uploaded "Lampiran Knowledge Base" file (txt/docx/pdf) into
 * plain text App\Services\AiBot\AiReplyGenerator can hand to the AI
 * provider as extra context. Called once at upload time
 * (App\Http\Controllers\Chat\AiBotController::attachFile()) — the result
 * is cached on wa_ai_bots.knowledge_base_text so a bad/slow parse never
 * happens on the hot path of answering an incoming WhatsApp message.
 *
 * Extraction is best-effort: a scanned/image-only PDF, a corrupted file,
 * or a format quirk PhpWord/pdfparser trips on should never break saving
 * the bot config — callers should treat a null return as "no knowledge
 * text available", not a hard error.
 */
class KnowledgeBaseExtractor
{
    /**
     * Keeps the system prompt (and therefore every single AI API call)
     * from ballooning in size/cost just because someone uploaded a huge
     * document — more than enough for a product catalog or FAQ sheet.
     */
    private const MAX_CHARS = 20000;

    public function extract(string $storagePath, string $originalName): ?string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $absolutePath = Storage::disk('local')->path($storagePath);

        try {
            $text = match ($extension) {
                'txt' => $this->extractTxt($absolutePath),
                'pdf' => $this->extractPdf($absolutePath),
                'docx' => $this->extractDocx($absolutePath),
                default => null,
            };
        } catch (Throwable $e) {
            Log::warning('AI Bot knowledge base extraction failed', [
                'file' => $originalName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $text) {
            return null;
        }

        $text = trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\R{3,}/', "\n\n", $text)) ?? $text);

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > self::MAX_CHARS
            ? mb_substr($text, 0, self::MAX_CHARS)."\n\n[...dipotong, dokumen terlalu panjang]"
            : $text;
    }

    private function extractTxt(string $absolutePath): ?string
    {
        $contents = file_get_contents($absolutePath);

        return $contents === false ? null : $contents;
    }

    private function extractPdf(string $absolutePath): ?string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($absolutePath);

        return $pdf->getText();
    }

    private function extractDocx(string $absolutePath): ?string
    {
        $phpWord = WordIOFactory::load($absolutePath, 'Word2007');
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            $text .= $this->walkElements($section->getElements());
        }

        return $text;
    }

    /**
     * PhpWord's element tree isn't uniform — a paragraph's runs, a
     * table's rows/cells, and a list item can each nest text a different
     * number of levels deep. Recursing through getElements()/getRows()/
     * getCells() until something exposes getText() catches all of them
     * (tables matter here since a price list/FAQ sheet is often a table).
     */
    private function walkElements(array $elements): string
    {
        $text = '';

        foreach ($elements as $element) {
            if (method_exists($element, 'getRows')) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $text .= $this->walkElements($cell->getElements());
                    }
                }
            } elseif (method_exists($element, 'getText')) {
                $value = $element->getText();
                $text .= (is_array($value) ? implode(' ', $value) : $value)."\n";
            } elseif (method_exists($element, 'getElements')) {
                $text .= $this->walkElements($element->getElements());
            }
        }

        return $text;
    }
}
