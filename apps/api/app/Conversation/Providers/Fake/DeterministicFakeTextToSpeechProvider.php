<?php

declare(strict_types=1);

namespace App\Conversation\Providers\Fake;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Support\GuidanceTtsPolicy;

final class DeterministicFakeTextToSpeechProvider implements TextToSpeechProvider
{
    public function synthesize(
        TextToSpeechRequest $request,
    ): TextToSpeechResult {
        if (
            ! in_array($request->purpose, GuidanceTtsPolicy::purposes(), true)
            || ! GuidanceTtsPolicy::isAllowlistedVoice($request->voice)
            || trim($request->text) === ''
        ) {
            throw new ConversationProviderException(
                safeCode: 'fake_tts_contract_mismatch',
                providerKey: 'deterministic-fake',
                modelVersion: 'fake-tts-v1',
            );
        }

        $expected = GuidanceTtsPolicy::voiceFor(
            $request->purpose,
            $request->uiLocale,
        );
        if ($request->voice !== $expected) {
            throw new ConversationProviderException(
                safeCode: 'fake_tts_voice_mismatch',
                providerKey: 'deterministic-fake',
                modelVersion: 'fake-tts-v1',
            );
        }

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
