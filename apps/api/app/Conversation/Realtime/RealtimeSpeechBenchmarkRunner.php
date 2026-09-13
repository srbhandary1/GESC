<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\Fake\DeterministicFakeSpeechToTextProvider;
use App\Conversation\Providers\SpeechToTextRequest;
use App\Conversation\Routing\SpeechDispatchAuthority;
use Illuminate\Support\Facades\Http;

final readonly class RealtimeSpeechBenchmarkRunner
{
    public function __construct(
        private DeterministicFakeSpeechToTextProvider $recorded,
        private DeterministicFakeRealtimeSpeechToTextProvider $realtime,
        private ElevenLabsScribeRealtimeSpeechToTextProvider $elevenLabs,
        private SarvamSaarasRealtimeSpeechToTextProvider $sarvam,
        private SpeechDispatchAuthority $authority,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        Http::preventStrayRequests();
        $utterance = 'I practise English after work.';
        $recorded = $this->recorded->transcribe(new SpeechToTextRequest(
            interactionId: '01J00000000000000000000001',
            prototypeUtterance: $utterance,
        ));
        $session = $this->realtime->open(new RealtimeSpeechToTextRequest(
            interactionId: '01J00000000000000000000002',
            prototypeUtterance: $utterance,
        ));
        $session->pushAudio(str_repeat("\0", 320));
        $events = $session->drain();
        $final = $session->finalize();
        $replay = $session->finalize();
        $session->abort();

        $vendorDenied = 0;
        foreach ([$this->sarvam, $this->elevenLabs] as $challenger) {
            try {
                $challenger->open(new RealtimeSpeechToTextRequest(
                    interactionId: '01J00000000000000000000003',
                ));
            } catch (ConversationProviderException $exception) {
                if ($exception->safeCode === 'provider_not_authorized') {
                    $vendorDenied++;
                }
            }
        }

        Http::assertNothingSent();

        return [
            'status' => 'completed',
            'http_sent' => false,
            'live_dispatch' => 'denied',
            'recovery_route' => 'recorded_upload',
            'always_on' => false,
            'full_duplex' => false,
            'authoritative_turn_required' => true,
            'recorded_finish_ms' => $recorded->usage()->latencyMilliseconds,
            'realtime_first_result_ms' => $final->usage->firstResultLatencyMilliseconds,
            'realtime_finish_ms' => $final->usage->latencyMilliseconds,
            'first_result_emitted' => $events[0]->type ?? null,
            'transcripts_match' => hash_equals($recorded->transcript, $final->transcript),
            'duplicate_finalize_suppressed' => $replay === $final
                || hash_equals($replay->transcript, $final->transcript),
            'vendor_candidates_fail_closed' => $vendorDenied === 2,
            'sarvam_denial' => $this->authority->denialReasons(
                'sarvam-saaras-realtime',
                'stt',
            ),
            'elevenlabs_denial' => $this->authority->denialReasons(
                'elevenlabs-stt-realtime',
                'stt',
            ),
        ];
    }
}
