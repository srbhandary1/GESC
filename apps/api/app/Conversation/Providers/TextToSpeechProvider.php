<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

interface TextToSpeechProvider
{
    public function synthesize(
        TextToSpeechRequest $request,
    ): TextToSpeechResult;
}
