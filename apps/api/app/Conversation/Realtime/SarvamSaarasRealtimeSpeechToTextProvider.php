<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

use App\Conversation\Routing\SpeechDispatchAuthority;

final readonly class SarvamSaarasRealtimeSpeechToTextProvider implements RealtimeSpeechToTextProvider
{
    public function __construct(
        private SpeechDispatchAuthority $authority,
    ) {}

    public function open(
        RealtimeSpeechToTextRequest $request,
    ): RealtimeSpeechToTextSession {
        $this->authority->assertDispatchAllowed('sarvam-saaras-realtime', 'stt');
    }
}
