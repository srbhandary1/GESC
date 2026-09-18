<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ElevenLabs\ElevenLabsTextToSpeechProvider;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\Sarvam\SarvamBulbulTextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Realtime\ElevenLabsScribeRealtimeSpeechToTextProvider;
use App\Conversation\Realtime\RealtimeSpeechToTextRequest;
use App\Conversation\Realtime\SarvamSaarasRealtimeSpeechToTextProvider;
use App\Conversation\Routing\PrimaryFallbackRecordedSpeechToTextProvider;
use App\Conversation\Routing\PrimaryFallbackTextToSpeechProvider;
use App\Conversation\Routing\SpeechDispatchAuthority;
use App\Conversation\Voice\Providers\ElevenLabs\ElevenLabsRecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;
use Illuminate\Support\Facades\Http;
use Tests\Support\RecordingSttTestDouble;
use Tests\TestCase;

final class SpeechProviderRoutingTest extends TestCase
{
    public function test_challenger_adapters_fail_closed_without_http(): void
    {
        Http::preventStrayRequests();

        $authority = $this->app->make(SpeechDispatchAuthority::class);
        $this->assertContains(
            'http_enablement_not_authorized',
            $authority->denialReasons('elevenlabs-tts', 'tts'),
        );
        $this->assertContains(
            'implementation_unavailable',
            $authority->denialReasons('sarvam-bulbul-tts', 'tts'),
        );

        foreach ([
            fn (): mixed => $this->app->make(ElevenLabsTextToSpeechProvider::class)
                ->synthesize($this->ttsRequest()),
            fn (): mixed => $this->app->make(SarvamBulbulTextToSpeechProvider::class)
                ->synthesize($this->ttsRequest()),
            fn (): mixed => $this->app->make(ElevenLabsRecordedSpeechToTextProvider::class)
                ->transcribe($this->sttRequest()),
            fn (): mixed => $this->app->make(ElevenLabsScribeRealtimeSpeechToTextProvider::class)
                ->open($this->realtimeRequest()),
            fn (): mixed => $this->app->make(SarvamSaarasRealtimeSpeechToTextProvider::class)
                ->open($this->realtimeRequest()),
        ] as $dispatch) {
            try {
                $dispatch();
                self::fail('Expected challenger adapters to stay fail-closed.');
            } catch (ConversationProviderException $exception) {
                $this->assertSame('provider_not_authorized', $exception->safeCode);
            }
        }

        Http::assertNothingSent();
    }

    public function test_primary_fallback_router_caps_recorded_stt_at_two_dispatches(): void
    {
        $primary = new RecordingSttTestDouble(new ConversationProviderException(
            safeCode: 'provider_http_failure',
            providerKey: 'primary-stt',
            modelVersion: 'test',
        ));
        $fallback = new RecordingSttTestDouble($this->sttResult('fallback'));

        $result = (new PrimaryFallbackRecordedSpeechToTextProvider(
            $primary,
            $fallback,
        ))->transcribe($this->sttRequest());

        $this->assertSame('fallback heard this.', $result->transcript);
        $this->assertSame(1, $primary->calls);
        $this->assertSame(1, $fallback->calls);
    }

    public function test_tts_router_does_not_fallback_on_authorization_failure(): void
    {
        $primary = new class implements TextToSpeechProvider
        {
            public int $calls = 0;

            public function synthesize(TextToSpeechRequest $request): TextToSpeechResult
            {
                $this->calls++;
                throw new ConversationProviderException(
                    safeCode: 'provider_not_authorized',
                    providerKey: 'elevenlabs-tts',
                    modelVersion: 'blocked',
                );
            }
        };
        $fallback = new class implements TextToSpeechProvider
        {
            public int $calls = 0;

            public function synthesize(TextToSpeechRequest $request): TextToSpeechResult
            {
                $this->calls++;

                return new TextToSpeechResult(
                    playbackAvailable: false,
                    renderingMode: 'test',
                    providerUsage: new ProviderUsage(
                        providerKey: 'fallback-tts',
                        modelVersion: 'test',
                        latencyMilliseconds: 1,
                        inputUnits: 1,
                        outputUnits: 0,
                        estimatedCostMicrounits: 0,
                        dispatchKind: 'local_execution',
                    ),
                );
            }
        };

        try {
            (new PrimaryFallbackTextToSpeechProvider($primary, $fallback))
                ->synthesize($this->ttsRequest());
            self::fail('Authorization failures must not open a fallback dispatch.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }

        $this->assertSame(1, $primary->calls);
        $this->assertSame(0, $fallback->calls);
    }

    private function ttsRequest(): TextToSpeechRequest
    {
        return new TextToSpeechRequest(
            interactionId: '01J00000000000000000000001',
            text: 'Hello.',
        );
    }

    private function realtimeRequest(): RealtimeSpeechToTextRequest
    {
        return new RealtimeSpeechToTextRequest(
            interactionId: '01J00000000000000000000001',
        );
    }

    private function sttRequest(): RecordedSpeechToTextRequest
    {
        return new RecordedSpeechToTextRequest(
            interactionId: '01J00000000000000000000001',
            audioBytes: 'wav',
            mimeType: 'audio/wav',
            language: 'en-IN',
        );
    }

    private function sttResult(string $source): RecordedSpeechToTextResult
    {
        return new RecordedSpeechToTextResult(
            transcript: $source.' heard this.',
            confidenceBps: 9_000,
            usage: new ProviderUsage(
                providerKey: $source.'-stt',
                modelVersion: 'test',
                latencyMilliseconds: 10,
                inputUnits: 1000,
                outputUnits: 4,
                estimatedCostMicrounits: 0,
                dispatchKind: 'local_execution',
            ),
        );
    }
}
