<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers\Azure;

use App\Conversation\Providers\ConversationProviderException;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use JsonException;

final readonly class AzureSpeechConfiguration
{
    private const string PROVIDER_KEY = 'azure-speech';

    public function __construct(
        private Application $application,
        private Repository $config,
    ) {}

    /**
     * @return list<string>
     */
    public function preflightErrors(bool $requireListenerEvidence = true): array
    {
        $errors = [];
        $expectedEnvironment = $this->string('speech.benchmark.environment');

        if (
            $expectedEnvironment !== 'speech-benchmark'
            || $this->application->environment() !== $expectedEnvironment
        ) {
            $errors[] = 'benchmark_environment_invalid';
        }

        if (
            ! $this->boolean('speech.benchmark.enabled')
            || ! $this->boolean('speech.azure.live_requests_enabled')
        ) {
            $errors[] = 'live_requests_not_authorized';
        }

        if ($this->string('speech.azure.region') !== 'centralindia') {
            $errors[] = 'region_invalid';
        }

        if (
            $this->string('speech.azure.stt.api_version') !== '2025-10-15'
            || $this->string('speech.azure.stt.locale') !== 'en-IN'
            || $this->string('speech.azure.stt.profanity_filter_mode') !== 'None'
            || $this->string('speech.azure.tts.locale') !== 'en-IN'
            || $this->string('speech.azure.tts.output_format')
                !== 'riff-24khz-16bit-mono-pcm'
        ) {
            $errors[] = 'locked_provider_configuration_invalid';
        }

        if ($this->validatedEndpointHost() === null) {
            $errors[] = 'endpoint_invalid';
        }

        if (! in_array(
            $this->string('speech.azure.credential_kind'),
            ['entra-token', 'subscription-key'],
            true,
        )) {
            $errors[] = 'credential_kind_invalid';
        }

        if ($this->validatedSecretPath() === null) {
            $errors[] = 'secret_file_invalid';
        }

        foreach (
            [
                'subscription',
                'contracting_entity',
                'privacy_legal_review',
                'logging_disabled',
                'credential_version',
                'spend_approval',
                'price_snapshot',
                'content_scan',
            ] as $evidence
        ) {
            if (! $this->safeIdentifier(
                $this->string('speech.benchmark.evidence.'.$evidence),
            )) {
                $errors[] = $evidence.'_evidence_missing';
            }
        }

        foreach (
            ['agreement_sha256', 'dpa_sha256', 'subprocessor_sha256'] as $hash
        ) {
            if (! $this->sha256(
                $this->string('speech.benchmark.evidence.'.$hash),
            )) {
                $errors[] = $hash.'_invalid';
            }
        }

        if (
            $this->integer('speech.benchmark.logical_operation_cap') !== 544
            || $this->integer('speech.benchmark.http_attempt_cap') !== 1088
            || $this->float('speech.benchmark.maximum_spend_inr') !== 1000.0
        ) {
            $errors[] = 'benchmark_caps_invalid';
        }

        if (
            preg_match(
                '/\A[a-f0-9]{40}\z/',
                $this->string('speech.benchmark.runner_commit'),
            ) !== 1
        ) {
            $errors[] = 'runner_commit_invalid';
        }

        foreach (
            [
                'php' => '8.4.23',
                'composer' => '2.10.2',
                'node' => '24.18.1',
                'pnpm' => '11.18.0',
            ] as $tool => $version
        ) {
            if (
                $this->string('speech.benchmark.toolchain.'.$tool) !== $version
            ) {
                $errors[] = 'toolchain_manifest_invalid';
            }
        }

        foreach (
            ['composer_lock_path', 'pnpm_lock_path'] as $lockPath
        ) {
            if (! is_file($this->string('speech.benchmark.'.$lockPath))) {
                $errors[] = 'dependency_lock_missing';
            }
        }

        $approvedSpend = $this->float(
            'speech.benchmark.approved_spend_cap_inr',
        );
        if ($approvedSpend <= 0.0 || $approvedSpend > 1000.0) {
            $errors[] = 'spend_approval_invalid';
        }

        $errors = array_merge(
            $errors,
            $this->operationalEvidenceErrors($approvedSpend),
        );

        foreach (
            ['inr_per_stt_second', 'inr_per_tts_character'] as $price
        ) {
            if ($this->float('speech.benchmark.pricing.'.$price) <= 0.0) {
                $errors[] = $price.'_invalid';
            }
        }

        if (! $this->safeIdentifier(
            $this->string('speech.benchmark.pricing.conversion_source'),
        )) {
            $errors[] = 'conversion_source_invalid';
        }

        if (! $this->isoInstant(
            $this->string('speech.benchmark.pricing.conversion_instant'),
        )) {
            $errors[] = 'conversion_instant_invalid';
        }

        $planPath = $this->string('speech.benchmark.plan_path');
        if (
            ! is_file($planPath)
            || ! hash_equals(
                $this->string('speech.benchmark.approved_plan_sha256'),
                (string) hash_file('sha256', $planPath),
            )
        ) {
            $errors[] = 'benchmark_plan_hash_mismatch';
        }

        $corpusPath = $this->string('speech.benchmark.corpus_path');
        $expectedCorpusHash = $this->string(
            'speech.benchmark.expected_corpus_sha256',
        );
        if (
            ! $this->sha256($expectedCorpusHash)
            || ! is_file($corpusPath)
            || ! hash_equals(
                $expectedCorpusHash,
                (string) hash_file('sha256', $corpusPath),
            )
        ) {
            $errors[] = 'benchmark_corpus_hash_mismatch';
        }

        if (! $this->absolutePath(
            $this->string('speech.benchmark.safe_report_path'),
        )) {
            $errors[] = 'safe_report_path_invalid';
        }

        if (
            ! $this->secureExternalDirectory(
                $this->string('speech.benchmark.ephemeral_root'),
            )
            || ! $this->boolean(
                'speech.benchmark.ephemeral_storage_acknowledged',
            )
        ) {
            $errors[] = 'ephemeral_storage_invalid';
        }

        if (
            $this->string(
                'speech.benchmark.approved_listener_workflow_sha256',
            ) !== 'a0848d462f9363cc2fb9a2babd2f50fd8ae7ad4ed99ee2cd3abaaeb828413e0f'
        ) {
            $errors[] = 'listener_workflow_hash_mismatch';
        }

        if (
            $requireListenerEvidence
            && ! $this->externalReadableFile(
                $this->string('speech.benchmark.listener_evidence_path'),
            )
        ) {
            $errors[] = 'listener_evidence_missing';
        }

        return array_values(array_unique($errors));
    }

    public function assertLiveAuthorized(
        bool $requireListenerEvidence = false,
    ): void {
        if ($this->preflightErrors($requireListenerEvidence) !== []) {
            throw $this->failure('provider_not_authorized');
        }
    }

    /**
     * @return array<string, string>
     */
    public function authenticationHeaders(): array
    {
        $this->assertLiveAuthorized();
        $path = $this->validatedSecretPath();

        if ($path === null) {
            throw $this->failure('provider_not_authorized');
        }

        $secret = file_get_contents($path);
        if (! is_string($secret)) {
            throw $this->failure('provider_not_authorized');
        }

        $secret = trim($secret);
        if ($secret === '' || strlen($secret) > 8192) {
            throw $this->failure('provider_not_authorized');
        }

        return $this->string('speech.azure.credential_kind') === 'entra-token'
            ? ['Authorization' => 'Bearer '.$secret]
            : ['Ocp-Apim-Subscription-Key' => $secret];
    }

    public function endpoint(): string
    {
        return rtrim($this->string('speech.azure.endpoint'), '/');
    }

    public function string(string $key): string
    {
        $value = $this->config->get($key);

        return is_string($value) ? trim($value) : '';
    }

    public function integer(string $key): int
    {
        $value = $this->config->get($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    public function float(string $key): float
    {
        $value = $this->config->get($key);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public function boolean(string $key): bool
    {
        return filter_var(
            $this->config->get($key),
            FILTER_VALIDATE_BOOL,
        );
    }

    public function configurationFingerprint(): string
    {
        $values = [
            'provider' => self::PROVIDER_KEY,
            'endpoint_host' => $this->validatedEndpointHost(),
            'region' => $this->string('speech.azure.region'),
            'stt_api_version' => $this->string(
                'speech.azure.stt.api_version',
            ),
            'stt_locale' => $this->string('speech.azure.stt.locale'),
            'stt_profanity' => $this->string(
                'speech.azure.stt.profanity_filter_mode',
            ),
            'tts_locale' => $this->string('speech.azure.tts.locale'),
            'tts_voice' => $this->string('speech.azure.tts.voice'),
            'tts_format' => $this->string('speech.azure.tts.output_format'),
            'credential_version' => $this->string(
                'speech.benchmark.evidence.credential_version',
            ),
            'credential_kind' => $this->string(
                'speech.azure.credential_kind',
            ),
            'benchmark_id' => $this->string('speech.benchmark.id'),
            'approved_adr_proposal_sha256' => $this->string(
                'speech.benchmark.approved_adr_proposal_sha256',
            ),
            'approved_plan_sha256' => $this->string(
                'speech.benchmark.approved_plan_sha256',
            ),
            'approved_listener_workflow_sha256' => $this->string(
                'speech.benchmark.approved_listener_workflow_sha256',
            ),
            'corpus_sha256' => $this->string(
                'speech.benchmark.expected_corpus_sha256',
            ),
            'runner_revision' => $this->string(
                'speech.benchmark.runner_revision',
            ),
            'runner_commit' => $this->string(
                'speech.benchmark.runner_commit',
            ),
            'transform_revision' => $this->string(
                'speech.benchmark.transform_revision',
            ),
            'logical_operation_cap' => $this->integer(
                'speech.benchmark.logical_operation_cap',
            ),
            'http_attempt_cap' => $this->integer(
                'speech.benchmark.http_attempt_cap',
            ),
            'maximum_spend_inr' => $this->float(
                'speech.benchmark.maximum_spend_inr',
            ),
            'approved_spend_cap_inr' => $this->float(
                'speech.benchmark.approved_spend_cap_inr',
            ),
            'vendor_evidence_sha256' => $this->externalFileHash(
                $this->string('speech.benchmark.vendor_evidence_path'),
            ),
            'spend_approval_sha256' => $this->externalFileHash(
                $this->string('speech.benchmark.spend_approval_path'),
            ),
            'safe_report_path_sha256' => hash(
                'sha256',
                $this->string('speech.benchmark.safe_report_path'),
            ),
            'ephemeral_root_sha256' => hash(
                'sha256',
                $this->string('speech.benchmark.ephemeral_root'),
            ),
            'evidence' => $this->config->get(
                'speech.benchmark.evidence',
                [],
            ),
            'pricing' => $this->config->get(
                'speech.benchmark.pricing',
                [],
            ),
            'toolchain' => $this->config->get(
                'speech.benchmark.toolchain',
                [],
            ),
        ];

        return hash(
            'sha256',
            json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }

    private function validatedEndpointHost(): ?string
    {
        $endpoint = $this->string('speech.azure.endpoint');
        $allowedHost = strtolower(
            $this->string('speech.azure.allowed_host'),
        );
        $parts = parse_url($endpoint);

        if (
            ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! is_string($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            return null;
        }

        $path = $parts['path'] ?? '';
        if ($path !== '' && $path !== '/') {
            return null;
        }

        $host = strtolower($parts['host']);
        if (
            $host === ''
            || ! hash_equals($allowedHost, $host)
            || (
                ! str_ends_with($host, '.cognitiveservices.azure.com')
                && $host !== 'centralindia.api.cognitive.microsoft.com'
            )
        ) {
            return null;
        }

        return $host;
    }

    private function validatedSecretPath(): ?string
    {
        $path = $this->string('speech.azure.secret_file');
        $realPath = realpath($path);
        $repositoryRoot = dirname(dirname(base_path()));

        if (
            ! $this->absolutePath($path)
            || $realPath === false
            || is_link($path)
            || ! is_file($realPath)
            || ! is_readable($realPath)
            || str_starts_with($realPath.'/', $repositoryRoot.'/')
            || (fileperms($realPath) & 0777) !== 0600
            || filesize($realPath) === false
            || filesize($realPath) < 1
            || filesize($realPath) > 8192
        ) {
            return null;
        }

        if (
            function_exists('posix_geteuid')
            && fileowner($realPath) !== posix_geteuid()
        ) {
            return null;
        }

        return $realPath;
    }

    private function externalReadableFile(string $path): bool
    {
        $realPath = realpath($path);
        $repositoryRoot = dirname(dirname(base_path()));

        return $this->absolutePath($path)
            && $realPath !== false
            && ! is_link($path)
            && is_file($realPath)
            && is_readable($realPath)
            && ! str_starts_with($realPath.'/', $repositoryRoot.'/')
            && (fileperms($realPath) & 0777) === 0600
            && (
                ! function_exists('posix_geteuid')
                || fileowner($realPath) === posix_geteuid()
            );
    }

    private function externalFileHash(string $path): string
    {
        if (! $this->externalReadableFile($path)) {
            return '';
        }
        $hash = hash_file('sha256', $path);

        return is_string($hash) ? $hash : '';
    }

    private function secureExternalDirectory(string $path): bool
    {
        $realPath = realpath($path);
        $repositoryRoot = dirname(dirname(base_path()));
        $forbidden = [
            $repositoryRoot,
            storage_path(),
            public_path(),
        ];
        if (
            ! $this->absolutePath($path)
            || $realPath === false
            || is_link($path)
            || ! is_dir($realPath)
            || ! is_readable($realPath)
            || ! is_writable($realPath)
            || (fileperms($realPath) & 0777) !== 0700
            || (
                function_exists('posix_geteuid')
                && fileowner($realPath) !== posix_geteuid()
            )
        ) {
            return false;
        }
        foreach ($forbidden as $prefix) {
            $resolved = realpath($prefix);
            if (
                is_string($resolved)
                && (
                    $realPath === $resolved
                    || str_starts_with($realPath.'/', $resolved.'/')
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function operationalEvidenceErrors(float $approvedSpend): array
    {
        $vendor = $this->externalJson(
            $this->string('speech.benchmark.vendor_evidence_path'),
        );
        $spend = $this->externalJson(
            $this->string('speech.benchmark.spend_approval_path'),
        );
        $errors = [];

        if (
            ! is_array($vendor)
            || ($vendor['schema_version'] ?? null) !== 1
            || ($vendor['provider'] ?? null) !== 'azure-speech'
            || ($vendor['region'] ?? null) !== 'centralindia'
            || ($vendor['approved_for_synthetic_benchmark'] ?? null) !== true
            || ($vendor['data_logging_enabled'] ?? null) !== false
            || ($vendor['model_improvement_enabled'] ?? null) !== false
            || ($vendor['custom_speech_enabled'] ?? null) !== false
            || ($vendor['subscription_evidence_id'] ?? null)
                !== $this->string(
                    'speech.benchmark.evidence.subscription',
                )
            || ($vendor['contracting_entity_review_id'] ?? null)
                !== $this->string(
                    'speech.benchmark.evidence.contracting_entity',
                )
            || ($vendor['agreement_sha256'] ?? null)
                !== $this->string(
                    'speech.benchmark.evidence.agreement_sha256',
                )
            || ($vendor['dpa_sha256'] ?? null)
                !== $this->string('speech.benchmark.evidence.dpa_sha256')
            || ($vendor['subprocessor_sha256'] ?? null)
                !== $this->string(
                    'speech.benchmark.evidence.subprocessor_sha256',
                )
            || ($vendor['privacy_legal_review_id'] ?? null)
                !== $this->string(
                    'speech.benchmark.evidence.privacy_legal_review',
                )
            || ($vendor['logging_disabled_evidence_id'] ?? null)
                !== $this->string(
                    'speech.benchmark.evidence.logging_disabled',
                )
            || ! $this->currentEvidenceDates($vendor)
        ) {
            $errors[] = 'vendor_evidence_record_invalid';
        }

        if (
            ! is_array($spend)
            || ($spend['schema_version'] ?? null) !== 1
            || ($spend['benchmark_id'] ?? null)
                !== $this->string('speech.benchmark.id')
            || ($spend['approval_id'] ?? null)
                !== $this->string(
                    'speech.benchmark.evidence.spend_approval',
                )
            || ($spend['approved'] ?? null) !== true
            || ! is_numeric($spend['cap_inr'] ?? null)
            || (float) $spend['cap_inr'] !== $approvedSpend
            || $approvedSpend <= 0.0
            || $approvedSpend > 1000.0
            || ! $this->currentEvidenceDates($spend)
        ) {
            $errors[] = 'spend_evidence_record_invalid';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function externalJson(string $path): ?array
    {
        if (! $this->externalReadableFile($path)) {
            return null;
        }

        $body = file_get_contents($path);
        if (! is_string($body) || strlen($body) > 64 * 1024) {
            return null;
        }

        try {
            $value = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function currentEvidenceDates(array $evidence): bool
    {
        $reviewedAt = $evidence['reviewed_at'] ?? null;
        $validUntil = $evidence['valid_until'] ?? null;

        if (
            ! is_string($reviewedAt)
            || ! is_string($validUntil)
            || ! $this->isoInstant($reviewedAt)
            || ! $this->isoInstant($validUntil)
        ) {
            return false;
        }

        return new DateTimeImmutable($reviewedAt) <= new DateTimeImmutable
            && new DateTimeImmutable($validUntil) > new DateTimeImmutable;
    }

    private function absolutePath(string $path): bool
    {
        return $path !== '' && str_starts_with($path, '/');
    }

    private function safeIdentifier(string $value): bool
    {
        return preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{2,127}\z/', $value)
            === 1;
    }

    private function sha256(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
    }

    private function isoInstant(string $value): bool
    {
        return preg_match(
            '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z\z/',
            $value,
        ) === 1;
    }

    private function failure(string $safeCode): ConversationProviderException
    {
        return new ConversationProviderException(
            safeCode: $safeCode,
            providerKey: self::PROVIDER_KEY,
            modelVersion: 'configuration-rejected',
        );
    }
}
