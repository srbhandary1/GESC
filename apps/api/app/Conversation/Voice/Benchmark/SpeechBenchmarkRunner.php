<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Benchmark;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\Providers\Azure\AzureRecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\Azure\AzureSpeechConfiguration;
use App\Conversation\Voice\Providers\Azure\AzureTextToSpeechProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;
use Carbon\CarbonImmutable;
use JsonException;
use RuntimeException;

final class SpeechBenchmarkRunner
{
    private int $logicalOperations = 0;

    private int $httpAttempts = 0;

    private int $firstAttemptSuccesses = 0;

    private float $estimatedSpendInr = 0.0;

    /** @var array<string, int> */
    private array $failures = [];

    /**
     * @var list<array{
     *   phase: string,
     *   category: string,
     *   condition: string,
     *   voice: string,
     *   reference_words: int,
     *   edits: int,
     *   exact: bool,
     *   material_hallucination: bool,
     *   latency_ms: int
     * }>
     */
    private array $sttMeasurements = [];

    /** @var list<int> */
    private array $ttsLatencies = [];

    /** @var array<string, true> */
    private array $responseMetadataHashes = [];

    /** @var array<string, int> */
    private array $listenerAggregate = [];

    private ?CarbonImmutable $runExpiresAt = null;

    public function __construct(
        private readonly AzureSpeechConfiguration $configuration,
        private readonly SpeechBenchmarkCorpus $corpus,
        private readonly SyntheticAudioTransformer $transformer,
        private readonly TranscriptMetrics $transcriptMetrics,
        private readonly AzureTextToSpeechProvider $textToSpeech,
        private readonly AzureRecordedSpeechToTextProvider $speechToText,
        private readonly PcmWavInspector $wavInspector,
        private readonly SpeechBenchmarkRunStore $runStore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function prepare(?string $requestedRunId = null): array
    {
        $this->reset();
        $this->configuration->assertLiveAuthorized(false);
        $utterances = $this->corpus->load();
        $voices = $this->lockedVoices();
        $runId = $requestedRunId ?? $this->runStore->generateRunId();
        $state = $this->runStore->begin($runId, $this->accounting());
        if (($state['status'] ?? null) === 'prepared') {
            return $this->runStore->safeSummary($state);
        }
        $this->runExpiresAt = CarbonImmutable::parse(
            (string) $state['expires_at'],
            'UTC',
        );

        try {
            foreach ($utterances as $utterance) {
                foreach ($voices as $voice) {
                    $tts = $this->tts(
                        $utterance['id'],
                        $utterance['text'],
                        $voice,
                    );
                    if (! $tts instanceof TextToSpeechResult) {
                        throw new RuntimeException(
                            'A benchmark TTS fixture could not be prepared.',
                        );
                    }
                    $audio = (string) $tts->audioBytes;
                    $this->wavInspector->inspect($audio);
                    $this->runStore->appendFixture(
                        $runId,
                        $utterance['id'],
                        $utterance['category'],
                        $voice,
                        $audio,
                        $this->accounting(),
                    );
                }
            }
            $state = $this->runStore->markPrepared(
                $runId,
                $this->accounting(),
            );
        } catch (\Throwable $exception) {
            $this->runStore->markPreparationFailed(
                $runId,
                $this->accounting(),
            );
            throw $exception;
        }

        return $this->runStore->safeSummary($state);
    }

    /**
     * @return array<string, mixed>
     */
    public function complete(string $runId): array
    {
        $this->reset();
        $reason = 'failed';
        $state = null;
        $lease = null;
        try {
            $this->configuration->assertLiveAuthorized(true);
            $lease = $this->runStore->beginCompletion($runId);
            $state = $lease->state;
            $this->runExpiresAt = CarbonImmutable::parse(
                (string) $state['expires_at'],
                'UTC',
            );
            $this->restoreAccounting($state['accounting']);
            $this->listenerAggregate = $this->runStore->reviewAggregate(
                $state,
            );
            $this->assertListenerEvidence($this->listenerAggregate);
            $utterances = $this->corpus->load();
            $voices = $this->lockedVoices();

            foreach ($utterances as $utterance) {
                foreach ($voices as $voice) {
                    $preparedAudio = $this->runStore->preparedAudio(
                        $runId,
                        $state,
                        $utterance['id'],
                        $voice,
                    );
                    foreach (['clean', 'snr20', 'snr10_lowpass'] as $condition) {
                        $seed = hash(
                            'sha256',
                            $this->configuration->string('speech.benchmark.id')
                                .$utterance['id'].$voice.$condition,
                        );
                        $audio = $this->transformer->transform(
                            $preparedAudio,
                            $seed,
                            $condition,
                        );
                        $this->stt(
                            phase: 'corpus',
                            fixtureId: $utterance['id'],
                            category: $utterance['category'],
                            reference: $utterance['text'],
                            voice: $voice,
                            condition: $condition,
                            audio: $audio,
                        );
                    }
                }
            }

            $byId = [];
            foreach ($utterances as $utterance) {
                $byId[$utterance['id']] = $utterance;
            }
            $repetitions = config(
                'speech.benchmark.repetition_fixture_ids',
                [],
            );
            if (! is_array($repetitions) || count($repetitions) !== 4) {
                throw new RuntimeException('The repetition fixtures changed.');
            }

            for ($round = 1; $round <= 20; $round++) {
                foreach ($repetitions as $fixtureId) {
                    $utterance = is_string($fixtureId)
                        ? ($byId[$fixtureId] ?? null)
                        : null;
                    if (! is_array($utterance)) {
                        throw new RuntimeException(
                            'A repetition fixture is unavailable.',
                        );
                    }

                    $tts = $this->tts(
                        'repeat-'.$round.'-'.$fixtureId,
                        $utterance['text'],
                        'en-IN-NeerjaNeural',
                    );
                    if (! $tts instanceof TextToSpeechResult) {
                        continue;
                    }

                    $audio = $this->transformer->transform(
                        (string) $tts->audioBytes,
                        hash(
                            'sha256',
                            $this->configuration->string('speech.benchmark.id')
                                .$fixtureId.'en-IN-NeerjaNeuralclean',
                        ),
                        'clean',
                    );
                    $this->stt(
                        phase: 'repetition',
                        fixtureId: 'repeat-'.$round.'-'.$fixtureId,
                        category: $utterance['category'],
                        reference: $utterance['text'],
                        voice: 'en-IN-NeerjaNeural',
                        condition: 'clean',
                        audio: $audio,
                    );
                }
            }

            $report = $this->report(
                (string) $state['created_at'],
                gmdate('Y-m-d\TH:i:s\Z'),
            );
            $reason = $report['passed'] === true ? 'completed' : 'failed';
        } finally {
            $lease?->release();
            $receipt = $this->runStore->purge($runId, $reason);
        }

        $report['ephemeral_content'] = [
            'storage_mode' => 'application_encrypted_ephemeral_bundle',
            'content_files_written' => 96,
            'bundle_deleted' => $receipt['success'],
            'deletion_receipt' => $receipt,
        ];
        $this->writeSafeReport($report);

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    public function preflightCompletion(string $runId): array
    {
        $this->reset();
        $this->configuration->assertLiveAuthorized(true);
        $state = $this->runStore->completionState($runId);
        $this->restoreAccounting($state['accounting']);
        $aggregate = $this->runStore->reviewAggregate($state);
        $this->assertListenerEvidence($aggregate);
        $summary = $this->runStore->safeSummary($state);
        $summary['status'] = 'complete_ready';
        $summary['listener_evidence'] = $aggregate;

        return $summary;
    }

    /**
     * @return list<string>
     */
    private function lockedVoices(): array
    {
        $voices = config('speech.azure.tts.fixture_voices', []);
        if (
            $voices !== ['en-IN-NeerjaNeural', 'en-IN-PrabhatNeural']
        ) {
            throw new RuntimeException('The locked fixture voices changed.');
        }

        return $voices;
    }

    /**
     * @return array<string, mixed>
     */
    private function accounting(): array
    {
        $metadataHashes = array_keys($this->responseMetadataHashes);
        sort($metadataHashes);

        return [
            'logical_operations' => $this->logicalOperations,
            'http_attempts' => $this->httpAttempts,
            'first_attempt_successes' => $this->firstAttemptSuccesses,
            'estimated_spend_inr' => $this->estimatedSpendInr,
            'safe_failures' => $this->failures,
            'tts_latencies' => $this->ttsLatencies,
            'response_metadata_sha256' => $metadataHashes,
        ];
    }

    /**
     * @param  array<string, mixed>  $accounting
     */
    private function restoreAccounting(array $accounting): void
    {
        $latencies = $accounting['tts_latencies'] ?? null;
        $failures = $accounting['safe_failures'] ?? null;
        $hashes = $accounting['response_metadata_sha256'] ?? null;
        $httpAttempts = $accounting['http_attempts'] ?? null;
        $firstAttemptSuccesses = $accounting[
            'first_attempt_successes'
        ] ?? null;
        $estimatedSpend = $accounting['estimated_spend_inr'] ?? null;
        if (
            ($accounting['logical_operations'] ?? null) !== 96
            || ! is_int($httpAttempts)
            || $httpAttempts < 96
            || $httpAttempts > 192
            || ! is_int($firstAttemptSuccesses)
            || $firstAttemptSuccesses < 0
            || $firstAttemptSuccesses > 96
            || ! is_numeric($estimatedSpend)
            || (float) $estimatedSpend < 0.0
            || (float) $estimatedSpend > $this->configuration->float(
                'speech.benchmark.approved_spend_cap_inr',
            )
            || ! is_array($latencies)
            || count($latencies) !== 96
            || array_filter(
                $latencies,
                static fn (mixed $value): bool => ! is_int($value)
                    || $value < 0,
            )
                !== []
            || ! is_array($failures)
            || $failures !== []
            || ! is_array($hashes)
        ) {
            throw new RuntimeException('The benchmark operation state is invalid.');
        }

        $this->logicalOperations = 96;
        $this->httpAttempts = $httpAttempts;
        $this->firstAttemptSuccesses = $firstAttemptSuccesses;
        $this->estimatedSpendInr = (float) $estimatedSpend;
        $this->ttsLatencies = array_values($latencies);
        foreach ($hashes as $hash) {
            if (
                ! is_string($hash)
                || preg_match('/\A[a-f0-9]{64}\z/', $hash) !== 1
            ) {
                throw new RuntimeException(
                    'The benchmark response metadata state is invalid.',
                );
            }
            $this->responseMetadataHashes[$hash] = true;
        }
    }

    private function reset(): void
    {
        $this->logicalOperations = 0;
        $this->httpAttempts = 0;
        $this->firstAttemptSuccesses = 0;
        $this->estimatedSpendInr = 0.0;
        $this->failures = [];
        $this->sttMeasurements = [];
        $this->ttsLatencies = [];
        $this->responseMetadataHashes = [];
        $this->listenerAggregate = [];
        $this->runExpiresAt = null;
    }

    /**
     * @param  array<string, int>  $aggregate
     */
    private function assertListenerEvidence(array $aggregate): void
    {
        if ($this->listenerEvidence() !== $aggregate) {
            throw new RuntimeException(
                'The listener evidence does not match the reviewed fixtures.',
            );
        }
    }

    private function tts(
        string $interactionId,
        string $text,
        string $voice,
    ): ?TextToSpeechResult {
        $cost = mb_strlen($text) * $this->configuration->float(
            'speech.benchmark.pricing.inr_per_tts_character',
        );
        $result = $this->providerOperation(
            $cost,
            fn (): TextToSpeechResult => $this->textToSpeech->synthesize(
                new TextToSpeechRequest($interactionId, $text, $voice),
            ),
        );

        if (! $result instanceof TextToSpeechResult) {
            return null;
        }

        if (
            ! $result->playbackAvailable
            || $result->mimeType !== 'audio/wav'
            || ! is_string($result->audioBytes)
        ) {
            $this->failure('tts_result_invalid');

            return null;
        }

        $this->ttsLatencies[] = $result->usage()->latencyMilliseconds;
        if (is_string($result->responseMetadataSha256)) {
            $this->responseMetadataHashes[$result->responseMetadataSha256]
                = true;
        }

        return $result;
    }

    private function stt(
        string $phase,
        string $fixtureId,
        string $category,
        string $reference,
        string $voice,
        string $condition,
        string $audio,
    ): void {
        $durationMs = $this->wavInspector->inspect($audio)->durationMs;
        $cost = ($durationMs / 1000.0) * $this->configuration->float(
            'speech.benchmark.pricing.inr_per_stt_second',
        );
        $result = $this->providerOperation(
            $cost,
            fn (): RecordedSpeechToTextResult => $this->speechToText->transcribe(
                new RecordedSpeechToTextRequest(
                    interactionId: $fixtureId,
                    audioBytes: $audio,
                    mimeType: 'audio/wav',
                    language: 'en-IN',
                ),
            ),
        );

        if (! $result instanceof RecordedSpeechToTextResult) {
            return;
        }

        $metrics = $this->transcriptMetrics->compare(
            $reference,
            $result->transcript,
        );
        $this->sttMeasurements[] = [
            'phase' => $phase,
            'category' => $category,
            'condition' => $condition,
            'voice' => $voice,
            'reference_words' => $metrics['reference_words'],
            'edits' => $metrics['edits'],
            'exact' => $metrics['exact'],
            'material_hallucination' => $metrics['material_hallucination'],
            'latency_ms' => $result->usage->latencyMilliseconds,
        ];
        if (is_string($result->responseMetadataSha256)) {
            $this->responseMetadataHashes[$result->responseMetadataSha256]
                = true;
        }
    }

    private function providerOperation(float $attemptCost, callable $call): mixed
    {
        $this->configuration->assertLiveAuthorized(
            $this->listenerAggregate !== [],
        );
        if (
            $this->runExpiresAt instanceof CarbonImmutable
            && $this->runExpiresAt->lessThanOrEqualTo(
                CarbonImmutable::now('UTC'),
            )
        ) {
            throw new RuntimeException('The benchmark run expired.');
        }

        $this->logicalOperations++;
        if (
            $this->logicalOperations
                > $this->configuration->integer(
                    'speech.benchmark.logical_operation_cap',
                )
        ) {
            throw new RuntimeException('The logical operation cap was exceeded.');
        }

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $approvedCap = min(
                $this->configuration->float(
                    'speech.benchmark.maximum_spend_inr',
                ),
                $this->configuration->float(
                    'speech.benchmark.approved_spend_cap_inr',
                ),
            );

            if ($this->estimatedSpendInr + $attemptCost > $approvedCap) {
                throw new RuntimeException('The approved spend cap would be exceeded.');
            }

            $this->httpAttempts++;
            if (
                $this->httpAttempts
                    > $this->configuration->integer(
                        'speech.benchmark.http_attempt_cap',
                    )
            ) {
                throw new RuntimeException('The HTTP attempt cap was exceeded.');
            }
            $this->estimatedSpendInr += $attemptCost;

            try {
                $result = $call();
                if ($attempt === 1) {
                    $this->firstAttemptSuccesses++;
                }

                return $result;
            } catch (ConversationProviderException $exception) {
                if ($exception->retryable && $attempt === 1) {
                    continue;
                }

                $this->failure($exception->safeCode);

                return null;
            }
        }

        $this->failure('provider_retry_exhausted');

        return null;
    }

    private function failure(string $safeCode): void
    {
        $this->failures[$safeCode] = ($this->failures[$safeCode] ?? 0) + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function report(string $startedAt, string $finishedAt): array
    {
        $corpusMeasurements = array_values(array_filter(
            $this->sttMeasurements,
            static fn (array $measurement): bool => $measurement['phase']
                === 'corpus',
        ));
        $conditionMetrics = [];
        $thresholdsPassed = true;

        foreach (
            [
                'clean' => [0.05, 0.10],
                'snr20' => [0.10, 0.18],
                'snr10_lowpass' => [0.20, 0.30],
            ] as $condition => [$overallLimit, $categoryLimit]
        ) {
            $conditionRows = $this->where(
                $corpusMeasurements,
                'condition',
                $condition,
            );
            $overallWer = $this->wer($conditionRows);
            $categories = [];

            foreach (
                ['foundation', 'beginner', 'india', 'number', 'repair'] as $category
            ) {
                $categories[$category] = $this->wer(
                    $this->where($conditionRows, 'category', $category),
                );
            }

            $conditionPass = count($conditionRows) === 96
                && $overallWer <= $overallLimit
                && max($categories) <= $categoryLimit;
            $thresholdsPassed = $thresholdsPassed && $conditionPass;
            $conditionMetrics[$condition] = [
                'clip_count' => count($conditionRows),
                'wer' => $overallWer,
                'category_wer' => $categories,
                'threshold_passed' => $conditionPass,
            ];
        }

        $cleanRows = $this->where(
            $corpusMeasurements,
            'condition',
            'clean',
        );
        $sttLatencies = array_column(
            $this->sttMeasurements,
            'latency_ms',
        );
        $hallucinations = count(array_filter(
            $corpusMeasurements,
            static fn (array $measurement): bool => $measurement[
                'material_hallucination'
            ],
        ));
        $listener = $this->listenerAggregate !== []
            ? $this->listenerAggregate
            : $this->listenerEvidence();
        $listenerPass = ($listener['output_count'] ?? 0) === 96
            && ($listener['reviewer_count'] ?? 0) === 2
            && ($listener['intelligibility_accepted_by_both'] ?? 0) >= 92
            && ($listener['acceptability_accepted_by_both'] ?? 0) >= 87
            && ($listener['critical_pronunciation_defects'] ?? -1) === 0;
        $projectedCost = 20 * $this->configuration->float(
            'speech.benchmark.pricing.inr_per_stt_second',
        ) + 250 * $this->configuration->float(
            'speech.benchmark.pricing.inr_per_tts_character',
        );
        $firstAttemptRate = $this->logicalOperations > 0
            ? $this->firstAttemptSuccesses / $this->logicalOperations
            : 0.0;
        $performancePass = $this->percentile($sttLatencies, 0.95) <= 4000
            && $this->maximum($sttLatencies) <= 8000
            && $this->percentile($this->ttsLatencies, 0.95) <= 2500
            && $this->maximum($this->ttsLatencies) <= 5000;
        $completenessPass = $this->logicalOperations === 544
            && count($this->sttMeasurements) === 368
            && count($this->ttsLatencies) === 176
            && $this->failures === []
            && $this->httpAttempts <= 1088;
        $qualityPass = $thresholdsPassed
            && $this->wer($cleanRows) <= 0.05
            && $hallucinations === 0;
        $reliabilityPass = $firstAttemptRate >= 0.99;
        $costPass = $projectedCost <= 1.0
            && $this->estimatedSpendInr <= $this->configuration->float(
                'speech.benchmark.approved_spend_cap_inr',
            );
        $passed = $completenessPass
            && $qualityPass
            && $performancePass
            && $reliabilityPass
            && $costPass
            && $listenerPass;
        $metadataHashes = array_keys($this->responseMetadataHashes);
        sort($metadataHashes);

        return [
            'schema_version' => 1,
            'benchmark_id' => $this->configuration->string(
                'speech.benchmark.id',
            ),
            'passed' => $passed,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'provider' => 'azure-speech',
            'region' => 'centralindia',
            'stt_api_version' => '2025-10-15',
            'stt_locale' => 'en-IN',
            'tts_voices' => [
                'en-IN-NeerjaNeural',
                'en-IN-PrabhatNeural',
            ],
            'approved_adr_proposal_sha256' => $this->configuration->string(
                'speech.benchmark.approved_adr_proposal_sha256',
            ),
            'benchmark_plan_sha256' => $this->configuration->string(
                'speech.benchmark.approved_plan_sha256',
            ),
            'corpus_sha256' => $this->configuration->string(
                'speech.benchmark.expected_corpus_sha256',
            ),
            'configuration_sha256' => $this->configuration
                ->configurationFingerprint(),
            'runner_revision' => $this->configuration->string(
                'speech.benchmark.runner_revision',
            ),
            'runner_commit' => $this->configuration->string(
                'speech.benchmark.runner_commit',
            ),
            'transform_revision' => $this->configuration->string(
                'speech.benchmark.transform_revision',
            ),
            'environment' => [
                'php_version' => PHP_VERSION,
                'os_family' => PHP_OS_FAMILY,
                'toolchain' => config('speech.benchmark.toolchain'),
                'composer_lock_sha256' => hash_file(
                    'sha256',
                    $this->configuration->string(
                        'speech.benchmark.composer_lock_path',
                    ),
                ),
                'pnpm_lock_sha256' => hash_file(
                    'sha256',
                    $this->configuration->string(
                        'speech.benchmark.pnpm_lock_path',
                    ),
                ),
            ],
            'operations' => [
                'logical' => $this->logicalOperations,
                'http_attempts' => $this->httpAttempts,
                'first_attempt_success_rate' => $firstAttemptRate,
                'safe_failures' => $this->failures,
            ],
            'provider_response_metadata_sha256' => $metadataHashes,
            'stt' => [
                'conditions' => $conditionMetrics,
                'exact_match_rate' => $this->exactRate($corpusMeasurements),
                'material_hallucinations' => $hallucinations,
                'latency_ms' => $this->latencySummary($sttLatencies),
            ],
            'tts' => [
                'valid_outputs' => count($this->ttsLatencies),
                'round_trip_wer' => $this->wer($cleanRows),
                'latency_ms' => $this->latencySummary($this->ttsLatencies),
                'listener_evidence' => $listener,
            ],
            'cost' => [
                'estimated_run_inr' => round($this->estimatedSpendInr, 4),
                'approved_cap_inr' => $this->configuration->float(
                    'speech.benchmark.approved_spend_cap_inr',
                ),
                'projected_turn_inr' => round($projectedCost, 4),
                'price_snapshot_id' => $this->configuration->string(
                    'speech.benchmark.evidence.price_snapshot',
                ),
                'conversion_source' => $this->configuration->string(
                    'speech.benchmark.pricing.conversion_source',
                ),
                'conversion_instant' => $this->configuration->string(
                    'speech.benchmark.pricing.conversion_instant',
                ),
            ],
            'gates' => [
                'completeness' => $completenessPass,
                'quality' => $qualityPass,
                'performance' => $performancePass,
                'reliability' => $reliabilityPass,
                'cost' => $costPass,
                'listener' => $listenerPass,
                'content_scan_evidence_id' => $this->configuration->string(
                    'speech.benchmark.evidence.content_scan',
                ),
            ],
            'evidence_ids' => [
                'subscription' => $this->configuration->string(
                    'speech.benchmark.evidence.subscription',
                ),
                'contracting_entity_review' => $this->configuration->string(
                    'speech.benchmark.evidence.contracting_entity',
                ),
                'agreement_sha256' => $this->configuration->string(
                    'speech.benchmark.evidence.agreement_sha256',
                ),
                'dpa_sha256' => $this->configuration->string(
                    'speech.benchmark.evidence.dpa_sha256',
                ),
                'subprocessor_sha256' => $this->configuration->string(
                    'speech.benchmark.evidence.subprocessor_sha256',
                ),
                'privacy_legal_review' => $this->configuration->string(
                    'speech.benchmark.evidence.privacy_legal_review',
                ),
                'logging_disabled' => $this->configuration->string(
                    'speech.benchmark.evidence.logging_disabled',
                ),
                'credential_version' => $this->configuration->string(
                    'speech.benchmark.evidence.credential_version',
                ),
                'spend_approval' => $this->configuration->string(
                    'speech.benchmark.evidence.spend_approval',
                ),
            ],
            'ephemeral_content' => [
                'storage_mode' => 'memory_only',
                'content_files_written' => 0,
                'deletion_evidence' => 'process_memory_released_on_exit',
            ],
            'limitations' => [
                'azure_tts_to_azure_stt_is_circular',
                'synthetic_speech_is_not_learner_accent_evidence',
                'managed_stt_model_may_rotate',
                'development_listeners_are_not_independent_review',
                'passing_does_not_authorize_learner_data_or_production',
            ],
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function listenerEvidence(): array
    {
        $path = $this->configuration->string(
            'speech.benchmark.listener_evidence_path',
        );
        $body = is_file($path) ? file_get_contents($path) : false;

        try {
            $evidence = is_string($body)
                ? json_decode($body, true, 8, JSON_THROW_ON_ERROR)
                : null;
        } catch (JsonException) {
            $evidence = null;
        }

        if (
            ! is_array($evidence)
            || ($evidence['benchmark_id'] ?? null)
                !== $this->configuration->string('speech.benchmark.id')
            || ! is_array($evidence['reviewer_ids'] ?? null)
            || count(array_unique($evidence['reviewer_ids'])) !== 2
        ) {
            return [
                'output_count' => 0,
                'reviewer_count' => 0,
                'intelligibility_accepted_by_both' => 0,
                'acceptability_accepted_by_both' => 0,
                'critical_pronunciation_defects' => -1,
            ];
        }

        return [
            'output_count' => (int) ($evidence['output_count'] ?? 0),
            'reviewer_count' => 2,
            'intelligibility_accepted_by_both' => (int) (
                $evidence['intelligibility_accepted_by_both'] ?? 0
            ),
            'acceptability_accepted_by_both' => (int) (
                $evidence['acceptability_accepted_by_both'] ?? 0
            ),
            'critical_pronunciation_defects' => (int) (
                $evidence['critical_pronunciation_defects'] ?? -1
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function where(array $rows, string $key, string $value): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => ($row[$key] ?? null) === $value,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function wer(array $rows): float
    {
        $words = array_sum(array_column($rows, 'reference_words'));
        $edits = array_sum(array_column($rows, 'edits'));

        return $words > 0 ? $edits / $words : 1.0;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function exactRate(array $rows): float
    {
        return $rows !== []
            ? count(array_filter(
                $rows,
                static fn (array $row): bool => (bool) ($row['exact'] ?? false),
            )) / count($rows)
            : 0.0;
    }

    /**
     * @param  list<int>  $values
     * @return array{p50: int, p95: int, maximum: int}
     */
    private function latencySummary(array $values): array
    {
        return [
            'p50' => $this->percentile($values, 0.50),
            'p95' => $this->percentile($values, 0.95),
            'maximum' => $this->maximum($values),
        ];
    }

    /**
     * @param  list<int>  $values
     */
    private function percentile(array $values, float $percentile): int
    {
        if ($values === []) {
            return PHP_INT_MAX;
        }

        sort($values, SORT_NUMERIC);
        $index = (int) ceil(count($values) * $percentile) - 1;

        return $values[max(0, min(count($values) - 1, $index))];
    }

    /**
     * @param  list<int>  $values
     */
    private function maximum(array $values): int
    {
        return $values === [] ? PHP_INT_MAX : max($values);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeSafeReport(array $report): void
    {
        $path = $this->configuration->string(
            'speech.benchmark.safe_report_path',
        );
        $directory = dirname($path);
        if (
            ! is_dir($directory)
            || ! is_writable($directory)
            || is_link($path)
        ) {
            throw new RuntimeException('The safe report destination is invalid.');
        }

        $temporary = $path.'.pending';
        $json = json_encode(
            $report,
            JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES,
        )."\n";

        if (
            file_put_contents($temporary, $json, LOCK_EX) === false
            || ! chmod($temporary, 0600)
            || ! rename($temporary, $path)
        ) {
            throw new RuntimeException('The safe benchmark report could not be written.');
        }
    }
}
