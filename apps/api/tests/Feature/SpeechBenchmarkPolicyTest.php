<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Voice\Benchmark\SpeechBenchmarkCorpus;
use App\Conversation\Voice\Benchmark\SpeechBenchmarkReviewServer;
use App\Conversation\Voice\Benchmark\SpeechBenchmarkRunner;
use App\Conversation\Voice\Benchmark\SpeechBenchmarkRunStore;
use App\Conversation\Voice\Benchmark\SyntheticAudioTransformer;
use App\Conversation\Voice\Benchmark\TranscriptMetrics;
use App\Conversation\Voice\PcmWavInspector;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class SpeechBenchmarkPolicyTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        foreach (array_reverse($this->temporaryDirectories) as $path) {
            $this->deleteDirectory($path);
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

    public function test_transcript_metrics_equate_spoken_and_digit_number_forms(): void
    {
        $cases = [
            [
                'The practice code is A B seven nine.',
                'the practice code is a b79.',
            ],
            [
                'My appointment is at nine thirty tomorrow.',
                'my appointment is at 9 thirty tomorrow.',
            ],
            [
                'Please write zero zero four two on the sample form.',
                'please write 0042 on the sample form.',
            ],
            [
                'Room twelve is on the second floor.',
                'room 12 is on the second floor.',
            ],
            [
                'The mock order number is five eight three one.',
                'The mock order number is 5831.',
            ],
            [
                'The sample balance is zero.',
                'The sample balance is 0.',
            ],
            [
                'The bus arrives at six forty-five.',
                'the bus arrives at six forty five.',
            ],
            [
                'Spell the word train as T R A I N.',
                'Spell the word train as t-r-a-i-n.',
            ],
            [
                'The sample price is two hundred and fifty rupees.',
                'the sample price is ₹250.',
            ],
        ];

        foreach ($cases as [$reference, $hypothesis]) {
            $metrics = (new TranscriptMetrics)->compare($reference, $hypothesis);
            $this->assertTrue(
                $metrics['exact'],
                'Expected exact match for '.$reference.' vs '.$hypothesis,
            );
        }
    }

    public function test_command_fails_preflight_without_any_provider_request(): void
    {
        Http::preventStrayRequests();

        $command = $this->artisan(
            'speech:benchmark --phase=preflight',
        );
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
        $sttRequestCount = 0;
        $expectedSttTexts = [];
        $utterances = $this->app->make(SpeechBenchmarkCorpus::class)->load();
        foreach ($utterances as $utterance) {
            for ($index = 0; $index < 6; $index++) {
                $expectedSttTexts[] = $utterance['text'];
            }
        }
        $byId = array_column($utterances, null, 'id');
        for ($round = 1; $round <= 20; $round++) {
            foreach (config('speech.benchmark.repetition_fixture_ids') as $id) {
                $expectedSttTexts[] = $byId[$id]['text'];
            }
        }
        $databaseQueries = [];
        DB::listen(static function () use (&$databaseQueries): void {
            $databaseQueries[] = true;
        });
        Http::fake(function (Request $request) use (
            &$currentText,
            &$requestCount,
            &$sttRequestCount,
            $expectedSttTexts,
        ) {
            $requestCount++;
            if (str_ends_with($request->url(), '/tts/cognitiveservices/v1')) {
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

            $transcript = $expectedSttTexts[$sttRequestCount] ?? '';
            $sttRequestCount++;

            return Http::response([
                'combinedPhrases' => [
                    ['channel' => 0, 'text' => $transcript],
                ],
                'phrases' => [[
                    'locale' => 'en-IN',
                    'confidence' => 0.99,
                    'offsetMilliseconds' => 0,
                    'durationMilliseconds' => 100,
                ]],
            ], 200, ['Content-Type' => 'application/json']);
        });

        $runner = $this->app->make(SpeechBenchmarkRunner::class);
        $summary = $runner->prepare();
        $runId = (string) $summary['run_id'];
        $store = $this->app->make(SpeechBenchmarkRunStore::class);
        $runDirectory = (string) config('speech.benchmark.ephemeral_root')
            .'/'.$runId;
        $this->assertSame('prepared', $summary['status']);
        $this->assertSame(96, $summary['fixture_count']);
        $this->assertSame(96, $summary['logical_operations']);
        $this->assertSame(97, $summary['http_attempts']);
        $this->assertSame(
            $summary,
            $runner->prepare($runId),
        );
        Http::assertSentCount(97);
        $this->assertCount(96, glob($runDirectory.'/*.fixture') ?: []);
        foreach (glob($runDirectory.'/*.fixture') ?: [] as $fixturePath) {
            $ciphertext = file_get_contents($fixturePath);
            $this->assertIsString($ciphertext);
            $this->assertStringNotContainsString('RIFF', $ciphertext);
            $this->assertSame(0600, fileperms($fixturePath) & 0777);
        }
        $choices = array_map(
            static fn (array $fixture): array => [
                'review_id' => $fixture['review_id'],
                'intelligible' => true,
                'acceptable' => true,
                'critical_defect' => false,
            ],
            $store->reviewManifest($runId),
        );
        $store->recordReview($runId, 1, $choices);
        $aggregate = $store->recordReview($runId, 2, $choices);
        $this->assertSame(96, $aggregate['output_count']);
        $completionPreflight = $runner->preflightCompletion($runId);
        $this->assertSame(
            'complete_ready',
            $completionPreflight['status'],
        );
        Http::assertSentCount(97);

        $report = $runner->complete($runId);

        $this->assertTrue($report['passed']);
        $this->assertSame(544, $report['operations']['logical']);
        $this->assertSame(545, $report['operations']['http_attempts']);
        $this->assertSame(368, $report['stt']['conditions']['clean']['clip_count']
            + $report['stt']['conditions']['snr20']['clip_count']
            + $report['stt']['conditions']['snr10_lowpass']['clip_count']
            + 80);
        $this->assertSame(176, $report['tts']['valid_outputs']);
        $this->assertSame([], $report['operations']['safe_failures']);
        $this->assertTrue($report['ephemeral_content']['bundle_deleted']);
        $this->assertDirectoryDoesNotExist($runDirectory);
        $this->assertFileExists(
            (string) config('speech.benchmark.ephemeral_root')
                .'/receipts/'.$runId.'.json',
        );
        $receiptPath = (string) config('speech.benchmark.ephemeral_root')
            .'/receipts/'.$runId.'.json';
        $receiptBeforeReplay = file_get_contents($receiptPath);
        try {
            $runner->complete($runId);
            $this->fail('A completed benchmark run must not replay.');
        } catch (\RuntimeException) {
            $this->assertSame(
                $receiptBeforeReplay,
                file_get_contents($receiptPath),
            );
        }
        $this->assertSame([], $databaseQueries);
        Http::assertSentCount(545);
    }

    public function test_review_utility_requires_memory_only_loopback_token(): void
    {
        $this->configureAuthorizedBenchmark();
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response(
                self::wav(),
                200,
                ['Content-Type' => 'audio/wav'],
            ),
        ]);
        $summary = $this->app
            ->make(SpeechBenchmarkRunner::class)
            ->prepare();
        $runId = (string) $summary['run_id'];
        $server = $this->app->make(SpeechBenchmarkReviewServer::class);
        $token = str_repeat('a', 64);

        $page = $server->handle($runId, 1, $token, 'GET', '/', [], '');
        $this->assertSame(200, $page['status']);
        $this->assertSame(
            'no-store, max-age=0',
            $page['headers']['Cache-Control'],
        );
        $this->assertStringContainsString(
            "frame-ancestors 'none'",
            $page['headers']['Content-Security-Policy'],
        );
        $script = $server->handle(
            $runId,
            1,
            $token,
            'GET',
            '/review.js',
            [],
            '',
        );
        $this->assertStringContainsString(
            'URL.revokeObjectURL',
            $script['body'],
        );
        $this->assertStringNotContainsString(
            'localStorage',
            $script['body'],
        );
        $this->assertStringNotContainsString(
            'indexedDB',
            $script['body'],
        );

        $unauthorized = $server->handle(
            $runId,
            1,
            $token,
            'GET',
            '/api/manifest',
            ['authorization' => 'Bearer '.str_repeat('b', 64)],
            '',
        );
        $this->assertSame(401, $unauthorized['status']);
        $proxied = $server->handle(
            $runId,
            1,
            $token,
            'GET',
            '/api/manifest',
            [
                'authorization' => 'Bearer '.$token,
                'x-forwarded-for' => '127.0.0.1',
            ],
            '',
        );
        $this->assertSame(403, $proxied['status']);
        $publicHost = $server->handle(
            $runId,
            1,
            $token,
            'GET',
            '/api/manifest',
            [
                'authorization' => 'Bearer '.$token,
                'host' => '192.168.1.20:8080',
            ],
            '',
        );
        $this->assertSame(403, $publicHost['status']);
        $manifest = $server->handle(
            $runId,
            1,
            $token,
            'GET',
            '/api/manifest',
            ['authorization' => 'Bearer '.$token],
            '',
        );
        $this->assertSame(200, $manifest['status']);
        $this->assertStringContainsString(
            'Hello, how are you today?',
            $manifest['body'],
        );
        $this->assertStringNotContainsString(
            'development-listener',
            $manifest['body'],
        );
        $store = $this->app->make(SpeechBenchmarkRunStore::class);
        $choices = array_map(
            static fn (array $fixture): array => [
                'review_id' => $fixture['review_id'],
                'intelligible' => true,
                'acceptable' => true,
                'critical_defect' => false,
            ],
            $store->reviewManifest($runId),
        );
        $submitted = $server->handle(
            $runId,
            1,
            $token,
            'POST',
            '/api/submit',
            [
                'authorization' => 'Bearer '.$token,
                'content-type' => 'application/json',
            ],
            json_encode(['choices' => $choices], JSON_THROW_ON_ERROR),
        );
        $this->assertTrue($submitted['review_completed'] ?? false);
        $mutated = $choices;
        $mutated[0]['acceptable'] = false;
        $replayed = $server->handle(
            $runId,
            1,
            $token,
            'POST',
            '/api/submit',
            [
                'authorization' => 'Bearer '.$token,
                'content-type' => 'application/json',
            ],
            json_encode(['choices' => $mutated], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(409, $replayed['status']);
        $store->recordReview($runId, 2, $choices);
        $root = (string) config('speech.benchmark.ephemeral_root');
        $runDirectory = $root.'/'.$runId;
        chmod($runDirectory, 0500);
        try {
            $this->app->make(SpeechBenchmarkRunner::class)->complete($runId);
            $this->fail('A failed deletion must block completion.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'The encrypted benchmark bundle deletion failed.',
                $exception->getMessage(),
            );
        } finally {
            chmod($runDirectory, 0700);
        }
        $this->assertNotSame(
            [],
            glob($root.'/receipts/'.$runId.'.failed.*.json') ?: [],
        );
        $fixturePath = (glob($runDirectory.'/*.fixture') ?: [])[0] ?? null;
        $this->assertIsString($fixturePath);
        file_put_contents($fixturePath, 'modified-ciphertext', LOCK_EX);
        chmod($fixturePath, 0600);
        try {
            $this->app->make(SpeechBenchmarkRunner::class)->complete($runId);
            $this->fail('Modified ciphertext must block completion.');
        } catch (\RuntimeException) {
            $this->assertDirectoryDoesNotExist($runDirectory);
            $this->assertFileExists(
                $root.'/receipts/'.$runId.'.json',
            );
        }
        Http::assertSentCount(96);
    }

    public function test_expired_bundle_is_not_reviewed_and_is_purged(): void
    {
        CarbonImmutable::setTestNow('2026-07-31T00:00:00Z');
        $this->configureAuthorizedBenchmark();
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response(
                self::wav(),
                200,
                ['Content-Type' => 'audio/wav'],
            ),
        ]);
        $summary = $this->app
            ->make(SpeechBenchmarkRunner::class)
            ->prepare();
        $runId = (string) $summary['run_id'];
        $root = (string) config('speech.benchmark.ephemeral_root');
        CarbonImmutable::setTestNow('2026-08-01T00:00:01Z');
        $store = $this->app->make(SpeechBenchmarkRunStore::class);

        try {
            $store->reviewManifest($runId);
            $this->fail('Expired fixtures must not be reviewable.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'The encrypted benchmark run expired.',
                $exception->getMessage(),
            );
        }

        $this->assertSame(
            ['purged' => 1, 'failed' => 0],
            $store->purgeExpired(),
        );
        $this->assertDirectoryDoesNotExist($root.'/'.$runId);
        $receipt = json_decode(
            (string) file_get_contents(
                $root.'/receipts/'.$runId.'.json',
            ),
            true,
            8,
            JSON_THROW_ON_ERROR,
        );
        $this->assertTrue($receipt['success']);
        $this->assertSame('expired', $receipt['reason']);
    }

    public function test_partial_preparation_cannot_restart_billable_operations(): void
    {
        $this->configureAuthorizedBenchmark();
        Http::preventStrayRequests();
        $attempt = 0;
        Http::fake(function () use (&$attempt) {
            $attempt++;

            return $attempt === 1
                ? Http::response(
                    self::wav(),
                    200,
                    ['Content-Type' => 'audio/wav'],
                )
                : Http::response('', 400);
        });
        $store = $this->app->make(SpeechBenchmarkRunStore::class);
        $runner = $this->app->make(SpeechBenchmarkRunner::class);
        $runId = $store->generateRunId();

        try {
            $runner->prepare($runId);
            $this->fail('Partial preparation must fail.');
        } catch (\RuntimeException) {
            $state = $store->load($runId, true);
            $this->assertSame('preparation_failed', $state['status']);
            $this->assertCount(1, $state['fixtures']);
            $this->assertSame(2, $state['accounting']['logical_operations']);
        }
        try {
            $runner->prepare($runId);
            $this->fail('Partial preparation must not restart.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'The benchmark run cannot be restarted.',
                $exception->getMessage(),
            );
        }
        Http::assertSentCount(2);
        $receipt = $store->purge($runId, 'cancelled');
        $this->assertTrue($receipt['success']);
    }

    public function test_external_secure_root_is_required_before_http(): void
    {
        $this->configureAuthorizedBenchmark();
        config()->set(
            'speech.benchmark.ephemeral_root',
            storage_path('speech-benchmark'),
        );
        if (! is_dir(storage_path('speech-benchmark'))) {
            mkdir(storage_path('speech-benchmark'), 0700);
            $this->temporaryDirectories[] = storage_path('speech-benchmark');
        }
        Http::preventStrayRequests();

        $this->expectException(\RuntimeException::class);
        try {
            $this->app->make(SpeechBenchmarkRunner::class)->prepare();
        } finally {
            Http::assertNothingSent();
        }
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
        $ephemeralRoot = $this->temporaryDirectory('fixtures');
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

    private function temporaryDirectory(string $suffix): string
    {
        $path = sys_get_temp_dir()
            .'/pr009-benchmark-'.Str::random(20).'-'.$suffix;
        mkdir($path, 0700);
        chmod($path, 0700);
        $this->temporaryDirectories[] = $path;

        return $path;
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path) || is_link($path)) {
            return;
        }
        chmod($path, 0700);
        $entries = scandir($path);
        if (! is_array($entries)) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path.'/'.$entry;
            if (is_dir($child) && ! is_link($child)) {
                $this->deleteDirectory($child);
            } else {
                unlink($child);
            }
        }
        rmdir($path);
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
