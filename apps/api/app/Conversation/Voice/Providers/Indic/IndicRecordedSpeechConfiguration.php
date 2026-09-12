<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers\Indic;

use App\Conversation\Providers\ConversationProviderException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;

/**
 * Optional cheaper Indic recorded-STT driver (ADR-0027). Soft-launch stays
 * on Azure until VOICE_PROVIDER_DRIVER=indic is set with a reviewed secret.
 */
final readonly class IndicRecordedSpeechConfiguration
{
    private const string PROVIDER_KEY = 'indic-http-stt';

    /**
     * @var list<string>
     */
    private const array ALLOWED_ENVIRONMENTS = [
        'local',
        'testing',
        'soft-launch',
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

        if (! $this->boolean('voice.upload_enabled')) {
            $errors[] = 'live_requests_not_authorized';
        }

        if ($this->string('voice.provider_driver') !== 'indic') {
            $errors[] = 'provider_driver_invalid';
        }

        if ($this->string('voice.indic.mode') !== 'translate') {
            $errors[] = 'mode_invalid';
        }

        if ($this->string('voice.indic.output_language') !== 'en-IN') {
            $errors[] = 'output_language_invalid';
        }

        if ($this->validatedTranscribeUrl() === null) {
            $errors[] = 'endpoint_invalid';
        }

        if ($this->validatedSecretPath() === null) {
            $errors[] = 'secret_file_invalid';
        }

        $header = $this->string('voice.indic.auth_header');
        if ($header === '' || preg_match('/\A[A-Za-z0-9-]{3,64}\z/', $header) !== 1) {
            $errors[] = 'auth_header_invalid';
        }

        $model = $this->string('voice.indic.model');
        if ($model === '' || preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $model) !== 1) {
            $errors[] = 'model_invalid';
        }

        $languageCode = $this->string('voice.indic.language_code');
        if (
            $languageCode !== 'unknown'
            && preg_match('/\A[a-z]{2}-[A-Z]{2}\z/', $languageCode) !== 1
        ) {
            $errors[] = 'language_code_invalid';
        }

        $maximumBytes = $this->integer('voice.indic.maximum_json_response_bytes');
        if ($maximumBytes < 1024 || $maximumBytes > 1024 * 1024) {
            $errors[] = 'response_limit_invalid';
        }

        return $errors;
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
        $path = $this->validatedSecretPath();
        if ($path === null) {
            throw $this->failure('provider_not_authorized');
        }

        $secret = trim((string) file_get_contents($path));
        if ($secret === '' || strlen($secret) > 8192) {
            throw $this->failure('provider_not_authorized');
        }

        return [
            $this->string('voice.indic.auth_header') => $secret,
        ];
    }

    public function transcribeUrl(): string
    {
        $url = $this->validatedTranscribeUrl();
        if ($url === null) {
            throw $this->failure('provider_not_authorized');
        }

        return $url;
    }

    public function model(): string
    {
        return $this->string('voice.indic.model');
    }

    public function mode(): string
    {
        return $this->string('voice.indic.mode');
    }

    public function languageCode(): string
    {
        return $this->string('voice.indic.language_code');
    }

    public function outputLanguage(): string
    {
        return $this->string('voice.indic.output_language');
    }

    public function modelVersion(): string
    {
        return $this->model();
    }

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function maximumJsonResponseBytes(): int
    {
        return $this->integer('voice.indic.maximum_json_response_bytes');
    }

    public function connectionTimeoutSeconds(): int
    {
        $value = $this->integer('voice.indic.connection_timeout_seconds');

        return $value > 0 ? $value : 5;
    }

    public function requestTimeoutSeconds(): int
    {
        $value = $this->integer('voice.indic.request_timeout_seconds');

        return $value > 0 ? $value : 20;
    }

    private function validatedTranscribeUrl(): ?string
    {
        $endpoint = $this->string('voice.indic.transcribe_url');
        $allowedHost = strtolower($this->string('voice.indic.allowed_host'));
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

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        if (
            $host === ''
            || $allowedHost === ''
            || ! hash_equals($allowedHost, $host)
            || $path === ''
            || $path === '/'
            || preg_match('#\A/[A-Za-z0-9/_-]{3,128}\z#', $path) !== 1
        ) {
            return null;
        }

        return 'https://'.$host.$path;
    }

    private function validatedSecretPath(): ?string
    {
        $path = $this->string('voice.indic.secret_file');
        $realPath = realpath($path);
        $repositoryRoot = dirname(dirname(base_path()));

        if (
            $path === ''
            || $path[0] !== '/'
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

    private function failure(string $safeCode): ConversationProviderException
    {
        return new ConversationProviderException(
            safeCode: $safeCode,
            providerKey: self::PROVIDER_KEY,
            modelVersion: $this->model() === '' ? 'unconfigured' : $this->model(),
        );
    }

    private function boolean(string $key): bool
    {
        return filter_var($this->config->get($key), FILTER_VALIDATE_BOOL) === true;
    }

    private function string(string $key): string
    {
        $value = $this->config->get($key);

        return is_string($value) ? $value : '';
    }

    private function integer(string $key): int
    {
        $value = $this->config->get($key);

        return is_numeric($value) ? (int) $value : 0;
    }
}
