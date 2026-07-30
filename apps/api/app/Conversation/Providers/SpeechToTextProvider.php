<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

interface SpeechToTextProvider
{
    public function transcribe(
        SpeechToTextRequest $request,
    ): SpeechToTextResult;
}
