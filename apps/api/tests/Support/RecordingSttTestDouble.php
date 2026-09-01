<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;

final class RecordingSttTestDouble implements RecordedSpeechToTextProvider
{
    public int $calls = 0;

    public function __construct(
        private RecordedSpeechToTextResult|ConversationProviderException $outcome,
    ) {}

    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        unset($request);
        $this->calls++;

        if ($this->outcome instanceof ConversationProviderException) {
            throw $this->outcome;
        }

        return $this->outcome;
    }
}
