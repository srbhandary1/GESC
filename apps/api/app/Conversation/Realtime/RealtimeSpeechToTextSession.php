<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

interface RealtimeSpeechToTextSession
{
    public function pushAudio(string $pcm16leChunk): void;

    /** @return list<RealtimeSpeechEvent> */
    public function drain(): array;

    public function finalize(): RealtimeSpeechToTextResult;

    public function abort(): void;
}
