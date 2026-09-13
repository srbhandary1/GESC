<?php

declare(strict_types=1);

namespace App\Conversation\Orchestration;

/**
 * Builds the only context fields allowed into the model prompt path.
 * Personal identifiers and authoritative learning records are excluded.
 * Lesson drill copy is published curriculum text, not personal data.
 */
final class ConversationContextAllowlist
{
    /**
     * @param  list<string>  $memoryFacts
     * @param  array<string, mixed>  $lessonDrill
     * @return array{
     *   coaching_mode: string,
     *   ui_locale: string,
     *   persona_style_code: string,
     *   scenario_response_code: string,
     *   transcript: string,
     *   prompt_key: string,
     *   prompt_version: int,
     *   prompt_body_sha256: string,
     *   memory_facts: list<string>,
     *   lesson_drill: array<string, mixed>
     * }
     */
    public function build(
        string $transcript,
        string $personaStyleCode,
        string $scenarioResponseCode,
        string $coachingMode,
        string $uiLocale,
        string $promptKey,
        int $promptVersion,
        string $promptBodySha256,
        array $memoryFacts = [],
        array $lessonDrill = [],
    ): array {
        $maximum = max(
            0,
            (int) config('memory.maximum_active_facts', 5),
        );
        $maximumCharacters = max(
            1,
            (int) config('memory.maximum_fact_characters', 200),
        );
        $bounded = [];

        foreach ($memoryFacts as $fact) {
            $trimmed = trim($fact);

            if (
                $trimmed === ''
                || mb_strlen($trimmed) > $maximumCharacters
                || str_contains($trimmed, '<')
                || str_contains($trimmed, '>')
            ) {
                continue;
            }

            $bounded[] = $trimmed;

            if (count($bounded) >= $maximum) {
                break;
            }
        }

        return [
            'coaching_mode' => $coachingMode,
            'ui_locale' => $uiLocale,
            'persona_style_code' => $personaStyleCode,
            'scenario_response_code' => $scenarioResponseCode,
            'transcript' => $transcript,
            'prompt_key' => $promptKey,
            'prompt_version' => $promptVersion,
            'prompt_body_sha256' => $promptBodySha256,
            'memory_facts' => $bounded,
            'lesson_drill' => $this->boundedLessonDrill($lessonDrill),
        ];
    }

    /**
     * @param  array<string, mixed>  $lessonDrill
     * @return array<string, mixed>
     */
    private function boundedLessonDrill(array $lessonDrill): array
    {
        $conceptKey = $this->token($lessonDrill['concept_key'] ?? null, 64);
        $patterns = $this->boundedStrings($lessonDrill['target_patterns'] ?? null, 8, 120);
        if ($conceptKey === '' || $patterns === []) {
            return [];
        }

        return [
            'concept_key' => $conceptKey,
            'part_id' => $this->token($lessonDrill['part_id'] ?? null, 32),
            'accepted_pattern_count' => $this->boundedInt(
                $lessonDrill['accepted_pattern_count'] ?? 0,
                0,
                24,
            ),
            'completed_turns' => $this->boundedInt(
                $lessonDrill['completed_turns'] ?? 0,
                0,
                24,
            ),
            'communication_function' => $this->token(
                $lessonDrill['communication_function'] ?? null,
                80,
            ),
            'talk_scenario' => $this->plainText($lessonDrill['talk_scenario'] ?? null, 400),
            'target_patterns' => $patterns,
            'elicitation_goals' => $this->boundedStrings(
                $lessonDrill['elicitation_goals'] ?? null,
                6,
                160,
            ),
            'fallback_turns' => $this->boundedStrings(
                $lessonDrill['fallback_turns'] ?? null,
                6,
                160,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function boundedStrings(mixed $value, int $maximumItems, int $maximumCharacters): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $text = $this->plainText($item, $maximumCharacters);
            if ($text === '') {
                continue;
            }
            $items[] = $text;
            if (count($items) >= $maximumItems) {
                break;
            }
        }

        return $items;
    }

    private function token(mixed $value, int $maximumCharacters): string
    {
        if (! is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if (
            $trimmed === ''
            || mb_strlen($trimmed) > $maximumCharacters
            || preg_match('/\A[a-z0-9][a-z0-9_]{0,62}\z/', $trimmed) !== 1
        ) {
            return '';
        }

        return $trimmed;
    }

    private function plainText(mixed $value, int $maximumCharacters): string
    {
        if (! is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if (
            $trimmed === ''
            || mb_strlen($trimmed) > $maximumCharacters
            || str_contains($trimmed, '<')
            || str_contains($trimmed, '>')
        ) {
            return '';
        }

        return $trimmed;
    }

    private function boundedInt(mixed $value, int $minimum, int $maximum): int
    {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && is_numeric($value)) {
            $number = (int) $value;
        } else {
            return $minimum;
        }

        return max($minimum, min($maximum, $number));
    }
}
