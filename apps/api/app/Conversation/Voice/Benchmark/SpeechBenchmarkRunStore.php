<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Benchmark;

use App\Conversation\Voice\Providers\Azure\AzureSpeechConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\Encrypter;
use RuntimeException;
use Throwable;

final readonly class SpeechBenchmarkRunStore
{
    private const int STATE_VERSION = 1;

    public function __construct(
        private Encrypter $encrypter,
        private AzureSpeechConfiguration $configuration,
        private SpeechBenchmarkCorpus $corpus,
    ) {}

    public function generateRunId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param  array<string, mixed>  $accounting
     * @return array<string, mixed>
     */
    public function begin(string $runId, array $accounting): array
    {
        $this->assertRunId($runId);
        $root = $this->secureRoot();
        $receipt = $this->receiptPath($root, $runId);
        $priorReceipts = glob($root.'/receipts/'.$runId.'.*') ?: [];
        if (
            is_file($receipt)
            || is_link($receipt)
            || $priorReceipts !== []
        ) {
            throw new RuntimeException('The benchmark run identifier was already used.');
        }

        $directory = $root.'/'.$runId;
        if (file_exists($directory) || is_link($directory)) {
            $state = $this->load($runId, true);
            if (($state['status'] ?? null) === 'prepared') {
                return $state;
            }

            throw new RuntimeException('The benchmark run cannot be restarted.');
        }

        if (! mkdir($directory, 0700) || ! chmod($directory, 0700)) {
            throw new RuntimeException('The encrypted benchmark run directory could not be created.');
        }

        $lock = $directory.'/run.lock';
        if (file_put_contents($lock, '', LOCK_EX) === false || ! chmod($lock, 0600)) {
            throw new RuntimeException('The benchmark run lock could not be created.');
        }

        $createdAt = CarbonImmutable::now('UTC')->startOfSecond();
        $state = [
            'schema_version' => self::STATE_VERSION,
            'run_id' => $runId,
            'benchmark_id' => $this->configuration->string(
                'speech.benchmark.id',
            ),
            'status' => 'preparing',
            'created_at' => $createdAt->toIso8601ZuluString(),
            'expires_at' => $createdAt->addHours(24)->toIso8601ZuluString(),
            'bindings' => $this->bindings(),
            'fixtures' => [],
            'accounting' => $accounting,
            'reviews' => [],
        ];
        $this->writeState($directory, $state);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $accounting
     */
    public function appendFixture(
        string $runId,
        string $fixtureId,
        string $category,
        string $voice,
        string $audio,
        array $accounting,
    ): void {
        $this->mutate($runId, function (array $state, string $directory) use (
            $fixtureId,
            $category,
            $voice,
            $audio,
            $accounting,
        ): array {
            if (($state['status'] ?? null) !== 'preparing') {
                throw new RuntimeException('The benchmark run is not preparing.');
            }

            foreach ($state['fixtures'] ?? [] as $fixture) {
                if (
                    is_array($fixture)
                    && ($fixture['fixture_id'] ?? null) === $fixtureId
                    && ($fixture['voice'] ?? null) === $voice
                ) {
                    throw new RuntimeException('The benchmark fixture already exists.');
                }
            }

            $ciphertext = $this->encrypter->encryptString(
                base64_encode($audio),
            );
            $filename = bin2hex(random_bytes(24)).'.fixture';
            $path = $directory.'/'.$filename;
            $this->writeFile($path, $ciphertext);
            $state['fixtures'][] = [
                'review_id' => bin2hex(random_bytes(16)),
                'fixture_id' => $fixtureId,
                'category' => $category,
                'voice' => $voice,
                'ciphertext_file' => $filename,
                'ciphertext_sha256' => hash('sha256', $ciphertext),
                'plaintext_sha256' => hash('sha256', $audio),
            ];
            $state['accounting'] = $accounting;

            return $state;
        });
    }

    /**
     * @param  array<string, mixed>  $accounting
     * @return array<string, mixed>
     */
    public function markPrepared(string $runId, array $accounting): array
    {
        return $this->mutate(
            $runId,
            function (array $state) use ($accounting): array {
                if (
                    ($state['status'] ?? null) !== 'preparing'
                    || count($state['fixtures'] ?? []) !== 96
                ) {
                    throw new RuntimeException('The benchmark preparation is incomplete.');
                }

                $state['status'] = 'prepared';
                $state['accounting'] = $accounting;
                $state['ciphertext_manifest_sha256'] = $this
                    ->manifestHash($state);

                return $state;
            },
        );
    }

    /**
     * @param  array<string, mixed>  $accounting
     */
    public function markPreparationFailed(
        string $runId,
        array $accounting,
    ): void {
        try {
            $this->mutate(
                $runId,
                function (array $state) use ($accounting): array {
                    if (($state['status'] ?? null) === 'preparing') {
                        $state['status'] = 'preparation_failed';
                        $state['accounting'] = $accounting;
                    }

                    return $state;
                },
                allowExpired: true,
            );
        } catch (Throwable) {
            // Preserve the encrypted partial bundle for explicit purge.
        }
    }

    /**
     * @return list<array{
     *   review_id: string,
     *   fixture_id: string,
     *   source_text: string,
     *   voice: string
     * }>
     */
    public function reviewManifest(string $runId): array
    {
        $state = $this->load($runId);
        if (
            ! in_array($state['status'] ?? null, ['prepared', 'reviewed'], true)
        ) {
            throw new RuntimeException('The benchmark run is not reviewable.');
        }

        $sourceById = [];
        foreach ($this->corpus->load() as $utterance) {
            $sourceById[$utterance['id']] = $utterance['text'];
        }

        return array_values(array_map(
            static function (array $fixture) use ($sourceById): array {
                $fixtureId = (string) $fixture['fixture_id'];

                return [
                    'review_id' => (string) $fixture['review_id'],
                    'fixture_id' => $fixtureId,
                    'source_text' => (string) ($sourceById[$fixtureId] ?? ''),
                    'voice' => (string) $fixture['voice'],
                ];
            },
            $state['fixtures'],
        ));
    }

    public function audio(string $runId, string $reviewId): string
    {
        $state = $this->load($runId);
        if (
            ! in_array($state['status'] ?? null, ['prepared', 'reviewed'], true)
        ) {
            throw new RuntimeException('The benchmark run is not reviewable.');
        }

        foreach ($state['fixtures'] as $fixture) {
            if (
                is_array($fixture)
                && hash_equals(
                    (string) ($fixture['review_id'] ?? ''),
                    $reviewId,
                )
            ) {
                return $this->decryptFixture($runId, $fixture);
            }
        }

        throw new RuntimeException('The benchmark fixture is unavailable.');
    }

    /**
     * @param  array<array-key, mixed>  $choices
     * @return array<string, int>
     */
    public function recordReview(
        string $runId,
        int $listenerSlot,
        array $choices,
    ): array {
        if (! in_array($listenerSlot, [1, 2], true)) {
            throw new RuntimeException('The listener slot is invalid.');
        }

        $state = $this->mutate(
            $runId,
            function (array $state) use ($listenerSlot, $choices): array {
                if (
                    ! in_array(
                        $state['status'] ?? null,
                        ['prepared', 'reviewed'],
                        true,
                    )
                ) {
                    throw new RuntimeException('The benchmark run is not reviewable.');
                }

                $normalized = $this->normalizeChoices($state, $choices);
                $slot = (string) $listenerSlot;
                if (isset($state['reviews'][$slot])) {
                    if ($state['reviews'][$slot] !== $normalized) {
                        throw new RuntimeException('The listener review cannot be changed.');
                    }

                    return $state;
                }

                $state['reviews'][$slot] = $normalized;
                if (count($state['reviews']) === 2) {
                    $state['status'] = 'reviewed';
                }

                return $state;
            },
        );

        return $this->reviewAggregate($state);
    }

    public function beginCompletion(
        string $runId,
    ): SpeechBenchmarkCompletionLease {
        $directory = $this->runDirectory($runId);
        $this->assertRunDirectory($directory);
        $lock = $this->openLock($directory);
        if (! flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('The benchmark run lock is unavailable.');
        }

        try {
            $state = $this->load($runId);
            $this->assertCompletionState($state);
            $state['status'] = 'completing';
            $this->writeState($directory, $state);

            return new SpeechBenchmarkCompletionLease($state, $lock);
        } catch (Throwable $exception) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function completionState(string $runId): array
    {
        $state = $this->load($runId);
        $this->assertCompletionState($state);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, int>
     */
    public function reviewAggregate(array $state): array
    {
        $reviews = $state['reviews'] ?? [];
        if (! is_array($reviews) || count($reviews) !== 2) {
            return [
                'output_count' => 0,
                'reviewer_count' => count($reviews),
                'intelligibility_accepted_by_both' => 0,
                'acceptability_accepted_by_both' => 0,
                'critical_pronunciation_defects' => -1,
            ];
        }

        $bySlot = array_values($reviews);
        $intelligible = 0;
        $acceptable = 0;
        $critical = 0;
        foreach ($bySlot[0] as $reviewId => $first) {
            $second = $bySlot[1][$reviewId] ?? null;
            if (! is_array($second)) {
                throw new RuntimeException('The listener reviews do not match.');
            }
            $intelligible += $first['intelligible'] && $second['intelligible']
                ? 1
                : 0;
            $acceptable += $first['acceptable'] && $second['acceptable']
                ? 1
                : 0;
            $critical += $first['critical_defect']
                || $second['critical_defect'] ? 1 : 0;
        }

        return [
            'output_count' => count($bySlot[0]),
            'reviewer_count' => 2,
            'intelligibility_accepted_by_both' => $intelligible,
            'acceptability_accepted_by_both' => $acceptable,
            'critical_pronunciation_defects' => $critical,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function safeSummary(array $state): array
    {
        return [
            'run_id' => $state['run_id'],
            'benchmark_id' => $state['benchmark_id'],
            'status' => $state['status'],
            'fixture_count' => count($state['fixtures'] ?? []),
            'listener_count' => count($state['reviews'] ?? []),
            'logical_operations' => (int) (
                $state['accounting']['logical_operations'] ?? 0
            ),
            'http_attempts' => (int) (
                $state['accounting']['http_attempts'] ?? 0
            ),
            'configuration_sha256' => $state['bindings'][
                'configuration_sha256'
            ],
            'corpus_sha256' => $state['bindings']['corpus_sha256'],
            'ciphertext_manifest_sha256' => $state[
                'ciphertext_manifest_sha256'
            ] ?? null,
            'expires_at' => $state['expires_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function preparedAudio(
        string $runId,
        array $state,
        string $fixtureId,
        string $voice,
    ): string {
        foreach ($state['fixtures'] as $fixture) {
            if (
                is_array($fixture)
                && ($fixture['fixture_id'] ?? null) === $fixtureId
                && ($fixture['voice'] ?? null) === $voice
            ) {
                return $this->decryptFixture($runId, $fixture);
            }
        }

        throw new RuntimeException('The prepared benchmark fixture is unavailable.');
    }

    /**
     * @return array<string, mixed>
     */
    public function purge(string $runId, string $reason): array
    {
        $this->assertRunId($runId);
        $root = $this->secureRoot();
        $directory = $root.'/'.$runId;
        $existingReceipt = $this->successfulReceipt($root, $runId);
        if ($existingReceipt !== null) {
            if (! file_exists($directory)) {
                return $existingReceipt;
            }

            throw new RuntimeException(
                'The deleted benchmark run identifier was recreated.',
            );
        }
        if (
            file_exists($this->receiptPath($root, $runId))
            || is_link($this->receiptPath($root, $runId))
        ) {
            throw new RuntimeException('The deletion receipt is invalid.');
        }
        $manifestHash = null;
        $deletedFiles = 0;
        $success = true;
        $lock = null;

        if (is_dir($directory) && ! is_link($directory)) {
            try {
                $this->assertRunDirectory($directory);
                $lock = $this->openLock($directory);
                if (! flock($lock, LOCK_EX | LOCK_NB)) {
                    fclose($lock);
                    $lock = null;
                    throw new RuntimeException(
                        'The benchmark run is currently active.',
                    );
                }
            } catch (Throwable) {
                $success = false;
            }
            try {
                $state = $this->load($runId, true);
                $manifestHash = $state['ciphertext_manifest_sha256']
                    ?? $this->manifestHash($state);
            } catch (Throwable) {
                $manifestHash = null;
            }

            if (is_resource($lock)) {
                $entries = scandir($directory);
                if (! is_array($entries)) {
                    $success = false;
                } else {
                    foreach ($entries as $entry) {
                        if ($entry === '.' || $entry === '..') {
                            continue;
                        }
                        if ($entry === 'run.lock') {
                            continue;
                        }
                        $path = $directory.'/'.$entry;
                        if (
                            is_link($path)
                            || ! is_file($path)
                            || ! @unlink($path)
                        ) {
                            $success = false;
                        } else {
                            $deletedFiles++;
                        }
                    }
                }
                if ($success) {
                    if (@unlink($directory.'/run.lock')) {
                        $deletedFiles++;
                    } else {
                        $success = false;
                    }
                }
                if ($success && ! @rmdir($directory)) {
                    $success = false;
                }
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        } elseif (file_exists($directory) || is_link($directory)) {
            $success = false;
        }

        $receipt = [
            'schema_version' => 1,
            'run_id' => $runId,
            'ciphertext_manifest_sha256' => $manifestHash,
            'deleted_at' => CarbonImmutable::now('UTC')
                ->startOfSecond()
                ->toIso8601ZuluString(),
            'deleted_file_count' => $deletedFiles,
            'success' => $success,
            'reason' => $this->safeReason($reason),
        ];
        $this->writeReceipt($root, $runId, $receipt, $success);

        if (! $success) {
            throw new RuntimeException('The encrypted benchmark bundle deletion failed.');
        }

        return $receipt;
    }

    /**
     * @return array{purged: int, failed: int}
     */
    public function purgeExpired(): array
    {
        $root = $this->secureRoot();
        $purged = 0;
        $failed = 0;
        $entries = scandir($root);
        if (! is_array($entries)) {
            throw new RuntimeException('The benchmark fixture root is unreadable.');
        }

        foreach ($entries as $runId) {
            if (preg_match('/\A[a-f0-9]{32}\z/', $runId) !== 1) {
                continue;
            }
            try {
                $state = $this->load($runId, true);
                if (! $this->expired($state)) {
                    continue;
                }
                $this->purge($runId, 'expired');
                $purged++;
            } catch (Throwable) {
                $failed++;
            }
        }

        return ['purged' => $purged, 'failed' => $failed];
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $runId, bool $allowExpired = false): array
    {
        $this->assertRunId($runId);
        $directory = $this->runDirectory($runId);
        $this->assertRunDirectory($directory);
        $path = $directory.'/state.enc';
        if (
            is_link($path)
            || ! is_file($path)
            || (fileperms($path) & 0777) !== 0600
            || filesize($path) === false
            || filesize($path) > 2 * 1024 * 1024
        ) {
            throw new RuntimeException('The encrypted benchmark state is invalid.');
        }

        $ciphertext = file_get_contents($path);
        if (! is_string($ciphertext)) {
            throw new RuntimeException('The encrypted benchmark state is unreadable.');
        }

        try {
            $state = $this->encrypter->decrypt($ciphertext, true);
        } catch (Throwable) {
            throw new RuntimeException('The encrypted benchmark state is invalid.');
        }

        if (
            ! is_array($state)
            || ($state['schema_version'] ?? null) !== self::STATE_VERSION
            || ($state['run_id'] ?? null) !== $runId
            || ($state['benchmark_id'] ?? null)
                !== $this->configuration->string('speech.benchmark.id')
            || ! is_array($state['bindings'] ?? null)
            || ! is_array($state['fixtures'] ?? null)
            || ! is_array($state['accounting'] ?? null)
            || ! is_array($state['reviews'] ?? null)
            || ! $this->validLifetime($state)
        ) {
            throw new RuntimeException('The encrypted benchmark state is invalid.');
        }

        if (! $allowExpired && $this->expired($state)) {
            throw new RuntimeException('The encrypted benchmark run expired.');
        }

        return $state;
    }

    /**
     * @param  callable(array<string, mixed>, string): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function mutate(
        string $runId,
        callable $callback,
        bool $allowExpired = false,
    ): array {
        $directory = $this->runDirectory($runId);
        $this->assertRunDirectory($directory);
        $lock = $this->openLock($directory);
        if (! flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('The benchmark run lock is unavailable.');
        }

        try {
            $state = $this->load($runId, $allowExpired);
            $updated = $callback($state, $directory);
            $this->writeState($directory, $updated);

            return $updated;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return resource
     */
    private function openLock(string $directory): mixed
    {
        $lockPath = $directory.'/run.lock';
        if (
            is_link($lockPath)
            || ! is_file($lockPath)
            || (fileperms($lockPath) & 0777) !== 0600
        ) {
            throw new RuntimeException('The benchmark run lock is invalid.');
        }
        $lock = fopen($lockPath, 'r+');
        if ($lock === false) {
            throw new RuntimeException('The benchmark run lock is unavailable.');
        }

        return $lock;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function writeState(string $directory, array $state): void
    {
        $ciphertext = $this->encrypter->encrypt($state, true);
        $this->writeFile($directory.'/state.enc', $ciphertext);
    }

    private function writeFile(string $path, string $contents): void
    {
        if (is_link($path) || is_dir($path)) {
            throw new RuntimeException('The encrypted benchmark path is invalid.');
        }

        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.pending';
        if (
            file_put_contents($temporary, $contents, LOCK_EX) === false
            || ! chmod($temporary, 0600)
            || ! rename($temporary, $path)
        ) {
            @unlink($temporary);
            throw new RuntimeException('The encrypted benchmark file could not be written.');
        }
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function decryptFixture(string $runId, array $fixture): string
    {
        $filename = $fixture['ciphertext_file'] ?? null;
        if (
            ! is_string($filename)
            || preg_match('/\A[a-f0-9]{48}\.fixture\z/', $filename) !== 1
        ) {
            throw new RuntimeException('The encrypted benchmark fixture is invalid.');
        }

        $path = $this->runDirectory($runId).'/'.$filename;
        if (
            is_link($path)
            || ! is_file($path)
            || (fileperms($path) & 0777) !== 0600
        ) {
            throw new RuntimeException('The encrypted benchmark fixture is invalid.');
        }
        $ciphertext = file_get_contents($path);
        if (
            ! is_string($ciphertext)
            || ! hash_equals(
                (string) ($fixture['ciphertext_sha256'] ?? ''),
                hash('sha256', $ciphertext),
            )
        ) {
            throw new RuntimeException('The encrypted benchmark fixture changed.');
        }

        try {
            $encoded = $this->encrypter->decryptString($ciphertext);
            $audio = base64_decode($encoded, true);
        } catch (Throwable) {
            $audio = false;
        }
        if (
            ! is_string($audio)
            || ! hash_equals(
                (string) ($fixture['plaintext_sha256'] ?? ''),
                hash('sha256', $audio),
            )
        ) {
            throw new RuntimeException('The encrypted benchmark fixture is invalid.');
        }

        return $audio;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function assertBindings(array $state): void
    {
        if ($state['bindings'] !== $this->bindings()) {
            throw new RuntimeException('The benchmark run bindings changed.');
        }
        if (
            ! hash_equals(
                (string) ($state['ciphertext_manifest_sha256'] ?? ''),
                $this->manifestHash($state),
            )
        ) {
            throw new RuntimeException('The benchmark fixture manifest changed.');
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function assertCiphertexts(array $state): void
    {
        foreach ($state['fixtures'] as $fixture) {
            if (! is_array($fixture)) {
                throw new RuntimeException('The benchmark fixture manifest is invalid.');
            }
            $this->decryptFixture((string) $state['run_id'], $fixture);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function assertCompletionState(array $state): void
    {
        if (
            ($state['status'] ?? null) !== 'reviewed'
            || count($state['reviews'] ?? []) !== 2
            || count($state['fixtures'] ?? []) !== 96
        ) {
            throw new RuntimeException('The benchmark run is not complete-ready.');
        }

        $this->assertBindings($state);
        $this->assertFixturePlan($state);
        $this->assertCiphertexts($state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function assertFixturePlan(array $state): void
    {
        $expected = [];
        $voices = config('speech.azure.tts.fixture_voices', []);
        if ($voices !== ['en-IN-NeerjaNeural', 'en-IN-PrabhatNeural']) {
            throw new RuntimeException('The locked fixture voices changed.');
        }
        foreach ($this->corpus->load() as $utterance) {
            foreach ($voices as $voice) {
                $expected[] = [
                    'fixture_id' => $utterance['id'],
                    'category' => $utterance['category'],
                    'voice' => $voice,
                ];
            }
        }

        $reviewIds = [];
        foreach ($state['fixtures'] as $index => $fixture) {
            $plan = $expected[$index] ?? null;
            if (
                ! is_array($fixture)
                || ! is_array($plan)
                || ($fixture['fixture_id'] ?? null) !== $plan['fixture_id']
                || ($fixture['category'] ?? null) !== $plan['category']
                || ($fixture['voice'] ?? null) !== $plan['voice']
                || ! is_string($fixture['review_id'] ?? null)
                || preg_match(
                    '/\A[a-f0-9]{32}\z/',
                    $fixture['review_id'],
                ) !== 1
                || isset($reviewIds[$fixture['review_id']])
            ) {
                throw new RuntimeException('The benchmark fixture plan changed.');
            }
            $reviewIds[$fixture['review_id']] = true;
        }
    }

    /**
     * @return array<string, string>
     */
    private function bindings(): array
    {
        return [
            'configuration_sha256' => $this->configuration
                ->configurationFingerprint(),
            'corpus_sha256' => $this->fileHash(
                $this->configuration->string(
                    'speech.benchmark.corpus_path',
                ),
            ),
            'plan_sha256' => $this->fileHash(
                $this->configuration->string('speech.benchmark.plan_path'),
            ),
            'adr_0009_proposal_sha256' => $this->configuration->string(
                'speech.benchmark.approved_listener_workflow_sha256',
            ),
            'runner_revision' => $this->configuration->string(
                'speech.benchmark.runner_revision',
            ),
            'runner_commit' => $this->configuration->string(
                'speech.benchmark.runner_commit',
            ),
            'transform_revision' => $this->configuration->string(
                'speech.benchmark.transform_revision',
            ),
            'composer_lock_sha256' => $this->fileHash(
                $this->configuration->string(
                    'speech.benchmark.composer_lock_path',
                ),
            ),
            'pnpm_lock_sha256' => $this->fileHash(
                $this->configuration->string(
                    'speech.benchmark.pnpm_lock_path',
                ),
            ),
        ];
    }

    private function fileHash(string $path): string
    {
        $hash = is_file($path) ? hash_file('sha256', $path) : false;
        if (! is_string($hash)) {
            throw new RuntimeException('A benchmark-bound file is unavailable.');
        }

        return $hash;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function manifestHash(array $state): string
    {
        $manifest = [];
        foreach ($state['fixtures'] ?? [] as $fixture) {
            if (! is_array($fixture)) {
                throw new RuntimeException('The benchmark fixture manifest is invalid.');
            }
            $manifest[] = [
                'review_id' => $fixture['review_id'] ?? null,
                'fixture_id' => $fixture['fixture_id'] ?? null,
                'category' => $fixture['category'] ?? null,
                'voice' => $fixture['voice'] ?? null,
                'ciphertext_file' => $fixture['ciphertext_file'] ?? null,
                'ciphertext_sha256' => $fixture['ciphertext_sha256'] ?? null,
                'plaintext_sha256' => $fixture['plaintext_sha256'] ?? null,
            ];
        }

        return hash(
            'sha256',
            json_encode(
                $manifest,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<array-key, mixed>  $choices
     * @return array<string, array{
     *   intelligible: bool,
     *   acceptable: bool,
     *   critical_defect: bool
     * }>
     */
    private function normalizeChoices(array $state, array $choices): array
    {
        if (count($choices) !== 96) {
            throw new RuntimeException('Every benchmark fixture must be reviewed.');
        }

        $expected = [];
        foreach ($state['fixtures'] as $fixture) {
            $expected[(string) $fixture['review_id']] = true;
        }
        $normalized = [];
        foreach ($choices as $choice) {
            if (
                ! is_array($choice)
                || ! is_string($choice['review_id'] ?? null)
                || ! array_key_exists('intelligible', $choice)
                || ! is_bool($choice['intelligible'])
                || ! array_key_exists('acceptable', $choice)
                || ! is_bool($choice['acceptable'])
                || ! array_key_exists('critical_defect', $choice)
                || ! is_bool($choice['critical_defect'])
                || ! isset($expected[$choice['review_id']])
                || isset($normalized[$choice['review_id']])
            ) {
                throw new RuntimeException('A benchmark review disposition is invalid.');
            }
            $normalized[$choice['review_id']] = [
                'intelligible' => $choice['intelligible'],
                'acceptable' => $choice['acceptable'],
                'critical_defect' => $choice['critical_defect'],
            ];
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function expired(array $state): bool
    {
        try {
            $expiry = CarbonImmutable::parse(
                (string) ($state['expires_at'] ?? ''),
                'UTC',
            );
        } catch (Throwable) {
            return true;
        }

        return $expiry->lessThanOrEqualTo(CarbonImmutable::now('UTC'));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function validLifetime(array $state): bool
    {
        try {
            $created = CarbonImmutable::parse(
                (string) ($state['created_at'] ?? ''),
                'UTC',
            );
            $expiry = CarbonImmutable::parse(
                (string) ($state['expires_at'] ?? ''),
                'UTC',
            );
        } catch (Throwable) {
            return false;
        }

        return $expiry->getTimestamp() - $created->getTimestamp() === 86_400
            && $created->lessThanOrEqualTo(CarbonImmutable::now('UTC'));
    }

    private function secureRoot(): string
    {
        $path = $this->configuration->string(
            'speech.benchmark.ephemeral_root',
        );
        $real = realpath($path);
        $repository = dirname(dirname(base_path()));
        $forbidden = [$repository, storage_path(), public_path()];
        if (
            $path === ''
            || ! str_starts_with($path, '/')
            || $real === false
            || is_link($path)
            || ! is_dir($real)
            || ! is_readable($real)
            || ! is_writable($real)
            || (fileperms($real) & 0777) !== 0700
            || (
                function_exists('posix_geteuid')
                && fileowner($real) !== posix_geteuid()
            )
        ) {
            throw new RuntimeException('The benchmark fixture root is invalid.');
        }
        foreach ($forbidden as $prefix) {
            $resolved = realpath($prefix);
            if (
                is_string($resolved)
                && (
                    $real === $resolved
                    || str_starts_with($real.'/', $resolved.'/')
                )
            ) {
                throw new RuntimeException('The benchmark fixture root is not external.');
            }
        }

        return $real;
    }

    private function runDirectory(string $runId): string
    {
        $this->assertRunId($runId);

        return $this->secureRoot().'/'.$runId;
    }

    private function assertRunDirectory(string $directory): void
    {
        if (
            is_link($directory)
            || ! is_dir($directory)
            || (fileperms($directory) & 0777) !== 0700
            || (
                function_exists('posix_geteuid')
                && fileowner($directory) !== posix_geteuid()
            )
        ) {
            throw new RuntimeException('The encrypted benchmark run directory is invalid.');
        }
    }

    private function assertRunId(string $runId): void
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $runId) !== 1) {
            throw new RuntimeException('The benchmark run identifier is invalid.');
        }
    }

    /**
     * @param  array<string, mixed>  $receipt
     */
    private function writeReceipt(
        string $root,
        string $runId,
        array $receipt,
        bool $success,
    ): void {
        $directory = $root.'/receipts';
        if (! is_dir($directory)) {
            if (
                file_exists($directory)
                || is_link($directory)
                || ! mkdir($directory, 0700)
                || ! chmod($directory, 0700)
            ) {
                throw new RuntimeException('The deletion receipt directory is invalid.');
            }
        }
        $this->assertRunDirectory($directory);
        $json = json_encode(
            $receipt,
            JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES,
        )."\n";
        $path = $success
            ? $this->receiptPath($root, $runId)
            : $directory.'/'.$runId.'.failed.'
                .CarbonImmutable::now('UTC')->format('Ymd\THis\Z').'.'
                .bin2hex(random_bytes(6)).'.json';
        $this->writeFile($path, $json);
    }

    private function receiptPath(string $root, string $runId): string
    {
        return $root.'/receipts/'.$runId.'.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function successfulReceipt(string $root, string $runId): ?array
    {
        $path = $this->receiptPath($root, $runId);
        if (
            is_link($path)
            || ! is_file($path)
            || (fileperms($path) & 0777) !== 0600
            || filesize($path) === false
            || filesize($path) > 16 * 1024
        ) {
            return null;
        }
        $body = file_get_contents($path);
        if (! is_string($body)) {
            return null;
        }
        try {
            $receipt = json_decode(
                $body,
                true,
                8,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            return null;
        }
        if (
            ! is_array($receipt)
            || ($receipt['run_id'] ?? null) !== $runId
            || ($receipt['success'] ?? null) !== true
        ) {
            return null;
        }

        return $receipt;
    }

    private function safeReason(string $reason): string
    {
        if (
            preg_match(
                '/\A(?:completed|failed|expired|cancelled|revoked)\z/',
                $reason,
            ) !== 1
        ) {
            throw new RuntimeException('The benchmark deletion reason is invalid.');
        }

        return $reason;
    }
}
