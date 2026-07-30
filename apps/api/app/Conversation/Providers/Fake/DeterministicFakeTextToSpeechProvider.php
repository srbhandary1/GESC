<?php

declare(strict_types=1);

namespace App\Conversation\Providers\Fake;

use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;

final class DeterministicFakeTextToSpeechProvider implements TextToSpeechProvider
{
    public function synthesize(
        TextToSpeechRequest $request,
    ): TextToSpeechResult {
        return new TextToSpeechResult(
            playbackAvailable: false,
            renderingMode: 'deterministic-fake-text-only',
            providerUsage: new ProviderUsage(
                providerKey: 'deterministic-fake',
                modelVersion: 'fake-tts-v1',
                latencyMilliseconds: 3,
                inputUnits: strlen($request->text),
                outputUnits: 0,
                estimatedCostMicrounits: 0,
            ),
        );
    }
}
