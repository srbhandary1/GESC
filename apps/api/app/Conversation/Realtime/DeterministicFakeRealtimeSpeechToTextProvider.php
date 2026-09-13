<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

final class DeterministicFakeRealtimeSpeechToTextProvider implements RealtimeSpeechToTextProvider
{
    public function open(
        RealtimeSpeechToTextRequest $request,
    ): RealtimeSpeechToTextSession {
        return new DeterministicFakeRealtimeSpeechToTextSession($request);
    }
}
