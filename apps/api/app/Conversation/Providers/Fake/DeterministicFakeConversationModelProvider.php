<?php

declare(strict_types=1);

namespace App\Conversation\Providers\Fake;

use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;

final class DeterministicFakeConversationModelProvider implements ConversationModelProvider
{
    public function respond(
        ConversationModelRequest $request,
    ): ConversationModelResult {
        if (
            $request->personaStyleCode !== 'patient-friend-v1'
            || $request->scenarioResponseCode
                !== 'friend.everyday.follow-up.v1'
            || ! in_array(
                $request->coachingMode,
                ['gentle', 'tutor', 'fluency'],
                true,
            )
            || $request->promptVersionId === ''
            || ! preg_match('/\A[0-9a-f]{64}\z/', $request->promptBodySha256)
        ) {
            throw new ConversationProviderException(
                safeCode: 'fake_model_contract_mismatch',
                providerKey: 'deterministic-fake',
                modelVersion: 'fake-conversation-v1',
            );
        }

        if (str_contains(mb_strtolower($request->transcript), 'unsafe-fixture')) {
            return new ConversationModelResult(
                understoodIntent: 'unsafe',
                assistantReply: '<script>bad</script>',
                providerUsage: new ProviderUsage(
                    providerKey: 'deterministic-fake',
                    modelVersion: 'fake-conversation-v1',
                    latencyMilliseconds: 7,
                    inputUnits: str_word_count($request->transcript),
                    outputUnits: 1,
                    estimatedCostMicrounits: 0,
                ),
            );
        }

        $reply = match ($request->coachingMode) {
            'tutor' => 'Good try. Keep the conversation going with one clear next idea.',
            'fluency' => 'Nice flow. What happened after that?',
            default => 'Thank you for sharing that. What would you like to talk about next?',
        };
        $correction = match ($request->coachingMode) {
            'tutor' => new PriorityCorrection(
                errorCategory: 'grammar.article',
                correctionEn: 'Say: "I enjoy learning English after work."',
                explanationKn: 'ಹೀಗೆ ಹೇಳಿ: "I enjoy learning English after work."',
                retryRecommended: true,
            ),
            'fluency' => new PriorityCorrection(
                errorCategory: 'fluency.pacing',
                correctionEn: 'Keep one idea per sentence so the flow stays smooth.',
                explanationKn: 'ಹರಿವು ಸುಗಮವಾಗಿರಲು ಒಂದು ವಾಕ್ಯದಲ್ಲಿ ಒಂದೇ ಆಲೋಚನೆ ಇರಿಸಿ.',
                retryRecommended: false,
            ),
            default => new PriorityCorrection(
                errorCategory: 'grammar.article',
                correctionEn: 'A clearer sentence is: "I enjoy learning English after work."',
                explanationKn: 'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
                retryRecommended: false,
            ),
        };

        return new ConversationModelResult(
            understoodIntent: 'prototype_utterance_received',
            assistantReply: $reply,
            providerUsage: new ProviderUsage(
                providerKey: 'deterministic-fake',
                modelVersion: 'fake-conversation-v1',
                latencyMilliseconds: 7,
                inputUnits: str_word_count($request->transcript),
                outputUnits: str_word_count($reply),
                estimatedCostMicrounits: 0,
            ),
            priorityCorrection: $correction,
            safetyState: 'ok',
            usedFallback: false,
        );
    }
}
