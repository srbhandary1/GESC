<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;

final class DeterministicFakeRealtimeSpeechToTextSession implements RealtimeSpeechToTextSession
{
    private bool $hasAudio = false;

    private bool $firstResultEmitted = false;

    private bool $aborted = false;

    private ?RealtimeSpeechToTextResult $final = null;

    /** @var list<RealtimeSpeechEvent> */
    private array $pending = [];

    public function __construct(
        private readonly RealtimeSpeechToTextRequest $request,
    ) {}

    public function pushAudio(string $pcm16leChunk): void
    {
        $this->assertOpen();
        if ($pcm16leChunk === '') {
            return;
        }

        $this->hasAudio = true;
        if (! $this->firstResultEmitted) {
            $this->firstResultEmitted = true;
            $this->pending[] = new RealtimeSpeechEvent(
                type: 'first_result',
                text: $this->transcript(),
            );
        }
    }

    public function drain(): array
    {
        $events = $this->pending;
        $this->pending = [];

        return $events;
    }

    public function finalize(): RealtimeSpeechToTextResult
    {
        $this->assertOpen();
        if ($this->final instanceof RealtimeSpeechToTextResult) {
            return $this->final;
        }

        if (! $this->hasAudio) {
            throw new ConversationProviderException(
                safeCode: 'fake_realtime_empty',
                providerKey: 'deterministic-fake-realtime',
                modelVersion: 'fake-stt-rt-v1',
            );
        }

        $transcript = $this->transcript();
        $this->pending[] = new RealtimeSpeechEvent(
            type: 'endpoint',
        );
        $this->pending[] = new RealtimeSpeechEvent(
            type: 'final',
            text: $transcript,
        );
        $this->final = new RealtimeSpeechToTextResult(
            transcript: $transcript,
            usage: new ProviderUsage(
                providerKey: 'deterministic-fake-realtime',
                modelVersion: 'fake-stt-rt-v1',
                latencyMilliseconds: 6,
                inputUnits: 1,
                outputUnits: strlen($transcript),
                estimatedCostMicrounits: 0,
                inputUnitKind: 'audio_frames',
                outputUnitKind: 'utf8_bytes',
                dispatchKind: 'local_execution',
                firstResultLatencyMilliseconds: 2,
            ),
            authoritative: false,
        );

        return $this->final;
    }

    public function abort(): void
    {
        $this->aborted = true;
        $this->pending = [];
    }

    private function transcript(): string
    {
        $text = preg_replace(
            '/\s+/u',
            ' ',
            trim((string) $this->request->prototypeUtterance),
        );

        return is_string($text) && $text !== '' ? $text : 'synthetic realtime';
    }

    private function assertOpen(): void
    {
        if ($this->aborted) {
            throw new ConversationProviderException(
                safeCode: 'fake_realtime_aborted',
                providerKey: 'deterministic-fake-realtime',
                modelVersion: 'fake-stt-rt-v1',
            );
        }
    }
}
