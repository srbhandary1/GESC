<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

interface RealtimeSpeechToTextProvider
{
    public function open(
        RealtimeSpeechToTextRequest $request,
    ): RealtimeSpeechToTextSession;
}
