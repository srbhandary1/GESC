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
use App\Conversation\Support\LessonDrillStepper;
use App\Conversation\Support\SyntheticL1Explanations;

final class DeterministicFakeConversationModelProvider implements ConversationModelProvider
{
    public function respond(
        ConversationModelRequest $request,
    ): ConversationModelResult {
        if (
            $request->personaStyleCode !== 'patient-friend-v1'
            || ! in_array(
                $request->scenarioResponseCode,
                [
                    'friend.everyday.follow-up.v1',
                    'friend.skill-focus.past-simple.v1',
                    'friend.review.carry-sentence.v1',
                ],
                true,
            )
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
                    inputUnitKind: 'estimated_words',
                    outputUnitKind: 'estimated_words',
                    dispatchKind: 'local_execution',
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
                    inputUnitKind: 'estimated_words',
                    outputUnitKind: 'estimated_words',
                    dispatchKind: 'local_execution',
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

        $patterns = $request->lessonDrill['target_patterns'] ?? [];
        $listed = [];
        if (is_array($patterns)) {
            foreach ($patterns as $pattern) {
                if (! is_string($pattern) || trim($pattern) === '') {
                    continue;
                }
                $listed[] = trim($pattern);
            }
        }
        if ($listed !== []) {
            $partId = is_string($request->lessonDrill['part_id'] ?? null)
                ? (string) $request->lessonDrill['part_id']
                : 'introduction';
            if ($partId !== 'practice') {
                $partId = 'introduction';
            }
            $completedTurns = $request->lessonDrill['completed_turns'] ?? 0;
            $fallbackTurns = $request->lessonDrill['fallback_turns'] ?? [];
            $listedFallbacks = [];
            if (is_array($fallbackTurns)) {
                foreach ($fallbackTurns as $turn) {
                    if (! is_string($turn) || trim($turn) === '') {
                        continue;
                    }
                    $listedFallbacks[] = trim($turn);
                }
            }
            $reply = LessonDrillStepper::reply(
                $partId,
                $listed,
                is_int($completedTurns) ? $completedTurns : (int) $completedTurns,
                $listedFallbacks,
            );
        }

        if ($request->memoryFacts !== []) {
            $reply .= ' I will keep your shared practice notes in mind.';
        }

        $locale = $request->uiLocale;
        $correction = $listed !== []
            ? new PriorityCorrection(
                errorCategory: 'none',
                correctionEn: 'none',
                explanationL1: 'none',
                explanationLocale: $locale,
                retryRecommended: false,
                needed: false,
            )
            : match ($request->coachingMode) {
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
                inputUnitKind: 'estimated_words',
                outputUnitKind: 'estimated_words',
                dispatchKind: 'local_execution',
            ),
            priorityCorrection: $correction,
            safetyState: 'ok',
            usedFallback: false,
        );
    }
}
