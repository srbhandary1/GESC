<?php

declare(strict_types=1);

namespace App\Conversation;

final readonly class LessonDrillContext
{
    public const array DRILL_PARTS = ['introduction', 'practice'];

    /**
     * @param  list<string>  $targetPatterns
     * @param  list<string>  $elicitationGoals
     * @param  list<string>  $fallbackTurns
     */
    public function __construct(
        public int $levelNumber,
        public string $partId,
        public string $conceptKey,
        public string $communicationFunction,
        public string $talkScenario,
        public array $targetPatterns,
        public array $elicitationGoals,
        public array $fallbackTurns = [],
    ) {}

    /**
     * @param  array<string, mixed>  $canonical
     */
    public static function fromCanonical(
        int $levelNumber,
        string $partId,
        string $conceptKey,
        array $canonical,
    ): ?self {
        if (! in_array($partId, self::DRILL_PARTS, true)) {
            return null;
        }

        $lesson = is_array($canonical['lesson'] ?? null) ? $canonical['lesson'] : [];
        $talk = is_array($canonical['talk'] ?? null) ? $canonical['talk'] : [];
        $patterns = self::stringList($lesson['target_patterns'] ?? null);
        if ($patterns === []) {
            return null;
        }

        $resolvedConcept = is_string($lesson['concept_key'] ?? null)
            ? (string) $lesson['concept_key']
            : $conceptKey;
        if ($resolvedConcept === '') {
            return null;
        }

        return new self(
            levelNumber: $levelNumber,
            partId: $partId,
            conceptKey: $resolvedConcept,
            communicationFunction: is_string($lesson['communication_function'] ?? null)
                ? (string) $lesson['communication_function']
                : '',
            talkScenario: is_string($talk['scenario'] ?? null)
                ? (string) $talk['scenario']
                : '',
            targetPatterns: $patterns,
            elicitationGoals: self::stringList($talk['elicitation_goals'] ?? null),
            fallbackTurns: self::stringList($talk['fallback_turns'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function fingerprint(): array
    {
        return [
            'concept_key' => $this->conceptKey,
            'level_number' => $this->levelNumber,
            'part_id' => $this->partId,
            'target_patterns' => $this->targetPatterns,
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }
            $trimmed = trim($item);
            if ($trimmed === '') {
                continue;
            }
            $items[] = $trimmed;
        }

        return $items;
    }
}
