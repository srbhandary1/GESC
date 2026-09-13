<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers\ElevenLabs;

use App\Conversation\Routing\SpeechDispatchAuthority;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;

final readonly class ElevenLabsRecordedSpeechToTextProvider implements RecordedSpeechToTextProvider
{
    public function __construct(
        private SpeechDispatchAuthority $authority,
    ) {}

    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        $this->authority->assertDispatchAllowed('elevenlabs-stt', 'stt');
    }
}
