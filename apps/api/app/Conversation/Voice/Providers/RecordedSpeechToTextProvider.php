<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers;

interface RecordedSpeechToTextProvider
{
    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult;
}
