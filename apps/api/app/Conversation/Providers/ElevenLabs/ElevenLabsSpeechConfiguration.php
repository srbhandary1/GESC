<?php

declare(strict_types=1);

namespace App\Conversation\Providers\ElevenLabs;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Routing\SpeechDispatchAuthority;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;

final readonly class ElevenLabsSpeechConfiguration
{
    private const string REQUIRED_HOST = 'api.in.residency.elevenlabs.io';

    /** @var list<string> */
    private const array ALLOWED_ENVIRONMENTS = ['testing', 'soft-launch'];

    public function __construct(
        private Application $application,
        private Repository $config,
        private SpeechDispatchAuthority $authority,
    ) {}

    /** @return list<string> */
    public function preflightErrors(string $candidateKey, string $stage): array
    {
        $errors = $this->authority->denialReasons($candidateKey, $stage);
        $candidate = $this->candidate($candidateKey);

        if (! in_array($this->application->environment(), self::ALLOWED_ENVIRONMENTS, true)) {
            $errors[] = 'environment_not_allowlisted';
        }
        if ($candidate === null || ($candidate['stage'] ?? null) !== $stage) {
            $errors[] = 'candidate_configuration_invalid';
        }
        if ($this->host($candidate) !== self::REQUIRED_HOST) {
            $errors[] = 'endpoint_invalid';
        }
        if ($this->validatedSecretPath($candidate) === null) {
            $errors[] = 'secret_file_invalid';
        }

        return array_values(array_unique($errors));
    }

    public function assertAuthorized(string $candidateKey, string $stage): void
    {
        if ($this->preflightErrors($candidateKey, $stage) !== []) {
            throw $this->failure($candidateKey, 'provider_not_authorized');
        }
    }

    /** @return array<string, string> */
    public function authenticationHeaders(string $candidateKey): array
    {
        $candidate = $this->candidate($candidateKey);
        $path = $this->validatedSecretPath($candidate);
        if ($path === null) {
            throw $this->failure($candidateKey, 'provider_not_authorized');
        }

        $secret = trim((string) file_get_contents($path));
        if ($secret === '' || strlen($secret) > 8192) {
            throw $this->failure($candidateKey, 'provider_not_authorized');
        }

        return ['xi-api-key' => $secret];
    }

    public function baseUrl(string $candidateKey): string
    {
        $candidate = $this->candidate($candidateKey);

        return 'https://'.$this->host($candidate);
    }

    public function string(string $candidateKey, string $key): string
    {
        $candidate = $this->candidate($candidateKey);
        $value = is_array($candidate) ? ($candidate[$key] ?? null) : null;

        return is_string($value) ? trim($value) : '';
    }

    public function integer(string $candidateKey, string $key): int
    {
        $candidate = $this->candidate($candidateKey);
        $value = is_array($candidate) ? ($candidate[$key] ?? null) : null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /** @return array<string, mixed>|null */
    private function candidate(string $candidateKey): ?array
    {
        $candidate = $this->config->get('speech_routing.candidates.'.$candidateKey);

        return is_array($candidate) ? $candidate : null;
    }

    /** @param array<string, mixed>|null $candidate */
    private function host(?array $candidate): string
    {
        $host = is_array($candidate) ? ($candidate['allowed_host'] ?? null) : null;

        return is_string($host) ? strtolower(trim($host)) : '';
    }

    /** @param array<string, mixed>|null $candidate */
    private function validatedSecretPath(?array $candidate): ?string
    {
        $path = is_array($candidate) ? ($candidate['secret_file'] ?? null) : null;
        if (! is_string($path) || $path === '' || $path[0] !== '/') {
            return null;
        }

        $realPath = realpath($path);
        $repositoryRoot = dirname(dirname(base_path()));
        if (
            $realPath === false
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
        if (function_exists('posix_geteuid') && fileowner($realPath) !== posix_geteuid()) {
            return null;
        }

        return $realPath;
    }

    private function failure(
        string $candidateKey,
        string $safeCode,
        int $latencyMilliseconds = 0,
        bool $retryable = false,
    ): ConversationProviderException {
        return new ConversationProviderException(
            safeCode: $safeCode,
            providerKey: $candidateKey,
            modelVersion: $this->string($candidateKey, 'api_model'),
            latencyMilliseconds: $latencyMilliseconds,
            retryable: $retryable,
        );
    }
}
