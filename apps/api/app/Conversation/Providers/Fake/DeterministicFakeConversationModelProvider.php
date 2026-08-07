<?php

declare(strict_types=1);

namespace App\Conversation\Providers\Fake;

use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Support\ConversationUiLocales;
use App\Conversation\Support\SyntheticL1Explanations;

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
            || ! ConversationUiLocales::isSupported($request->uiLocale)
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

        if (str_contains(mb_strtolower($request->transcript), 'shame-fixture')) {
            return new ConversationModelResult(
                understoodIntent: 'prototype_utterance_received',
                assistantReply: 'That was stupid bad English.',
                providerUsage: new ProviderUsage(
                    providerKey: 'deterministic-fake',
                    modelVersion: 'fake-conversation-v1',
                    latencyMilliseconds: 7,
                    inputUnits: str_word_count($request->transcript),
                    outputUnits: 4,
                    estimatedCostMicrounits: 0,
                ),
                priorityCorrection: new PriorityCorrection(
                    errorCategory: 'grammar.article',
                    correctionEn: 'Try a clearer sentence.',
                    explanationL1: SyntheticL1Explanations::for(
                        SyntheticL1Explanations::gentleClearerSentence(),
                        $request->uiLocale,
                    ),
                    explanationLocale: $request->uiLocale,
                    retryRecommended: false,
                ),
                safetyState: 'ok',
                usedFallback: false,
            );
        }

        $reply = match ($request->coachingMode) {
            'tutor' => 'Good try. Keep the conversation going with one clear next idea.',
            'fluency' => 'Nice flow. What happened after that?',
            default => 'Thank you for sharing that. What would you like to talk about next?',
        };

        if ($request->memoryFacts !== []) {
            $reply .= ' I will keep your shared practice notes in mind.';
        }

        $locale = $request->uiLocale;
        $correction = match ($request->coachingMode) {
            'tutor' => new PriorityCorrection(
                errorCategory: 'grammar.article',
                correctionEn: 'Say: "I enjoy learning English after work."',
                explanationL1: SyntheticL1Explanations::for(
                    SyntheticL1Explanations::tutorSaySentence(),
                    $locale,
                ),
                explanationLocale: $locale,
                retryRecommended: true,
            ),
            'fluency' => new PriorityCorrection(
                errorCategory: 'fluency.pacing',
                correctionEn: 'Keep one idea per sentence so the flow stays smooth.',
                explanationL1: SyntheticL1Explanations::for(
                    SyntheticL1Explanations::fluencyOneIdea(),
                    $locale,
                ),
                explanationLocale: $locale,
                retryRecommended: false,
            ),
            default => new PriorityCorrection(
                errorCategory: 'grammar.article',
                correctionEn: 'A clearer sentence is: "I enjoy learning English after work."',
                explanationL1: SyntheticL1Explanations::for(
                    SyntheticL1Explanations::gentleClearerSentence(),
                    $locale,
                ),
                explanationLocale: $locale,
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
