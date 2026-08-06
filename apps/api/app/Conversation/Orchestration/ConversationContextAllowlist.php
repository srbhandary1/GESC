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
     * @return array{
     *   coaching_mode: string,
     *   persona_style_code: string,
     *   scenario_response_code: string,
     *   transcript: string,
     *   prompt_key: string,
     *   prompt_version: int,
     *   prompt_body_sha256: string
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
    ): array {
        return [
            'coaching_mode' => $coachingMode,
            'persona_style_code' => $personaStyleCode,
            'scenario_response_code' => $scenarioResponseCode,
            'transcript' => $transcript,
            'prompt_key' => $promptKey,
            'prompt_version' => $promptVersion,
            'prompt_body_sha256' => $promptBodySha256,
        ];
    }
}
