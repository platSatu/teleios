<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaCsatSurvey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives "a WhatsApp poll vote just arrived" from the Go backend (see
 * g_backend's WaInboxService.notifyPollVoteWebhook, fired from
 * handlePollVote() right after it decrypts and records the vote in its
 * own wa_poll_votes table). Generic on the Go side (any poll this app
 * ever sends fires this), but today the only consumer is Fitur #7's CSAT
 * survey: this matches the vote's poll_message_id against
 * App\Models\WaCsatSurvey and, if found, scores the response.
 *
 * A vote for a poll that ISN'T a CSAT survey (an ordinary Fitur #5 poll
 * sent from the Inbox, say) simply finds no matching row here and is a
 * silent no-op — its result is still visible via App\Services\Chat\
 * InboxService::pollResults(), this webhook just has nothing further to
 * do with it.
 */
class WaPollVoteWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string'],
            'poll_message_id' => ['required', 'string'],
            'chat_jid' => ['nullable', 'string'],
            'voter_jid' => ['nullable', 'string'],
            'selected_options' => ['nullable', 'array'],
            'selected_options.*' => ['string'],
        ]);

        $survey = WaCsatSurvey::where('device_id', $validated['device_id'])
            ->where('poll_message_id', $validated['poll_message_id'])
            ->first();

        if (! $survey) {
            return response()->json(['status' => 'no matching CSAT survey, ignored']);
        }

        $selected = $validated['selected_options'] ?? [];
        $chosenText = $selected[0] ?? null;

        if ($chosenText === null) {
            // An empty vote means the customer cleared their selection
            // (WhatsApp allows un-voting a poll) — leave the survey's
            // previous score/response as-is rather than erasing a real
            // answer over a retraction; the customer can always vote
            // again to change it.
            return response()->json(['status' => 'empty vote, ignored']);
        }

        $options = $survey->options ?? [];
        $index = array_search($chosenText, $options, true);

        if ($index === false) {
            // Shouldn't normally happen (the vote's text comes from
            // g_backend re-hashing this exact survey's own option list),
            // but a company editing companies.csat_question/options
            // between send and vote — or a vote racing a options schema
            // change — could in principle produce a mismatch. Recorded
            // as an unscored response rather than dropped entirely, so
            // it's still visible that a reply came in.
            Log::warning('csat-survey: vote text did not match any stored option', [
                'survey_id' => $survey->id,
                'chosen_text' => $chosenText,
            ]);

            $survey->forceFill([
                'selected_option' => $chosenText,
                'responded_at' => now(),
            ])->save();

            return response()->json(['status' => 'recorded (unscored)', 'survey_id' => $survey->id]);
        }

        $survey->forceFill([
            'score' => $index + 1,
            'selected_option' => $chosenText,
            'responded_at' => now(),
        ])->save();

        Log::info('csat-survey: response recorded', [
            'survey_id' => $survey->id,
            'company_id' => $survey->company_id,
            'score' => $index + 1,
        ]);

        return response()->json(['status' => 'recorded', 'survey_id' => $survey->id, 'score' => $index + 1]);
    }
}
