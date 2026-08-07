<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\Fake\DeterministicFakeTextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Support\GuidanceTtsPolicy;
use Tests\TestCase;

final class DeterministicFakeTextToSpeechProviderTest extends TestCase
{
    public function test_accepts_allowlisted_model_en_voice(): void
    {
        $result = (new DeterministicFakeTextToSpeechProvider)->synthesize(
            new TextToSpeechRequest(
                interactionId: 'tts-1',
                text: 'I practise English after work.',
                voice: GuidanceTtsPolicy::voiceFor(
                    GuidanceTtsPolicy::PURPOSE_MODEL_EN,
                    'kn-IN',
                ),
                purpose: GuidanceTtsPolicy::PURPOSE_MODEL_EN,
                uiLocale: 'kn-IN',
            ),
        );

        $this->assertFalse($result->playbackAvailable);
        $this->assertSame('deterministic-fake-text-only', $result->renderingMode);
    }

    public function test_rejects_voice_mismatch_for_purpose(): void
    {
        $this->expectException(ConversationProviderException::class);

        (new DeterministicFakeTextToSpeechProvider)->synthesize(
            new TextToSpeechRequest(
                interactionId: 'tts-2',
                text: 'Hello',
                voice: 'kn-IN-SapnaNeural',
                purpose: GuidanceTtsPolicy::PURPOSE_MODEL_EN,
                uiLocale: 'kn-IN',
            ),
        );
    }
}
