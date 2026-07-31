<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Voice\Benchmark\SpeechBenchmarkCorpus;
use App\Conversation\Voice\Benchmark\SpeechBenchmarkRunner;
use App\Conversation\Voice\Benchmark\SyntheticAudioTransformer;
use App\Conversation\Voice\Benchmark\TranscriptMetrics;
use App\Conversation\Voice\PcmWavInspector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class SpeechBenchmarkPolicyTest extends TestCase
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

    public function test_locked_corpus_has_exact_shape_and_hash(): void
    {
        $utterances = $this->app->make(SpeechBenchmarkCorpus::class)->load();

        $this->assertCount(48, $utterances);
        $this->assertSame('foundation-01', $utterances[0]['id']);
        $this->assertSame(
            'Hello, how are you today?',
            $utterances[0]['text'],
        );
        $this->assertSame('repair-08', $utterances[47]['id']);
        $this->assertSame(
            config('speech.benchmark.expected_corpus_sha256'),
            hash_file(
                'sha256',
                (string) config('speech.benchmark.corpus_path'),
            ),
        );
    }

    public function test_audio_transforms_are_deterministic_and_normalized(): void
    {
        $transformer = $this->app->make(SyntheticAudioTransformer::class);
        $source = self::wav();
        $seed = hash('sha256', 'locked-test-seed');
        $clean = $transformer->transform($source, $seed, 'clean');
        $snr20 = $transformer->transform($source, $seed, 'snr20');
        $snr20Replay = $transformer->transform($source, $seed, 'snr20');
        $lowPass = $transformer->transform(
            $source,
            $seed,
            'snr10_lowpass',
        );

        $metadata = $this->app->make(PcmWavInspector::class)->inspect($clean);
        $this->assertSame(16_000, $metadata->sampleRateHz);
        $this->assertSame(100, $metadata->durationMs);
        $this->assertSame($snr20, $snr20Replay);
        $this->assertNotSame($clean, $snr20);
        $this->assertNotSame($snr20, $lowPass);
    }

    public function test_transcript_metrics_preserve_errors_and_flag_insertions(): void
    {
        $metrics = (new TranscriptMetrics)->compare(
            'Yesterday I go to the market.',
            'Yesterday I go really very slowly to the market.',
        );

        $this->assertGreaterThan(0, $metrics['edits']);
        $this->assertFalse($metrics['exact']);
        $this->assertTrue($metrics['material_hallucination']);
    }

    public function test_command_fails_preflight_without_any_provider_request(): void
    {
        Http::preventStrayRequests();

        $command = $this->artisan('speech:benchmark --preflight');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('"status":"blocked"')
            ->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_locked_runner_completes_exact_operation_plan_with_http_fakes(): void
    {
        $this->configureAuthorizedBenchmark();
        Http::preventStrayRequests();
        $currentText = '';
        $requestCount = 0;
        Http::fake(function (Request $request) use (
            &$currentText,
            &$requestCount,
        ) {
            $requestCount++;
            if (str_ends_with($request->url(), '/cognitiveservices/v1')) {
                preg_match(
                    '/<voice name="[^"]+">(.*)<\/voice>/s',
                    $request->body(),
                    $match,
                );
                $currentText = html_entity_decode(
                    (string) ($match[1] ?? ''),
                    ENT_QUOTES | ENT_XML1,
                    'UTF-8',
                );

                if ($requestCount === 1) {
                    return Http::response('', 503);
                }

                return Http::response(
                    self::wav(),
                    200,
                    ['Content-Type' => 'audio/wav'],
                );
            }

            return Http::response([
                'combinedPhrases' => [
                    ['channel' => 0, 'text' => $currentText],
                ],
                'phrases' => [[
                    'locale' => 'en-IN',
                    'confidence' => 0.99,
                    'offsetMilliseconds' => 0,
                    'durationMilliseconds' => 100,
                ]],
            ], 200, ['Content-Type' => 'application/json']);
        });

        $report = $this->app->make(SpeechBenchmarkRunner::class)->run();

        $this->assertTrue($report['passed']);
        $this->assertSame(544, $report['operations']['logical']);
        $this->assertSame(545, $report['operations']['http_attempts']);
        $this->assertSame(368, $report['stt']['conditions']['clean']['clip_count']
            + $report['stt']['conditions']['snr20']['clip_count']
            + $report['stt']['conditions']['snr10_lowpass']['clip_count']
            + 80);
        $this->assertSame(176, $report['tts']['valid_outputs']);
        $this->assertSame([], $report['operations']['safe_failures']);
        Http::assertSentCount(545);
    }

    private function configureAuthorizedBenchmark(): void
    {
        $this->app->detectEnvironment(
            static fn (): string => 'speech-benchmark',
        );
        $secret = $this->temporaryPath('secret');
        $vendor = $this->temporaryPath('vendor.json');
        $spend = $this->temporaryPath('spend.json');
        $listener = $this->temporaryPath('listener.json');
        $report = $this->temporaryPath('report.json');
        file_put_contents($secret, 'not-a-real-provider-key');
        chmod($secret, 0600);
        $this->writeEvidence($vendor, [
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
        $this->writeEvidence($spend, [
            'schema_version' => 1,
            'benchmark_id' => 'pr009-azure-speech-synthetic-v1',
            'approval_id' => 'spend-approval-v1',
            'approved' => true,
            'cap_inr' => 1000,
            'reviewed_at' => '2026-07-31T00:00:00Z',
            'valid_until' => '2099-07-31T00:00:00Z',
        ]);
        $this->writeEvidence($listener, [
            'benchmark_id' => 'pr009-azure-speech-synthetic-v1',
            'reviewer_ids' => ['development-listener-1', 'development-listener-2'],
            'output_count' => 96,
            'intelligibility_accepted_by_both' => 96,
            'acceptability_accepted_by_both' => 96,
            'critical_pronunciation_defects' => 0,
        ]);

        config()->set([
            'speech.azure.live_requests_enabled' => true,
            'speech.azure.endpoint' => 'https://speech-test.cognitiveservices.azure.com',
            'speech.azure.allowed_host' => 'speech-test.cognitiveservices.azure.com',
            'speech.azure.credential_kind' => 'subscription-key',
            'speech.azure.secret_file' => $secret,
            'speech.benchmark.enabled' => true,
            'speech.benchmark.runner_commit' => str_repeat('d', 40),
            'speech.benchmark.approved_spend_cap_inr' => 1000,
            'speech.benchmark.vendor_evidence_path' => $vendor,
            'speech.benchmark.spend_approval_path' => $spend,
            'speech.benchmark.safe_report_path' => $report,
            'speech.benchmark.listener_evidence_path' => $listener,
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
            'speech.benchmark.pricing.inr_per_stt_second' => 0.001,
            'speech.benchmark.pricing.inr_per_tts_character' => 0.0001,
            'speech.benchmark.pricing.conversion_source' => 'billing-source-v1',
            'speech.benchmark.pricing.conversion_instant' => '2026-07-31T00:00:00Z',
        ]);
    }

    private function temporaryPath(string $suffix): string
    {
        $path = sys_get_temp_dir()
            .'/pr009-benchmark-'.Str::random(20).'-'.$suffix;
        $this->temporaryFiles[] = $path;

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

    private static function wav(): string
    {
        $sampleRate = 24_000;
        $samples = '';
        for ($index = 0; $index < 2400; $index++) {
            $value = (int) round(
                sin(2 * M_PI * 440 * $index / $sampleRate) * 8000,
            );
            $samples .= pack('v', $value < 0 ? $value + 65536 : $value);
        }
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
        $body = 'WAVE'.$format.'data'.pack('V', strlen($samples)).$samples;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }
}
