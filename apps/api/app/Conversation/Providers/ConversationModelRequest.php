<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

final readonly class ConversationModelRequest
{
    /**
     * @param  list<string>  $memoryFacts
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
    ) {}
}
