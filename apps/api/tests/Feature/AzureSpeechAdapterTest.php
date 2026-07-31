<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Voice\Providers\Azure\AzureRecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\Azure\AzureSpeechConfiguration;
use App\Conversation\Voice\Providers\Azure\AzureTextToSpeechProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AzureSpeechAdapterTest extends TestCase
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
        foreach ($this->temporaryDirectories as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }

        parent::tearDown();
    }

    public function test_default_configuration_fails_before_any_http_request(): void
    {
        Http::preventStrayRequests();

        try {
            $this->app->make(AzureTextToSpeechProvider::class)->synthesize(
                new TextToSpeechRequest('synthetic-test', 'Hello.'),
            );
            self::fail('Expected the disabled provider to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
            $this->assertFalse($exception->retryable);
        }

        Http::assertNothingSent();
    }

    public function test_configuration_rejects_repository_secret_and_public_endpoint(): void
    {
        $this->configureAuthorizedBoundary();
        config()->set(
            'speech.azure.endpoint',
            'http://0.0.0.0:8000/provider',
        );
        config()->set('speech.azure.secret_file', base_path('.env.example'));

        $errors = $this->app->make(
            AzureSpeechConfiguration::class,
        )->preflightErrors(false);

        $this->assertContains('endpoint_invalid', $errors);
        $this->assertContains('secret_file_invalid', $errors);
    }

    public function test_stt_sends_locked_request_and_validates_owned_result(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake([
            'https://speech-test.cognitiveservices.azure.com/*' => Http::response(
                [
                    'combinedPhrases' => [
                        ['channel' => 0, 'text' => 'Hello, how are you today?'],
                    ],
                    'phrases' => [[
                        'locale' => 'en-IN',
                        'confidence' => 0.93,
                        'offsetMilliseconds' => 0,
                        'durationMilliseconds' => 100,
                    ]],
                ],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $result = $this->app->make(
            AzureRecordedSpeechToTextProvider::class,
        )->transcribe(new RecordedSpeechToTextRequest(
            interactionId: 'opaque-synthetic-interaction',
            audioBytes: self::wav(16_000),
            mimeType: 'audio/wav',
            language: 'en-IN',
        ));

        $this->assertSame('Hello, how are you today?', $result->transcript);
        $this->assertSame(9300, $result->confidenceBps);
        $this->assertSame('azure-speech', $result->usage->providerKey);
        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->method() === 'POST'
                && $request->url()
                    === 'https://speech-test.cognitiveservices.azure.com/speechtotext/transcriptions:transcribe?api-version=2025-10-15'
                && $request->hasHeader(
                    'Ocp-Apim-Subscription-Key',
                    'not-a-real-provider-key',
                )
                && str_contains($body, '"locales":["en-IN"]')
                && str_contains($body, '"profanityFilterMode":"None"')
                && ! str_contains($body, 'opaque-synthetic-interaction');
        });
    }

    public function test_tts_escapes_text_and_rejects_redirects_without_retry(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(
                self::wav(24_000),
                200,
                ['Content-Type' => 'audio/wav'],
            )
            ->push('', 302, ['Location' => 'https://example.test/leak']);

        $provider = $this->app->make(AzureTextToSpeechProvider::class);
        $result = $provider->synthesize(new TextToSpeechRequest(
            interactionId: 'synthetic-tts',
            text: 'Tea & biscuits < please.',
            voice: 'en-IN-PrabhatNeural',
        ));

        $this->assertTrue($result->playbackAvailable);
        $this->assertSame('audio/wav', $result->mimeType);
        $this->assertSame(self::wav(24_000), $result->audioBytes);
        Http::assertSent(function (Request $request): bool {
            return str_contains(
                $request->body(),
                'Tea &amp; biscuits &lt; please.',
            ) && str_contains(
                $request->body(),
                'name="en-IN-PrabhatNeural"',
            );
        });

        try {
            $provider->synthesize(new TextToSpeechRequest(
                interactionId: 'synthetic-redirect',
                text: 'Hello.',
            ));
            self::fail('Expected redirect response to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame(
                'provider_redirect_refused',
                $exception->safeCode,
            );
            $this->assertFalse($exception->retryable);
        }

        Http::assertSentCount(2);
    }

    #[DataProvider('httpFailureProvider')]
    public function test_http_failures_are_safely_classified(
        int $status,
        string $safeCode,
        bool $retryable,
    ): void {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response(
                'provider body must not be exposed',
                $status,
                ['Content-Type' => 'text/plain'],
            ),
        ]);

        try {
            $this->app->make(AzureTextToSpeechProvider::class)->synthesize(
                new TextToSpeechRequest('synthetic-failure', 'Hello.'),
            );
            self::fail('Expected the provider response to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame($safeCode, $exception->safeCode);
            $this->assertSame($retryable, $exception->retryable);
            $this->assertStringNotContainsString(
                'provider body',
                $exception->getMessage(),
            );
        }

        Http::assertSentCount(1);
    }

    /**
     * @return iterable<string, array{int, string, bool}>
     */
    public static function httpFailureProvider(): iterable
    {
        yield 'unauthenticated' => [401, 'provider_authentication_failed', false];
        yield 'forbidden' => [403, 'provider_authorization_failed', false];
        yield 'timeout status' => [408, 'provider_transient_failure', true];
        yield 'request too large' => [413, 'provider_request_too_large', false];
        yield 'rate limited' => [429, 'provider_transient_failure', true];
        yield 'server error' => [500, 'provider_transient_failure', true];
        yield 'unavailable' => [503, 'provider_transient_failure', true];
    }

    public function test_connection_and_malformed_bodies_fail_without_content_leak(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(
                '{"combinedPhrases":',
                200,
                ['Content-Type' => 'application/json'],
            )
            ->push(
                'not a wave file',
                200,
                ['Content-Type' => 'audio/wav'],
            )
            ->push(
                '',
                200,
                [
                    'Content-Type' => 'audio/wav',
                    'Content-Length' => (string) (10 * 1024 * 1024 + 1),
                ],
            )
            ->pushFailedConnection('sensitive transport detail');

        $cases = [
            fn () => $this->app->make(
                AzureRecordedSpeechToTextProvider::class,
            )->transcribe(new RecordedSpeechToTextRequest(
                'synthetic-json',
                self::wav(16_000),
                'audio/wav',
                'en-IN',
            )),
            fn () => $this->app->make(
                AzureTextToSpeechProvider::class,
            )->synthesize(new TextToSpeechRequest('synthetic-wav', 'Hello.')),
            fn () => $this->app->make(
                AzureTextToSpeechProvider::class,
            )->synthesize(new TextToSpeechRequest('synthetic-size', 'Hello.')),
            fn () => $this->app->make(
                AzureTextToSpeechProvider::class,
            )->synthesize(new TextToSpeechRequest('synthetic-timeout', 'Hello.')),
        ];
        $expected = [
            'provider_malformed_response',
            'provider_malformed_response',
            'provider_response_too_large',
            'provider_connection_failure',
        ];

        foreach ($cases as $index => $case) {
            try {
                $case();
                self::fail('Expected the injected fault to fail closed.');
            } catch (ConversationProviderException $exception) {
                $this->assertSame($expected[$index], $exception->safeCode);
                $this->assertStringNotContainsString(
                    'sensitive',
                    $exception->getMessage(),
                );
            }
        }

        Http::assertSentCount(4);
    }

    private function configureAuthorizedBoundary(): void
    {
        $this->app->detectEnvironment(
            static fn (): string => 'speech-benchmark',
        );
        $secretPath = $this->temporaryPath('secret');
        $vendorPath = $this->temporaryPath('vendor.json');
        $spendPath = $this->temporaryPath('spend.json');
        $ephemeralRoot = $this->temporaryDirectory('fixtures');
        file_put_contents($secretPath, 'not-a-real-provider-key');
        chmod($secretPath, 0600);
        $this->writeEvidence($vendorPath, [
            'schema_version' => 1,
            'provider' => 'azure-speech',
            'region' => 'centralindia',
            'approved_for_synthetic_benchmark' => true,
            'data_logging_enabled' => false,
            'model_improvement_enabled' => false,
            'custom_speech_enabled' => false,
            'subscription_evidence_id' => 'subscription-evidence-v1',
            'contracting_entity_review_id' => 'entity-review-v1',
            'agreement_sha256' => str_repeat('a', 64),
            'dpa_sha256' => str_repeat('b', 64),
            'subprocessor_sha256' => str_repeat('c', 64),
            'privacy_legal_review_id' => 'privacy-review-v1',
            'logging_disabled_evidence_id' => 'logging-review-v1',
            'reviewed_at' => '2026-07-31T00:00:00Z',
            'valid_until' => '2099-07-31T00:00:00Z',
        ]);
        $this->writeEvidence($spendPath, [
            'schema_version' => 1,
            'benchmark_id' => 'pr009-azure-speech-synthetic-v1',
            'approval_id' => 'spend-approval-v1',
            'approved' => true,
            'cap_inr' => 1000,
            'reviewed_at' => '2026-07-31T00:00:00Z',
            'valid_until' => '2099-07-31T00:00:00Z',
        ]);

        config()->set([
            'speech.azure.live_requests_enabled' => true,
            'speech.azure.endpoint' => 'https://speech-test.cognitiveservices.azure.com',
            'speech.azure.allowed_host' => 'speech-test.cognitiveservices.azure.com',
            'speech.azure.credential_kind' => 'subscription-key',
            'speech.azure.secret_file' => $secretPath,
            'speech.benchmark.enabled' => true,
            'speech.benchmark.runner_commit' => str_repeat('d', 40),
            'speech.benchmark.approved_spend_cap_inr' => 1000,
            'speech.benchmark.vendor_evidence_path' => $vendorPath,
            'speech.benchmark.spend_approval_path' => $spendPath,
            'speech.benchmark.safe_report_path' => '/tmp/pr009-safe-report.json',
            'speech.benchmark.ephemeral_root' => $ephemeralRoot,
            'speech.benchmark.ephemeral_storage_acknowledged' => true,
            'speech.benchmark.evidence.subscription' => 'subscription-evidence-v1',
            'speech.benchmark.evidence.contracting_entity' => 'entity-review-v1',
            'speech.benchmark.evidence.agreement_sha256' => str_repeat('a', 64),
            'speech.benchmark.evidence.dpa_sha256' => str_repeat('b', 64),
            'speech.benchmark.evidence.subprocessor_sha256' => str_repeat('c', 64),
            'speech.benchmark.evidence.privacy_legal_review' => 'privacy-review-v1',
            'speech.benchmark.evidence.logging_disabled' => 'logging-review-v1',
            'speech.benchmark.evidence.credential_version' => 'credential-version-v1',
            'speech.benchmark.evidence.spend_approval' => 'spend-approval-v1',
            'speech.benchmark.evidence.price_snapshot' => 'price-snapshot-v1',
            'speech.benchmark.evidence.content_scan' => 'content-scan-v1',
            'speech.benchmark.pricing.inr_per_stt_second' => 0.01,
            'speech.benchmark.pricing.inr_per_tts_character' => 0.001,
            'speech.benchmark.pricing.conversion_source' => 'billing-source-v1',
            'speech.benchmark.pricing.conversion_instant' => '2026-07-31T00:00:00Z',
        ]);
    }

    private function temporaryPath(string $suffix): string
    {
        $path = sys_get_temp_dir()
            .'/pr009-speech-'.Str::random(20).'-'.$suffix;
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function temporaryDirectory(string $suffix): string
    {
        $path = sys_get_temp_dir()
            .'/pr009-speech-'.Str::random(20).'-'.$suffix;
        mkdir($path, 0700);
        chmod($path, 0700);
        $this->temporaryDirectories[] = $path;

        return $path;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function writeEvidence(string $path, array $evidence): void
    {
        file_put_contents($path, json_encode($evidence, JSON_THROW_ON_ERROR));
        chmod($path, 0600);
    }

    private static function wav(int $sampleRate): string
    {
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
