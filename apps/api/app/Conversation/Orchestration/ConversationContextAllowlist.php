<?php

declare(strict_types=1);

namespace App\Conversation\Orchestration;

/**
 * Builds the only context fields allowed into the model prompt path.
 * Personal identifiers and authoritative learning records are excluded.
 */
final class ConversationContextAllowlist
{
    /**
     * @param  list<string>  $memoryFacts
     * @return array{
     *   coaching_mode: string,
     *   persona_style_code: string,
     *   scenario_response_code: string,
     *   transcript: string,
     *   prompt_key: string,
     *   prompt_version: int,
     *   prompt_body_sha256: string,
     *   memory_facts: list<string>
     * }
     */
    public function build(
        string $transcript,
        string $personaStyleCode,
        string $scenarioResponseCode,
        string $coachingMode,
        string $promptKey,
        int $promptVersion,
        string $promptBodySha256,
        array $memoryFacts = [],
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
            'persona_style_code' => $personaStyleCode,
            'scenario_response_code' => $scenarioResponseCode,
            'transcript' => $transcript,
            'prompt_key' => $promptKey,
            'prompt_version' => $promptVersion,
            'prompt_body_sha256' => $promptBodySha256,
            'memory_facts' => $bounded,
        ];
    }
}
