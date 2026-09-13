<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

/**
 * Normalizes learner-confirmed text into the existing turn transcript stage.
 *
 * Voice recordings are transcribed and confirmed through the separate
 * ADR-0007 recording lifecycle before their text reaches this provider.
 */
final class ConfirmedTextSpeechToTextProvider implements SpeechToTextProvider
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
                safeCode: 'confirmed_text_empty',
                providerKey: 'project-confirmed-text',
                modelVersion: 'confirmed-text-v1',
            );
        }

        return new SpeechToTextResult(
            transcript: $transcript,
            providerUsage: new ProviderUsage(
                providerKey: 'project-confirmed-text',
                modelVersion: 'confirmed-text-v1',
                latencyMilliseconds: 0,
                inputUnits: mb_strlen($request->prototypeUtterance),
                outputUnits: mb_strlen($transcript),
                estimatedCostMicrounits: 0,
                inputUnitKind: 'unicode_characters',
                outputUnitKind: 'unicode_characters',
                dispatchKind: 'local_execution',
            ),
        );
    }
}
