<?php

declare(strict_types=1);

namespace App\Conversation\Providers\Fake;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\SpeechToTextProvider;
use App\Conversation\Providers\SpeechToTextRequest;
use App\Conversation\Providers\SpeechToTextResult;

final class DeterministicFakeSpeechToTextProvider implements SpeechToTextProvider
{
    public function transcribe(
        SpeechToTextRequest $request,
    ): SpeechToTextResult {
        $transcript = preg_replace(
            '/\s+/u',
            ' ',
            trim($request->prototypeUtterance),
        );

        if (! is_string($transcript) || $transcript === '') {
            throw new ConversationProviderException(
                safeCode: 'fake_stt_empty',
                providerKey: 'deterministic-fake',
                modelVersion: 'fake-stt-v1',
            );
        }

        return new SpeechToTextResult(
            transcript: $transcript,
            providerUsage: new ProviderUsage(
                providerKey: 'deterministic-fake',
                modelVersion: 'fake-stt-v1',
                latencyMilliseconds: 4,
                inputUnits: strlen($request->prototypeUtterance),
                outputUnits: strlen($transcript),
                estimatedCostMicrounits: 0,
                inputUnitKind: 'utf8_bytes',
                outputUnitKind: 'utf8_bytes',
                dispatchKind: 'local_execution',
            ),
        );
    }
}
