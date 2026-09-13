<?php

declare(strict_types=1);

namespace App\Conversation\Support;

/**
 * Soft-launch / development Role-play catalog (clinic + common scenarios).
 *
 * Entries are synthetic development-only prototypes until curriculum review.
 */
final class RoleplayCatalog
{
    /**
     * @return list<array{
     *   id: string,
     *   display_name: string,
     *   persona_key: string,
     *   scenario_key: string,
     *   topic_code: string,
     *   style_code: string,
     *   response_code: string,
     *   objectives: list<array{
     *     objective_key: string,
     *     title: string,
     *     completion_criteria_code: string,
     *     sort_order: int
     *   }>,
     *   pedagogy: array{
     *     observe_model: list<string>,
     *     rehearse_chunks: list<string>,
     *     perform_prompt: string
     *   }|null
     * }>
     */
    public static function entries(): array
    {
        $configured = config('conversation.roleplay_catalog', []);

        if (! is_array($configured) || $configured === []) {
            return [];
        }

        $entries = [];

        foreach ($configured as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $id = $entry['id'] ?? null;
            $displayName = $entry['display_name'] ?? null;
            $personaKey = $entry['persona_key'] ?? null;
            $scenarioKey = $entry['scenario_key'] ?? null;
            $topicCode = $entry['topic_code'] ?? null;
            $styleCode = $entry['style_code'] ?? null;
            $responseCode = $entry['response_code'] ?? null;
            $objectives = $entry['objectives'] ?? null;

            if (
                ! is_string($id)
                || ! is_string($displayName)
                || ! is_string($personaKey)
                || ! is_string($scenarioKey)
                || ! is_string($topicCode)
                || ! is_string($styleCode)
                || ! is_string($responseCode)
                || ! is_array($objectives)
                || $objectives === []
            ) {
                continue;
            }

            $normalizedObjectives = [];

            foreach ($objectives as $objective) {
                if (
                    ! is_array($objective)
                    || ! is_string($objective['objective_key'] ?? null)
                    || ! is_string($objective['title'] ?? null)
                    || ! is_string($objective['completion_criteria_code'] ?? null)
                    || ! is_int($objective['sort_order'] ?? null)
                ) {
                    continue 2;
                }

                $normalizedObjectives[] = [
                    'objective_key' => $objective['objective_key'],
                    'title' => $objective['title'],
                    'completion_criteria_code' => $objective['completion_criteria_code'],
                    'sort_order' => $objective['sort_order'],
                ];
            }

            $entries[] = [
                'id' => $id,
                'display_name' => $displayName,
                'persona_key' => $personaKey,
                'scenario_key' => $scenarioKey,
                'topic_code' => $topicCode,
                'style_code' => $styleCode,
                'response_code' => $responseCode,
                'objectives' => $normalizedObjectives,
                'pedagogy' => RoleplayPedagogy::for($id),
            ];
        }

        return $entries;
    }

    public static function modeFor(string $personaKey, string $scenarioKey): ?string
    {
        foreach (self::entries() as $entry) {
            if (
                $entry['persona_key'] === $personaKey
                && $entry['scenario_key'] === $scenarioKey
            ) {
                return 'roleplay';
            }
        }

        return null;
    }
}
