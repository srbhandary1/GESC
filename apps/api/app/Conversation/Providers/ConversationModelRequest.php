<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

final readonly class ConversationModelRequest
{
    /**
     * @param  list<string>  $memoryFacts
     * @param  array<string, mixed>  $lessonDrill
     * @param  array<string, mixed>  $dialogue
     */
    public function __construct(
        public string $interactionId,
        public string $transcript,
        public string $personaStyleCode,
        public string $scenarioResponseCode,
        public string $coachingMode,
        public string $uiLocale,
        public string $promptVersionId,
        public string $promptBodySha256,
        public string $promptBody = '',
        public array $memoryFacts = [],
        public array $lessonDrill = [],
        public array $dialogue = [],
    ) {}
}
