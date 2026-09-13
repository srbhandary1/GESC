<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Voice\Providers\Indic\IndicHttpSpeechToTextProvider;
use App\Conversation\Voice\Providers\Indic\IndicWithAzureFailoverSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\RecordingSttTestDouble;
use Tests\TestCase;

final class IndicWithAzureFailoverSpeechToTextProviderTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_transient_indic_http_failure_retries_once_on_azure(): void
    {
        $this->configureAuthorizedIndicBoundary();
        Http::preventStrayRequests();
        Http::fake([
            'https://stt.indic.test/speech-to-text' => Http::response(
                ['error' => 'unavailable'],
                503,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $failover = new RecordingSttTestDouble($this->azureResult());
        $result = (new IndicWithAzureFailoverSpeechToTextProvider(
            $this->app->make(IndicHttpSpeechToTextProvider::class),
            $failover,
        ))->transcribe($this->request());

        $this->assertSame('Azure heard this English.', $result->transcript);
        $this->assertSame('azure-speech-soft-launch-stt', $result->usage->providerKey);
        $this->assertSame(1, $failover->calls);
        Http::assertSentCount(1);
    }

    public function test_malformed_indic_response_does_not_failover(): void
    {
        $this->configureAuthorizedIndicBoundary();
        Http::preventStrayRequests();
        Http::fake([
            'https://stt.indic.test/speech-to-text' => Http::response(
                ['transcript' => ''],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $failover = new RecordingSttTestDouble($this->azureResult());

        try {
            (new IndicWithAzureFailoverSpeechToTextProvider(
                $this->app->make(IndicHttpSpeechToTextProvider::class),
                $failover,
            ))->transcribe($this->request());
            self::fail('Expected a malformed Indic response to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_malformed_response', $exception->safeCode);
            $this->assertSame('indic-http-stt', $exception->providerKey);
        }

        $this->assertSame(0, $failover->calls);
    }

    public function test_unauthorized_azure_failover_rethrows_the_indic_failure(): void
    {
        $primary = new RecordingSttTestDouble(
            new ConversationProviderException(
                safeCode: 'provider_connection_failure',
                providerKey: 'indic-http-stt',
                modelVersion: 'indic-http-stt-v1:saaras:v3',
            ),
        );
        $failover = new RecordingSttTestDouble(
            new ConversationProviderException(
                safeCode: 'provider_not_authorized',
                providerKey: 'azure-speech-soft-launch-stt',
                modelVersion: 'fast-transcription-2025-10-15-provider-managed',
            ),
        );

        try {
            (new IndicWithAzureFailoverSpeechToTextProvider(
                $primary,
                $failover,
            ))->transcribe($this->request());
            self::fail('Expected the Indic connection failure to surface.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_connection_failure', $exception->safeCode);
            $this->assertSame('indic-http-stt', $exception->providerKey);
        }

        $this->assertSame(1, $failover->calls);
    }

    public function test_successful_indic_does_not_call_azure(): void
    {
        $primary = new RecordingSttTestDouble(new RecordedSpeechToTextResult(
            transcript: 'I practise English after work.',
            confidenceBps: 8700,
            usage: new ProviderUsage(
                providerKey: 'indic-http-stt',
                modelVersion: 'indic-http-stt-v1:saaras:v3',
                latencyMilliseconds: 12,
                inputUnits: 100,
                outputUnits: 30,
                estimatedCostMicrounits: 0,
            ),
        ));
        $failover = new RecordingSttTestDouble($this->azureResult());

        $result = (new IndicWithAzureFailoverSpeechToTextProvider(
            $primary,
            $failover,
        ))->transcribe($this->request());

        $this->assertSame('I practise English after work.', $result->transcript);
        $this->assertSame('indic-http-stt', $result->usage->providerKey);
        $this->assertSame(1, $primary->calls);
        $this->assertSame(0, $failover->calls);
    }

    private function configureAuthorizedIndicBoundary(): void
    {
        $secretPath = sys_get_temp_dir().'/indic-stt-'.Str::random(20).'.key';
        $this->temporaryFiles[] = $secretPath;
        file_put_contents($secretPath, 'not-a-real-indic-key');
        chmod($secretPath, 0600);

        config()->set([
            'voice.upload_enabled' => true,
            'voice.provider_driver' => 'indic',
            'voice.indic.transcribe_url' => 'https://stt.indic.test/speech-to-text',
            'voice.indic.allowed_host' => 'stt.indic.test',
            'voice.indic.secret_file' => $secretPath,
            'voice.indic.auth_header' => 'api-subscription-key',
            'voice.indic.model' => 'indic-translate-test',
            'voice.indic.mode' => 'translate',
            'voice.indic.language_code' => 'unknown',
            'voice.indic.output_language' => 'en-IN',
            'voice.indic.maximum_json_response_bytes' => 65536,
        ]);
    }

    private function request(): RecordedSpeechToTextRequest
    {
        return new RecordedSpeechToTextRequest(
            interactionId: 'opaque-synthetic-interaction',
            audioBytes: self::wav(),
            mimeType: 'audio/wav',
            language: 'en-IN',
        );
    }

    private function azureResult(): RecordedSpeechToTextResult
    {
        return new RecordedSpeechToTextResult(
            transcript: 'Azure heard this English.',
            confidenceBps: 9100,
            usage: new ProviderUsage(
                providerKey: 'azure-speech-soft-launch-stt',
                modelVersion: 'fast-transcription-2025-10-15-provider-managed',
                latencyMilliseconds: 40,
                inputUnits: 100,
                outputUnits: 24,
                estimatedCostMicrounits: 0,
            ),
        );
    }

    private static function wav(): string
    {
        $sampleRate = 16_000;
        $dataSize = (int) ($sampleRate * 2 / 10);
        $format = 'fmt '
            .pack(
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
