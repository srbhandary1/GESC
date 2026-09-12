<?php

declare(strict_types=1);

namespace App\Conversation\Providers\SoftLaunch;

use App\Conversation\Providers\ConversationProviderException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;

/**
 * Soft-launch ordinary-product learner STT gate (APP-0068).
 *
 * Reuses the APP-0064 Central India Azure Speech resource. Benchmark-only
 * AzureSpeechConfiguration remains unused.
 */
final readonly class SoftLaunchRecordedSpeechConfiguration
{
    private const string PROVIDER_KEY = 'azure-speech-soft-launch-stt';

    private const string MODEL_VERSION = 'fast-transcription-2025-10-15-provider-managed';

    /**
     * @var list<string>
     */
    private const array ALLOWED_ENVIRONMENTS = [
        'soft-launch',
        'testing',
    ];

    public function __construct(
        private Application $application,
        private Repository $config,
        private SoftLaunchGuidanceTtsConfiguration $guidanceTts,
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

        if ($this->string('voice.provider_driver') !== 'azure') {
            $errors[] = 'provider_driver_invalid';
        }

        if ($this->string('voice.soft_launch.stt_locale') !== 'en-IN') {
            $errors[] = 'stt_locale_invalid';
        }

        if ($this->string('voice.soft_launch.stt_api_version') !== '2025-10-15') {
            $errors[] = 'stt_api_version_invalid';
        }

        if ($this->string('voice.soft_launch.stt_route') !== '/speechtotext/transcriptions:transcribe') {
            $errors[] = 'stt_route_invalid';
        }

        if ($this->string('voice.soft_launch.stt_profanity_filter_mode') !== 'None') {
            $errors[] = 'stt_profanity_invalid';
        }

        return [
            ...$errors,
            ...$this->guidanceTts->preflightErrors(),
        ];
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
        return $this->guidanceTts->authenticationHeaders();
    }

    public function transcribeUrl(): string
    {
        return $this->guidanceTts->endpoint()
            .$this->string('voice.soft_launch.stt_route')
            .'?api-version='.rawurlencode($this->string('voice.soft_launch.stt_api_version'));
    }

    public function locale(): string
    {
        return $this->string('voice.soft_launch.stt_locale');
    }

    public function modelVersion(): string
    {
        return self::MODEL_VERSION;
    }

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function maximumJsonResponseBytes(): int
    {
        return $this->integer('voice.soft_launch.maximum_json_response_bytes');
    }

    public function connectionTimeoutSeconds(): int
    {
        return $this->guidanceTts->connectionTimeoutSeconds();
    }

    public function requestTimeoutSeconds(): int
    {
        return $this->guidanceTts->requestTimeoutSeconds();
    }

    private function failure(string $safeCode): ConversationProviderException
    {
        return new ConversationProviderException(
            safeCode: $safeCode,
            providerKey: self::PROVIDER_KEY,
            modelVersion: self::MODEL_VERSION,
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
