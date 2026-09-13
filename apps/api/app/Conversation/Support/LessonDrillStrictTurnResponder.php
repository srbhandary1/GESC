<?php

declare(strict_types=1);

namespace App\Conversation\Support;

use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ProviderUsage;

/**
 * Authoritative introduction/practice drill gate: advance only on a matching try.
 */
final readonly class LessonDrillStrictTurnResponder
{
    public const string INTENT_PATTERN_ACCEPTED = 'lesson_drill_pattern_accepted';

    public const string INTENT_PATTERN_RETRY = 'lesson_drill_pattern_retry';

    public const string INTENT_DRILL_COMPLETE = 'lesson_drill_complete';

    /**
     * @param  array<string, mixed>  $lessonDrill
     */
    public function applies(array $lessonDrill): bool
    {
        $partId = is_string($lessonDrill['part_id'] ?? null)
            ? (string) $lessonDrill['part_id']
            : '';

        if ($partId !== 'introduction' && $partId !== 'practice') {
            return false;
        }

        $patterns = $lessonDrill['target_patterns'] ?? [];

        return is_array($patterns) && $patterns !== [];
    }

    /**
     * @param  array<string, mixed>  $lessonDrill
     */
    public function respond(
        string $transcript,
        array $lessonDrill,
        string $uiLocale,
    ): ConversationModelResult {
        $partId = is_string($lessonDrill['part_id'] ?? null)
            ? (string) $lessonDrill['part_id']
            : 'introduction';
        if ($partId !== 'practice') {
            $partId = 'introduction';
        }

        $patterns = $this->stringList($lessonDrill['target_patterns'] ?? null);
        $fallbackTurns = $this->stringList($lessonDrill['fallback_turns'] ?? null);
        $acceptedCount = $lessonDrill['accepted_pattern_count'] ?? 0;
        $acceptedCount = is_int($acceptedCount)
            ? max(0, $acceptedCount)
            : max(0, (int) $acceptedCount);

        $models = LessonDrillStepper::models($patterns, $fallbackTurns);
        $currentModel = LessonDrillStepper::currentModel(
            $patterns,
            $acceptedCount,
            $fallbackTurns,
        );

        if ($currentModel === null) {
            return $this->result(
                intent: self::INTENT_DRILL_COMPLETE,
                reply: LessonDrillStepper::reply(
                    $partId,
                    $patterns,
                    max(0, count($models) - 1),
                    $fallbackTurns,
                ),
                correction: null,
                uiLocale: $uiLocale,
            );
        }

        if (LessonDrillStepper::utteranceMatchesModel($transcript, $currentModel)) {
            return $this->result(
                intent: self::INTENT_PATTERN_ACCEPTED,
                reply: LessonDrillStepper::reply(
                    $partId,
                    $patterns,
                    $acceptedCount,
                    $fallbackTurns,
                ),
                correction: new PriorityCorrection(
                    errorCategory: 'none',
                    correctionEn: 'none',
                    explanationL1: SyntheticL1Explanations::for(
                        SyntheticL1Explanations::drillPatternAccepted(),
                        $uiLocale,
                    ),
                    explanationLocale: $uiLocale,
                    retryRecommended: false,
                    needed: false,
                ),
                uiLocale: $uiLocale,
            );
        }

        return $this->result(
            intent: self::INTENT_PATTERN_RETRY,
            reply: LessonDrillStepper::retryReply($partId, $currentModel),
            correction: new PriorityCorrection(
                errorCategory: 'lesson.pattern_mismatch',
                correctionEn: $currentModel,
                explanationL1: SyntheticL1Explanations::for(
                    SyntheticL1Explanations::drillRepeatModel($currentModel),
                    $uiLocale,
                ),
                explanationLocale: $uiLocale,
                retryRecommended: true,
                learnerSpan: trim($transcript),
            ),
            uiLocale: $uiLocale,
        );
    }

    private function result(
        string $intent,
        string $reply,
        ?PriorityCorrection $correction,
        string $uiLocale,
    ): ConversationModelResult {
        return new ConversationModelResult(
            understoodIntent: $intent,
            assistantReply: $reply,
            providerUsage: new ProviderUsage(
                providerKey: 'project-lesson-drill',
                modelVersion: 'strict-pattern-gate-v1',
                latencyMilliseconds: 0,
                inputUnits: 0,
                outputUnits: 0,
                estimatedCostMicrounits: 0,
                inputUnitKind: 'unicode_characters',
                outputUnitKind: 'unicode_characters',
                dispatchKind: 'local_execution',
            ),
            priorityCorrection: $correction,
            safetyState: 'ok',
            usedFallback: false,
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                continue;
            }
            $items[] = trim($item);
        }

        return $items;
    }
}
