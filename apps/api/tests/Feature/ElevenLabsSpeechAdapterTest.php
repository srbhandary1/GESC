<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ElevenLabs\ElevenLabsTextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Voice\Providers\ElevenLabs\ElevenLabsRecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ElevenLabsSpeechAdapterTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        foreach (array_reverse($this->temporaryDirectories) as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }

        parent::tearDown();
    }

    public function test_default_configuration_fails_before_http(): void
    {
        Http::preventStrayRequests();

        try {
            $this->app->make(ElevenLabsTextToSpeechProvider::class)->synthesize(
                new TextToSpeechRequest('synthetic', 'Hello.'),
            );
            self::fail('Expected ElevenLabs to fail closed by default.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }

        Http::assertNothingSent();
    }

    public function test_tts_uses_india_host_zero_retention_and_returns_owned_wav(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.in.residency.elevenlabs.io/*' => Http::response(
                str_repeat("\0", 4800),
                200,
                [
                    'Content-Type' => 'audio/pcm',
                    'request-id' => 'provider-request-id',
                ],
            ),
        ]);

        $result = $this->app->make(ElevenLabsTextToSpeechProvider::class)
            ->synthesize(new TextToSpeechRequest(
                interactionId: 'synthetic',
                text: 'Please introduce yourself.',
                voice: 'en-IN-NeerjaNeural',
                purpose: 'model_en',
                uiLocale: 'en-IN',
            ));

        $this->assertTrue($result->playbackAvailable);
        $this->assertSame('audio/wav', $result->mimeType);
        $this->assertStringStartsWith('RIFF', (string) $result->audioBytes);
        $this->assertSame('elevenlabs-tts', $result->usage()->providerKey);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.in.residency.elevenlabs.io/v1/text-to-speech/voice123456?output_format=pcm_24000&enable_logging=false'
                && $request->hasHeader('xi-api-key', 'not-a-real-elevenlabs-key')
                && ($payload['model_id'] ?? null) === 'eleven_v3'
                && ($payload['language_code'] ?? null) === 'en'
                && ! str_contains($request->body(), 'synthetic');
        });
    }

    public function test_stt_uses_zero_retention_and_maps_word_log_probability(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.in.residency.elevenlabs.io/*' => Http::response([
                'language_code' => 'en',
                'language_probability' => 0.99,
                'text' => 'I practise English after work.',
                'words' => [
                    ['type' => 'word', 'text' => 'I', 'logprob' => -0.1],
                    ['type' => 'spacing', 'text' => ' '],
                    ['type' => 'word', 'text' => 'practise', 'logprob' => -0.2],
                ],
            ], 200, [
                'Content-Type' => 'application/json',
                'request-id' => 'provider-request-id',
            ]),
        ]);

        $result = $this->app->make(ElevenLabsRecordedSpeechToTextProvider::class)
            ->transcribe(new RecordedSpeechToTextRequest(
                interactionId: 'synthetic',
                audioBytes: self::wav(),
                mimeType: 'audio/wav',
                language: 'en-IN',
            ));

        $this->assertSame('I practise English after work.', $result->transcript);
        $this->assertSame(8618, $result->confidenceBps);
        $this->assertSame('elevenlabs-stt', $result->usage->providerKey);
        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.in.residency.elevenlabs.io/v1/speech-to-text?enable_logging=false'
                && $request->hasHeader('xi-api-key', 'not-a-real-elevenlabs-key')
                && str_contains($body, 'name="model_id"')
                && str_contains($body, 'scribe_v2')
                && str_contains($body, 'name="tag_audio_events"')
                && str_contains($body, 'false')
                && ! str_contains($body, 'synthetic');
        });
    }

    public function test_unrotated_or_unapproved_evidence_blocks_before_http(): void
    {
        $this->configureAuthorizedBoundary(false);
        Http::preventStrayRequests();

        try {
            $this->app->make(ElevenLabsRecordedSpeechToTextProvider::class)
                ->transcribe(new RecordedSpeechToTextRequest(
                    interactionId: 'synthetic',
                    audioBytes: self::wav(),
                    mimeType: 'audio/wav',
                    language: 'en-IN',
                ));
            self::fail('Expected incomplete live evidence to block dispatch.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }

        Http::assertNothingSent();
    }

    private function configureAuthorizedBoundary(bool $complete = true): void
    {
        $directory = sys_get_temp_dir().'/elevenlabs-'.Str::random(20);
        mkdir($directory, 0700);
        $this->temporaryDirectories[] = $directory;

        $secret = $directory.'/elevenlabs.secret';
        file_put_contents($secret, 'not-a-real-elevenlabs-key');
        chmod($secret, 0600);
        $this->temporaryFiles[] = $secret;

        $evidence = $directory.'/elevenlabs-vendor-evidence.json';
        $this->writeJson($evidence, [
            'provider' => 'elevenlabs',
            'api_host' => 'api.in.residency.elevenlabs.io',
            'india_residency_asserted' => true,
            'zero_retention_asserted' => true,
            'approved_for_learner_content' => $complete,
            'key_rotated' => $complete,
            'deletion_test_evidence_id' => $complete ? 'deletion-test-1' : null,
            'valid_until' => '2099-01-01T00:00:00Z',
        ]);

        if ($complete) {
            $this->writeJson($directory.'/elevenlabs-rotation-receipt.json', [
                'provider' => 'elevenlabs',
                'previous_key_revoked' => true,
                'new_secret_outside_git' => true,
            ]);
            $this->writeJson($directory.'/elevenlabs-deletion-test.json', [
                'provider' => 'elevenlabs',
                'evidence_id' => 'deletion-test-1',
                'content_free' => true,
                'audio_retained' => false,
                'transcript_retained' => false,
            ]);
            $this->writeJson($directory.'/elevenlabs-spend-approval.json', [
                'provider' => 'elevenlabs',
                'approved' => true,
                'content_dispatch_authorized' => true,
                'cap_inr' => 500,
                'valid_until' => '2099-01-01T00:00:00Z',
            ]);
        }

        config()->set([
            'speech_routing.live_router_enabled' => true,
            'speech_routing.candidates.elevenlabs-tts.dispatch_authorized' => true,
            'speech_routing.candidates.elevenlabs-tts.kill_switch' => false,
            'speech_routing.candidates.elevenlabs-tts.secret_file' => $secret,
            'speech_routing.candidates.elevenlabs-tts.evidence_file' => $evidence,
            'speech_routing.candidates.elevenlabs-tts.voice_id' => 'voice123456',
            'speech_routing.candidates.elevenlabs-stt.dispatch_authorized' => true,
            'speech_routing.candidates.elevenlabs-stt.kill_switch' => false,
            'speech_routing.candidates.elevenlabs-stt.secret_file' => $secret,
            'speech_routing.candidates.elevenlabs-stt.evidence_file' => $evidence,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function writeJson(string $path, array $payload): void
    {
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
        chmod($path, 0600);
        $this->temporaryFiles[] = $path;
    }

    private static function wav(): string
    {
        $sampleRate = 16_000;
        $dataSize = 3200;
        $format = 'fmt '.pack(
            'VvvVVvv',
            16,
            1,
            1,
            $sampleRate,
            $sampleRate * 2,
            2,
            16,
        );
        $data = 'data'.pack('V', $dataSize).str_repeat("\0", $dataSize);
        $body = 'WAVE'.$format.$data;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }
}
