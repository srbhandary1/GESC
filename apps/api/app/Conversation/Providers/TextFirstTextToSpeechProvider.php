<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

use App\Conversation\Support\GuidanceTtsPolicy;

/**
 * Explicit ordinary-product fallback while live Azure Speech DI is gated.
 *
 * The client keeps text primary and may use reviewed on-device playback. This
 * adapter performs no provider request and never pretends audio was generated.
 */
final class TextFirstTextToSpeechProvider implements TextToSpeechProvider
{
    public function synthesize(
        TextToSpeechRequest $request,
    ): TextToSpeechResult {
        if (
            trim($request->text) === ''
            || ! in_array($request->purpose, GuidanceTtsPolicy::purposes(), true)
            || ! GuidanceTtsPolicy::isAllowlistedVoice($request->voice)
            || $request->voice !== GuidanceTtsPolicy::voiceFor(
                $request->purpose,
                $request->uiLocale,
            )
        ) {
            throw new ConversationProviderException(
                safeCode: 'text_first_contract_mismatch',
                providerKey: 'project-text-first',
                modelVersion: 'text-first-v1',
            );
        }

        return new TextToSpeechResult(
            playbackAvailable: false,
            renderingMode: 'client-text-to-speech-fallback',
            providerUsage: new ProviderUsage(
                providerKey: 'project-text-first',
                modelVersion: 'text-first-v1',
                latencyMilliseconds: 0,
                inputUnits: mb_strlen($request->text),
                outputUnits: 0,
                estimatedCostMicrounits: 0,
            ),
        );
    }
}
