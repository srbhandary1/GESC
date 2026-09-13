<?php

declare(strict_types=1);

namespace App\Conversation\Speech;

final readonly class SynthesizeDeferredGuidanceSpeech
{
    public function __construct(
        public string $organizationId,
        public string $membershipId,
        public string $sessionId,
        public string $turnId,
        public string $guidanceText,
        public string $uiLocale,
    ) {}

    public function handle(ConversationGuidanceDeferredSynthesizer $synthesizer): void
    {
        $synthesizer->synthesize(
            $this->organizationId,
            $this->membershipId,
            $this->sessionId,
            $this->turnId,
            $this->guidanceText,
            $this->uiLocale,
        );
    }
}
