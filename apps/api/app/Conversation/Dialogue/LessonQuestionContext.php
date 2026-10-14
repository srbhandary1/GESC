<?php

declare(strict_types=1);

namespace App\Conversation\Dialogue;

use App\Models\LessonPracticeBlueprint;

/** Constructed only after level/part authorization; never from client lesson text. */
final readonly class LessonQuestionContext
{
    /** @param array<string, mixed> $content */
    private function __construct(public array $content) {}

    public static function fromBlueprint(LessonPracticeBlueprint $blueprint, int $level, string $part): self
    {
        $canonical = $blueprint->canonical_json;
        $lesson = $canonical['lesson'] ?? [];
        $talk = $canonical['talk'] ?? [];

        return new self([
            'level_number' => $level, 'part_id' => $part,
            'blueprint_id' => (string) $blueprint->getKey(),
            'blueprint_content_hash' => (string) $blueprint->content_hash,
            'concept_key' => (string) $blueprint->concept_key,
            'objective' => $lesson['objective'] ?? '',
            'communication_function' => $lesson['communication_function'] ?? '',
            'target_patterns' => $lesson['target_patterns'] ?? [],
            'examples' => $talk['fallback_turns'] ?? [],
            'scenario' => $talk['scenario'] ?? '',
        ]);
    }
}
