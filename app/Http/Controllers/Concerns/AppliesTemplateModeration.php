<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Moderation\ModerationResult;

/**
 * Shared by Chat\CategoryTemplateController and Chat\
 * MessageTemplateController — both hand a
 * App\Services\Moderation\ModerationResult back from
 * App\Services\Moderation\TemplateModerationService::moderate() through
 * the exact same two translations: which `review_status`/
 * `rejection_reason`/`reviewed_*` columns to write, and what to tell
 * the user in the flash message. Kept here once instead of duplicated
 * in both controllers.
 */
trait AppliesTemplateModeration
{
    /**
     * 'approved' and 'corrected' both land on review_status=approved —
     * the correction itself, if any, already happened to the content
     * before this is called. reviewed_by stays null since no human made
     * the call; reviewed_at records when the AI actually evaluated it
     * (left null for 'unavailable' — nothing was actually evaluated).
     *
     * @return array<string, mixed>
     */
    private function reviewFieldsFor(ModerationResult $moderation): array
    {
        if ($moderation->isRejected()) {
            return [
                'review_status' => 'rejected',
                'rejection_reason' => $moderation->reason,
                'reviewed_by' => null,
                'reviewed_at' => now(),
            ];
        }

        if ($moderation->isUnavailable()) {
            return [
                'review_status' => 'pending',
                'rejection_reason' => $moderation->reason,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ];
        }

        return [
            'review_status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => now(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function flashFor(ModerationResult $moderation, string $verb, ?string $correctionNote = null): array
    {
        if ($moderation->isRejected()) {
            return ['error', "Ditolak oleh AI moderasi: {$moderation->reason}"];
        }

        if ($moderation->isUnavailable()) {
            return ['success', "{$verb}, menunggu moderasi AI (belum bisa dijalankan saat ini: {$moderation->reason})"];
        }

        if ($moderation->isCorrected()) {
            $note = $correctionNote ?? 'beberapa bagian disesuaikan otomatis agar sesuai kebijakan konten';

            return ['success', "{$verb} dan lolos moderasi AI — {$note}."];
        }

        return ['success', "{$verb} dan lolos moderasi AI."];
    }
}
