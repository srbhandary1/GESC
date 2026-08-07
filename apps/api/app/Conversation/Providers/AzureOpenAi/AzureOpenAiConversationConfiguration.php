<?php

declare(strict_types=1);

namespace App\Conversation\Providers\AzureOpenAi;

use App\Conversation\Providers\ConversationProviderException;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use JsonException;

final readonly class AzureOpenAiConversationConfiguration
{
    private const string PROVIDER_KEY = 'azure-openai';

    private const float MAXIMUM_SPEND_INR = 500.0;

    /**
     * @var list<string>
     */
    private const array ALLOWED_ENVIRONMENTS = [
        'local',
        'testing',
        'conversation-model-dev',
    ];

    public function __construct(
        private Application $application,
        private Repository $config,
    ) {}

    /**
     * @return list<string>
     */
    public function preflightErrors(): array
    {
        $errors = [];

        if (! in_array($this->application->environment(), self::ALLOWED_ENVIRONMENTS, true)) {
            $errors[] = 'environment_not_allowlisted';
        }

        if (! $this->boolean('conversation.azure_openai.live_requests_enabled')) {
            $errors[] = 'live_requests_not_authorized';
        }

        if ($this->validatedEndpointHost() === null) {
            $errors[] = 'endpoint_invalid';
        }

        if (! $this->safeDeploymentName(
            $this->string('conversation.azure_openai.deployment'),
        )) {
            $errors[] = 'deployment_invalid';
        }

        if (! $this->safeApiVersion(
            $this->string('conversation.azure_openai.api_version'),
        )) {
            $errors[] = 'api_version_invalid';
        }

        if (! in_array(
            $this->string('conversation.azure_openai.credential_kind'),
            ['api-key', 'entra-token'],
            true,
        )) {
            $errors[] = 'credential_kind_invalid';
        }

        if ($this->validatedSecretPath() === null) {
            $errors[] = 'secret_file_invalid';
        }

        if ($this->float('conversation.azure_openai.maximum_spend_inr') !== self::MAXIMUM_SPEND_INR) {
            $errors[] = 'spend_cap_invalid';
        }

        foreach (
            [
                'subscription',
                'contracting_entity',
                'privacy_legal_review',
                'logging_disabled',
                'credential_version',
                'spend_approval',
                'region_acceptance',
            ] as $evidence
        ) {
            if (! $this->safeIdentifier(
                $this->string('conversation.azure_openai.evidence.'.$evidence),
            )) {
                $errors[] = $evidence.'_evidence_missing';
            }
        }

        foreach (
            ['agreement_sha256', 'dpa_sha256', 'subprocessor_sha256'] as $hash
        ) {
            if (! $this->sha256(
                $this->string('conversation.azure_openai.evidence.'.$hash),
            )) {
                $errors[] = $hash.'_invalid';
            }
        }

        $vendorErrors = $this->vendorEvidenceErrors();
        $spendErrors = $this->spendEvidenceErrors();

        return [...$errors, ...$vendorErrors, ...$spendErrors];
    }

    public function assertLiveAuthorized(): void
    {
        if ($this->preflightErrors() !== []) {
            throw $this->failure('provider_not_authorized');
        }
    }

    /**
     * @return array<string, string>
     */
    public function authenticationHeaders(): array
    {
        $secret = $this->readSecret();
        $kind = $this->string('conversation.azure_openai.credential_kind');

        return match ($kind) {
            'api-key' => ['api-key' => $secret],
            'entra-token' => ['Authorization' => 'Bearer '.$secret],
            default => throw $this->failure('provider_not_authorized'),
        };
    }

    public function chatCompletionsUrl(): string
    {
        $endpoint = rtrim($this->string('conversation.azure_openai.endpoint'), '/');
        $deployment = rawurlencode(
            $this->string('conversation.azure_openai.deployment'),
        );
        $apiVersion = rawurlencode(
            $this->string('conversation.azure_openai.api_version'),
        );

        return $endpoint
            .'/openai/deployments/'
            .$deployment
            .'/chat/completions?api-version='
            .$apiVersion;
    }

    public function modelVersion(): string
    {
        return $this->string('conversation.azure_openai.deployment');
    }

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function integer(string $key): int
    {
        $value = $this->config->get($key);

        return is_int($value) ? $value : (int) $value;
    }

    public function failure(
        string $safeCode,
        int $latencyMilliseconds = 0,
        bool $retryable = false,
        int $inputUnits = 0,
        int $outputUnits = 0,
    ): ConversationProviderException {
        return new ConversationProviderException(
            safeCode: $safeCode,
            providerKey: self::PROVIDER_KEY,
            modelVersion: $this->modelVersion() !== ''
                ? $this->modelVersion()
                : 'azure-openai-unconfigured',
            latencyMilliseconds: $latencyMilliseconds,
            inputUnits: $inputUnits,
            outputUnits: $outputUnits,
            retryable: $retryable,
        );
    }

    /**
     * @return list<string>
     */
    private function vendorEvidenceErrors(): array
    {
        $path = $this->string('conversation.azure_openai.vendor_evidence_path');
        $payload = $this->readExternalJson($path);
        if ($payload === null) {
            return ['vendor_evidence_invalid'];
        }

        $errors = [];
        if (($payload['schema_version'] ?? null) !== 1) {
            $errors[] = 'vendor_evidence_schema_invalid';
        }
        if (($payload['provider'] ?? null) !== 'azure-openai') {
            $errors[] = 'vendor_evidence_provider_invalid';
        }
        if (($payload['approved_for_conversation_model'] ?? null) !== true) {
            $errors[] = 'vendor_evidence_not_approved';
        }
        if (($payload['data_logging_enabled'] ?? null) !== false) {
            $errors[] = 'vendor_evidence_logging_enabled';
        }
        if (($payload['model_improvement_enabled'] ?? null) !== false) {
            $errors[] = 'vendor_evidence_training_enabled';
        }
        if (! $this->safeIdentifier((string) ($payload['subscription_evidence_id'] ?? ''))) {
            $errors[] = 'vendor_evidence_subscription_invalid';
        }
        if (! $this->timestampsValid(
            (string) ($payload['reviewed_at'] ?? ''),
            (string) ($payload['valid_until'] ?? ''),
        )) {
            $errors[] = 'vendor_evidence_validity_invalid';
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function spendEvidenceErrors(): array
    {
        $path = $this->string('conversation.azure_openai.spend_approval_path');
        $payload = $this->readExternalJson($path);
        if ($payload === null) {
            return ['spend_evidence_invalid'];
        }

        $errors = [];
        if (($payload['schema_version'] ?? null) !== 1) {
            $errors[] = 'spend_evidence_schema_invalid';
        }
        if (($payload['approval_id'] ?? null) !== 'APP-0022') {
            $errors[] = 'spend_evidence_approval_id_invalid';
        }
        if (($payload['approved'] ?? null) !== true) {
            $errors[] = 'spend_evidence_not_approved';
        }

        $cap = $payload['cap_inr'] ?? null;
        if (! is_int($cap) && ! is_float($cap)) {
            $errors[] = 'spend_evidence_cap_invalid';
        } elseif ((float) $cap > self::MAXIMUM_SPEND_INR || (float) $cap <= 0) {
            $errors[] = 'spend_evidence_cap_invalid';
        }

        $approvedCap = $this->float(
            'conversation.azure_openai.approved_spend_cap_inr',
        );
        if (
            $approvedCap <= 0.0
            || $approvedCap > self::MAXIMUM_SPEND_INR
            || (is_int($cap) || is_float($cap)) && (float) $cap !== $approvedCap
        ) {
            $errors[] = 'spend_evidence_cap_mismatch';
        }

        if (! $this->timestampsValid(
            (string) ($payload['reviewed_at'] ?? ''),
            (string) ($payload['valid_until'] ?? ''),
        )) {
            $errors[] = 'spend_evidence_validity_invalid';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readExternalJson(string $path): ?array
    {
        $realPath = $this->externalReadableFile($path);
        if ($realPath === null) {
            return null;
        }

        try {
            $decoded = json_decode(
                (string) file_get_contents($realPath),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function readSecret(): string
    {
        $path = $this->validatedSecretPath();
        if ($path === null) {
            throw $this->failure('provider_not_authorized');
        }

        $secret = trim((string) file_get_contents($path));
        if ($secret === '' || strlen($secret) > 8192) {
            throw $this->failure('provider_not_authorized');
        }

        return $secret;
    }

    private function validatedEndpointHost(): ?string
    {
        $endpoint = $this->string('conversation.azure_openai.endpoint');
        $allowedHost = strtolower(
            $this->string('conversation.azure_openai.allowed_host'),
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
                ! str_ends_with($host, '.openai.azure.com')
                && ! str_ends_with($host, '.cognitiveservices.azure.com')
            )
        ) {
            return null;
        }

        return $host;
    }

    private function validatedSecretPath(): ?string
    {
        $path = $this->string('conversation.azure_openai.secret_file');
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

    private function externalReadableFile(string $path): ?string
    {
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
            || filesize($realPath) > 65536
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

    private function timestampsValid(string $reviewedAt, string $validUntil): bool
    {
        try {
            $reviewed = new DateTimeImmutable($reviewedAt);
            $until = new DateTimeImmutable($validUntil);
        } catch (\Exception) {
            return false;
        }

        return $until > $reviewed && $until > new DateTimeImmutable('now');
    }

    private function safeDeploymentName(string $value): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/', $value) === 1;
    }

    private function safeApiVersion(string $value): bool
    {
        return preg_match('/\A\d{4}-\d{2}-\d{2}(-preview)?\z/', $value) === 1;
    }

    private function safeIdentifier(string $value): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/', $value) === 1;
    }

    private function sha256(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
    }

    private function absolutePath(string $path): bool
    {
        return $path !== '' && $path[0] === '/';
    }

    private function string(string $key): string
    {
        $value = $this->config->get($key);

        return is_string($value) ? $value : '';
    }

    private function boolean(string $key): bool
    {
        return (bool) $this->config->get($key, false);
    }

    private function float(string $key): float
    {
        $value = $this->config->get($key);

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }
}
