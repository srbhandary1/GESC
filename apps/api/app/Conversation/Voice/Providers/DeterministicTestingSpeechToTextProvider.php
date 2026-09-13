<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers;

use App\Conversation\Providers\ProviderUsage;

final class DeterministicTestingSpeechToTextProvider implements RecordedSpeechToTextProvider
{
    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        return new RecordedSpeechToTextResult(
            transcript: 'I practise English every day.',
            confidenceBps: 8700,
            usage: new ProviderUsage(
                providerKey: 'deterministic-test',
                modelVersion: 'test-stt-v1',
                latencyMilliseconds: 7,
                inputUnits: strlen($request->audioBytes),
                outputUnits: 31,
                estimatedCostMicrounits: 0,
                inputUnitKind: 'audio_bytes',
                outputUnitKind: 'unicode_characters',
                dispatchKind: 'local_execution',
            ),
        );
    }
}
