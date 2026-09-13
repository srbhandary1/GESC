<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Curriculum\Commissioning\MediaTranscription\AzureCurriculumMediaTranscriptionProvider;
use App\Curriculum\Commissioning\MediaTranscription\CurriculumMediaTranscriptionProvider;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionCandidate;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionCandidateStore;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionConfiguration;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionProviderException;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionRejected;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionRequest;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionSafeReportWriter;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionSegment;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CurriculumMediaTranscriptionBoundaryTest extends TestCase
{
    public function test_live_provider_is_not_available_through_ordinary_dependency_injection(): void
    {
        $this->assertFalse($this->app->bound(CurriculumMediaTranscriptionProvider::class));
        $this->assertTrue($this->app->bound(MediaTranscriptionCandidateStore::class));

        Http::preventStrayRequests();
        try {
            $this->app->make(MediaTranscriptionConfiguration::class)->authorize(
                (string) Str::ulid(),
            );
            self::fail('Expected disabled-default commissioning to fail closed.');
        } catch (MediaTranscriptionRejected $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }
        Http::assertNothingSent();
    }

    public function test_candidate_store_encrypts_content_and_verifies_its_digest(): void
    {
        Storage::fake('transcription-test');
        config()->set(
            'media_transcription.candidate.disk',
            'transcription-test',
        );
        $organizationId = (string) Str::ulid();
        $campaignId = (string) Str::ulid();
        $assetId = (string) Str::ulid();
        $attemptId = (string) Str::ulid();
        $candidate = new MediaTranscriptionCandidate(
            transcript: 'I can speak in English.',
            locale: 'en-IN',
            segments: [new MediaTranscriptionSegment(1, 'I can speak in English.', 0, 1400, 9600)],
        );
        $store = $this->app->make(MediaTranscriptionCandidateStore::class);

        $digest = $store->put(
            $organizationId,
            $campaignId,
            $assetId,
            $attemptId,
            $candidate,
        );
        $ciphertext = Storage::disk('transcription-test')->get(
            'v1/'.$organizationId.'/'.$campaignId.'/'.$assetId
            .'/'.$attemptId.'.candidate',
        );
        self::assertIsString($ciphertext);

        $this->assertSame(64, strlen($digest));
        $this->assertStringNotContainsString($candidate->transcript, $ciphertext);
        $this->assertSame(
            $candidate->toArray(),
            $store->read(
                $organizationId,
                $campaignId,
                $assetId,
                $attemptId,
                $digest,
            )->toArray(),
        );
        $this->assertSame(
            $digest,
            $store->digest($organizationId, $campaignId, $assetId, $attemptId),
        );

        $store->delete($organizationId, $campaignId, $assetId, $attemptId);
        $this->assertFalse($store->exists(
            $organizationId,
            $campaignId,
            $assetId,
            $attemptId,
        ));
    }

    public function test_azure_adapter_sends_only_locked_inline_mp3_and_validates_segments(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response([
                'combinedPhrases' => [[
                    'text' => 'I can speak in English.',
                ]],
                'phrases' => [[
                    'locale' => 'en-IN',
                    'text' => 'I can speak in English.',
                    'confidence' => 0.96,
                    'offsetMilliseconds' => 0,
                    'durationMilliseconds' => 1400,
                ]],
                'modelVersion' => 'provider-managed',
            ], 200, [
                'Content-Type' => 'application/json',
                'apim-request-id' => 'safe-provider-correlation',
            ]),
        ]);
        $audio = "ID3\x04\x00synthetic-mp3";
        $provider = new AzureCurriculumMediaTranscriptionProvider(
            $this->app->make(Factory::class),
            $this->authorization(),
        );

        $result = $provider->transcribe(new MediaTranscriptionRequest(
            audioBytes: $audio,
            sourceSha256: hash('sha256', $audio),
            sourceDurationMilliseconds: 2000,
        ));

        $this->assertSame('I can speak in English.', $result->transcript);
        $this->assertSame(9600, $result->segments[0]->confidenceBps);
        $this->assertSame(2000, $result->usageMilliseconds);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $result->responseMetadataSha256);
        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->method() === 'POST'
                && $request->url() === 'https://speech-test.cognitiveservices.azure.com/speechtotext/transcriptions:transcribe?api-version=2025-10-15'
                && $request->hasHeader('Ocp-Apim-Subscription-Key', 'synthetic-secret')
                && str_contains($body, 'filename="audio.mp3"')
                && str_contains($body, 'Content-Type: audio/mpeg')
                && str_contains($body, '"locales":["en-IN"]')
                && str_contains($body, '"profanityFilterMode":"None"')
                && ! str_contains($body, 'organization_id')
                && ! str_contains($body, 'campaign_id');
        });
    }

    #[DataProvider('providerFailureCases')]
    public function test_azure_adapter_fails_closed_on_http_and_response_faults(
        int $status,
        string $body,
        string $contentType,
        string $expectedCode,
        bool $retryable,
    ): void {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response($body, $status, ['Content-Type' => $contentType])]);
        $audio = 'synthetic-mp3';

        try {
            (new AzureCurriculumMediaTranscriptionProvider(
                $this->app->make(Factory::class),
                $this->authorization(),
            ))->transcribe(new MediaTranscriptionRequest(
                audioBytes: $audio,
                sourceSha256: hash('sha256', $audio),
                sourceDurationMilliseconds: 2000,
            ));
            self::fail('Expected provider fault to fail closed.');
        } catch (MediaTranscriptionProviderException $exception) {
            $this->assertSame($expectedCode, $exception->safeCode);
            $this->assertSame($retryable, $exception->retryable);
            if ($body !== '') {
                $this->assertStringNotContainsString($body, $exception->getMessage());
            }
        }
    }

    /** @return iterable<string, array{int, string, string, string, bool}> */
    public static function providerFailureCases(): iterable
    {
        yield 'rate limit' => [429, 'sensitive provider body', 'text/plain', 'provider_transient_failure', true];
        yield 'redirect' => [302, '', 'text/plain', 'provider_redirect_refused', false];
        yield 'malformed json' => [200, '{"combinedPhrases":', 'application/json', 'provider_malformed_response', false];
        yield 'html success' => [200, '<html>sensitive</html>', 'text/html', 'provider_malformed_response', false];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('malformedProviderPayloads')]
    public function test_azure_adapter_rejects_malformed_transcription_shapes(
        array $payload,
    ): void {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response($payload, 200, [
            'Content-Type' => 'application/json',
        ])]);

        try {
            $this->transcribeSyntheticAudio();
            self::fail('Expected malformed provider payload to fail closed.');
        } catch (MediaTranscriptionProviderException $exception) {
            $this->assertSame(
                'provider_malformed_response',
                $exception->safeCode,
            );
            $this->assertFalse($exception->retryable);
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function malformedProviderPayloads(): iterable
    {
        $valid = [
            'combinedPhrases' => [[
                'channel' => 0,
                'text' => 'I can speak in English.',
            ]],
            'phrases' => [[
                'locale' => 'en-IN',
                'text' => 'I can speak in English.',
                'confidence' => 0.96,
                'offsetMilliseconds' => 0,
                'durationMilliseconds' => 1000,
            ]],
        ];

        yield 'wrong locale' => [[
            ...$valid,
            'phrases' => [[
                ...$valid['phrases'][0],
                'locale' => 'en-US',
            ]],
        ]];
        yield 'multiple combined channels' => [[
            ...$valid,
            'combinedPhrases' => [
                $valid['combinedPhrases'][0],
                ['channel' => 1, 'text' => 'Unexpected second channel.'],
            ],
        ]];
        yield 'unexpected explicit channel' => [[
            ...$valid,
            'combinedPhrases' => [[
                'channel' => 1,
                'text' => 'Unexpected channel.',
            ]],
        ]];
        yield 'overlapping segments' => [[
            ...$valid,
            'phrases' => [
                $valid['phrases'][0],
                [
                    ...$valid['phrases'][0],
                    'text' => 'Overlap.',
                    'offsetMilliseconds' => 999,
                ],
            ],
        ]];
        yield 'segment beyond bounded duration' => [[
            ...$valid,
            'phrases' => [[
                ...$valid['phrases'][0],
                'offsetMilliseconds' => 2500,
                'durationMilliseconds' => 501,
            ]],
        ]];
        yield 'non-NFC transcript' => [[
            ...$valid,
            'combinedPhrases' => [[
                'channel' => 0,
                'text' => "Cafe\u{0301}",
            ]],
        ]];
        yield 'confidence outside range' => [[
            ...$valid,
            'phrases' => [[
                ...$valid['phrases'][0],
                'confidence' => 1.01,
            ]],
        ]];
    }

    /** @param array<string, string> $headers */
    #[DataProvider('oversizedProviderResponses')]
    public function test_azure_adapter_rejects_oversized_provider_responses(
        string $body,
        array $headers,
    ): void {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response($body, 200, [
            'Content-Type' => 'application/json',
            ...$headers,
        ])]);

        try {
            $this->transcribeSyntheticAudio();
            self::fail('Expected oversized provider response to fail closed.');
        } catch (MediaTranscriptionProviderException $exception) {
            $this->assertSame(
                'provider_response_too_large',
                $exception->safeCode,
            );
            $this->assertFalse($exception->retryable);
        }
    }

    /** @return iterable<string, array{string, array<string, string>}> */
    public static function oversizedProviderResponses(): iterable
    {
        yield 'declared content length' => [
            '{}',
            ['Content-Length' => '1048577'],
        ];
        yield 'actual body' => [
            str_repeat('x', 1_048_577),
            [],
        ];
    }

    public function test_safe_report_writer_allows_only_content_free_external_reports(): void
    {
        $path = sys_get_temp_dir().'/gse-safe-transcription-report-'
            .Str::lower((string) Str::ulid()).'.json';
        $report = $this->safeReport();

        try {
            $digest = $this->app->make(
                MediaTranscriptionSafeReportWriter::class,
            )->write($report, $path);
            $contents = file_get_contents($path);
            self::assertIsString($contents);

            $this->assertSame(hash('sha256', $contents), $digest);
            $this->assertSame(0600, fileperms($path) & 0777);
            $this->assertStringNotContainsString('transcript', $contents);
            $this->assertSame(
                $report,
                json_decode($contents, true, 64, JSON_THROW_ON_ERROR),
            );

            try {
                $this->app->make(
                    MediaTranscriptionSafeReportWriter::class,
                )->write([...$report, 'transcript' => 'sensitive'], $path);
                self::fail('Expected content-bearing report to be rejected.');
            } catch (MediaTranscriptionRejected $exception) {
                $this->assertSame('safe_report_invalid', $exception->safeCode);
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function transcribeSyntheticAudio(): void
    {
        $audio = 'synthetic-mp3';
        (new AzureCurriculumMediaTranscriptionProvider(
            $this->app->make(Factory::class),
            $this->authorization(),
        ))->transcribe(new MediaTranscriptionRequest(
            audioBytes: $audio,
            sourceSha256: hash('sha256', $audio),
            sourceDurationMilliseconds: 2000,
        ));
    }

    /** @return array<string, int|string> */
    private function safeReport(): array
    {
        return [
            'campaign_id' => (string) Str::ulid(),
            'state' => 'provider_complete',
            'manifest_sha256' => str_repeat('a', 64),
            'configuration_sha256' => str_repeat('b', 64),
            'vendor_evidence_sha256' => str_repeat('c', 64),
            'price_evidence_sha256' => str_repeat('d', 64),
            'logical_assets' => 4,
            'logical_asset_cap' => 4,
            'reserved_http_attempts' => 4,
            'http_attempt_cap' => 8,
            'reserved_uploaded_bytes' => 18_760_190,
            'uploaded_byte_cap' => 37_520_380,
            'reserved_billable_milliseconds' => 1_172_513,
            'billable_millisecond_cap' => 2_345_026,
            'reserved_estimated_cost_microunits' => 50_000_000,
            'estimated_cost_microunits_cap' => 100_000_000,
            'provider_attempts' => 4,
            'provider_successes' => 4,
            'provider_failures' => 0,
            'human_reconciliations' => 0,
            'candidate_deletions_completed' => 0,
            'candidate_deletion_failures' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function authorization(): array
    {
        return [
            'auth_header' => [
                'Ocp-Apim-Subscription-Key' => 'synthetic-secret',
            ],
            'endpoint' => 'https://speech-test.cognitiveservices.azure.com',
        ];
    }
}
