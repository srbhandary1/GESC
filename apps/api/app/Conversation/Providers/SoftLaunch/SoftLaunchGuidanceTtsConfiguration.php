<?php

declare(strict_types=1);

namespace App\Conversation\Providers\SoftLaunch;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Support\GuidanceTtsPolicy;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use JsonException;

/**
 * Soft-launch ordinary-product guidance TTS gate (APP-0064).
 *
 * Separate from the locked PR-009 speech-benchmark environment. Allows
 * GuidanceTtsPolicy voices (en-IN model lines + kn-IN and other L1 guidance).
 */
final readonly class SoftLaunchGuidanceTtsConfiguration
{
    private const string PROVIDER_KEY = 'azure-speech-guidance';

    private const float MAXIMUM_SPEND_INR = 500.0;

    /**
     * @var list<string>
     */
    private const array ALLOWED_ENVIRONMENTS = [
        'soft-launch',
        'testing',
    ];

    /**
     * @var list<string>
     */
    private const array ALLOWED_SPEND_APPROVAL_IDS = [
        'APP-0064',
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

        if (! $this->boolean('conversation.guidance_tts.live_requests_enabled')) {
            $errors[] = 'live_requests_not_authorized';
        }

        if ($this->string('conversation.guidance_tts.region') !== 'centralindia') {
            $errors[] = 'region_invalid';
        }

        if (
            $this->string('conversation.guidance_tts.output_format')
            !== 'riff-24khz-16bit-mono-pcm'
        ) {
            $errors[] = 'output_format_invalid';
        }

        if ($this->validatedEndpointHost() === null) {
            $errors[] = 'endpoint_invalid';
        }

        if (! in_array(
            $this->string('conversation.guidance_tts.credential_kind'),
            ['subscription-key', 'entra-token'],
            true,
        )) {
            $errors[] = 'credential_kind_invalid';
        }

        if ($this->validatedSecretPath() === null) {
            $errors[] = 'secret_file_invalid';
        }

        if ($this->float('conversation.guidance_tts.maximum_spend_inr') !== self::MAXIMUM_SPEND_INR) {
            $errors[] = 'spend_cap_invalid';
        }

        if ($this->integer('conversation.guidance_tts.maximum_characters') < 1) {
            $errors[] = 'maximum_characters_invalid';
        }

        foreach (
            [
                'subscription',
                'contracting_entity',
                'privacy_legal_review',
                'logging_disabled',
                'credential_version',
                'spend_approval',
            ] as $evidence
        ) {
            if (! $this->safeIdentifier(
                $this->string('conversation.guidance_tts.evidence.'.$evidence),
            )) {
                $errors[] = $evidence.'_evidence_missing';
            }
        }

        foreach (
            ['agreement_sha256', 'dpa_sha256', 'subprocessor_sha256'] as $hash
        ) {
            if (! $this->sha256(
                $this->string('conversation.guidance_tts.evidence.'.$hash),
            )) {
                $errors[] = $hash.'_invalid';
            }
        }

        return [
            ...$errors,
            ...$this->vendorEvidenceErrors(),
            ...$this->spendEvidenceErrors(),
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

        return $this->string('conversation.guidance_tts.credential_kind') === 'entra-token'
            ? ['Authorization' => 'Bearer '.$secret]
            : ['Ocp-Apim-Subscription-Key' => $secret];
    }

    public function endpoint(): string
    {
        return rtrim($this->string('conversation.guidance_tts.endpoint'), '/');
    }

    public function ttsRoute(): string
    {
        return $this->string('conversation.guidance_tts.route');
    }

    public function outputFormat(): string
    {
        return $this->string('conversation.guidance_tts.output_format');
    }

    public function maximumCharacters(): int
    {
        return $this->integer('conversation.guidance_tts.maximum_characters');
    }

    public function maximumAudioResponseBytes(): int
    {
        return $this->integer('conversation.guidance_tts.maximum_audio_response_bytes');
    }

    public function connectionTimeoutSeconds(): int
    {
        return $this->integer('conversation.guidance_tts.connection_timeout_seconds');
    }

    public function requestTimeoutSeconds(): int
    {
        return $this->integer('conversation.guidance_tts.request_timeout_seconds');
    }

    public function modelVersionBase(): string
    {
        return $this->string('conversation.guidance_tts.model_version');
    }

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    public function localeForVoice(string $voice): string
    {
        if (! GuidanceTtsPolicy::isAllowlistedVoice($voice)) {
            throw $this->failure('provider_input_invalid');
        }

        foreach (GuidanceTtsPolicy::voiceMap() as $locale => $mappedVoice) {
            if ($mappedVoice === $voice) {
                return $locale;
            }
        }

        throw $this->failure('provider_input_invalid');
    }

    public function estimatedCostMicrounits(int $characterCount): int
    {
        $inrPerCharacter = $this->float(
            'conversation.guidance_tts.pricing.inr_per_tts_character',
        );

        if ($inrPerCharacter <= 0.0 || $characterCount < 1) {
            return 0;
        }

        return max(0, (int) round($characterCount * $inrPerCharacter * 1_000_000));
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

    /**
     * @return list<string>
     */
    private function vendorEvidenceErrors(): array
    {
        $path = $this->string('conversation.guidance_tts.vendor_evidence_path');

        if (! $this->absoluteReadableFile($path)) {
            return ['vendor_evidence_missing'];
        }

        try {
            $vendor = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return ['vendor_evidence_invalid'];
        }

        if (! is_array($vendor)) {
            return ['vendor_evidence_invalid'];
        }

        $errors = [];

        if (($vendor['provider'] ?? null) !== 'azure-speech') {
            $errors[] = 'vendor_provider_invalid';
        }

        if (($vendor['region'] ?? null) !== 'centralindia') {
            $errors[] = 'vendor_region_invalid';
        }

        if (($vendor['data_logging_enabled'] ?? null) !== false) {
            $errors[] = 'vendor_logging_enabled';
        }

        if (($vendor['approved_for_soft_launch_guidance_tts'] ?? null) !== true) {
            $errors[] = 'vendor_soft_launch_guidance_not_approved';
        }

        if (
            ($vendor['subscription_evidence_id'] ?? null)
            !== $this->string('conversation.guidance_tts.evidence.subscription')
            || ($vendor['contracting_entity_review_id'] ?? null)
            !== $this->string('conversation.guidance_tts.evidence.contracting_entity')
            || ($vendor['agreement_sha256'] ?? null)
            !== $this->string('conversation.guidance_tts.evidence.agreement_sha256')
            || ($vendor['dpa_sha256'] ?? null)
            !== $this->string('conversation.guidance_tts.evidence.dpa_sha256')
            || ($vendor['subprocessor_sha256'] ?? null)
            !== $this->string('conversation.guidance_tts.evidence.subprocessor_sha256')
            || ($vendor['privacy_legal_review_id'] ?? null)
            !== $this->string('conversation.guidance_tts.evidence.privacy_legal_review')
            || ($vendor['logging_disabled_evidence_id'] ?? null)
            !== $this->string('conversation.guidance_tts.evidence.logging_disabled')
        ) {
            $errors[] = 'vendor_evidence_mismatch';
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function spendEvidenceErrors(): array
    {
        $path = $this->string('conversation.guidance_tts.spend_approval_path');

        if (! $this->absoluteReadableFile($path)) {
            return ['spend_approval_missing'];
        }

        try {
            $spend = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return ['spend_approval_invalid'];
        }

        if (! is_array($spend)) {
            return ['spend_approval_invalid'];
        }

        $errors = [];
        $approvalId = $spend['approval_id'] ?? null;
        $cap = $spend['cap_inr'] ?? null;

        if (
            ! is_string($approvalId)
            || ! in_array($approvalId, self::ALLOWED_SPEND_APPROVAL_IDS, true)
            || $approvalId
            !== $this->string('conversation.guidance_tts.evidence.spend_approval')
        ) {
            $errors[] = 'spend_approval_id_invalid';
        }

        if (($spend['approved'] ?? null) !== true) {
            $errors[] = 'spend_not_approved';
        }

        if (! is_numeric($cap) || (float) $cap > self::MAXIMUM_SPEND_INR || (float) $cap <= 0.0) {
            $errors[] = 'spend_cap_exceeds_authorization';
        }

        $approvedCap = $this->float(
            'conversation.guidance_tts.approved_spend_cap_inr',
        );

        if ($approvedCap <= 0.0 || $approvedCap > self::MAXIMUM_SPEND_INR) {
            $errors[] = 'approved_spend_cap_invalid';
        }

        if (is_numeric($cap) && (float) $cap !== $approvedCap) {
            $errors[] = 'spend_cap_mismatch';
        }

        $validUntil = $spend['valid_until'] ?? null;

        if (! is_string($validUntil)) {
            $errors[] = 'spend_validity_invalid';
        } else {
            try {
                $until = new DateTimeImmutable($validUntil);
                if ($until < new DateTimeImmutable('now')) {
                    $errors[] = 'spend_approval_expired';
                }
            } catch (\Exception) {
                $errors[] = 'spend_validity_invalid';
            }
        }

        return $errors;
    }

    private function validatedEndpointHost(): ?string
    {
        $endpoint = $this->string('conversation.guidance_tts.endpoint');
        $allowedHost = $this->string('conversation.guidance_tts.allowed_host');

        if (
            $endpoint === ''
            || $allowedHost === ''
            || ! str_starts_with($endpoint, 'https://')
        ) {
            return null;
        }

        $host = parse_url($endpoint, PHP_URL_HOST);

        if (! is_string($host) || $host !== $allowedHost) {
            return null;
        }

        return $host;
    }

    private function validatedSecretPath(): ?string
    {
        $path = $this->string('conversation.guidance_tts.secret_file');

        if (! $this->absoluteReadableFile($path) || ! is_file($path)) {
            return null;
        }

        return $path;
    }

    private function absoluteReadableFile(string $path): bool
    {
        return $path !== ''
            && $path[0] === '/'
            && is_file($path)
            && is_readable($path);
    }

    private function safeIdentifier(string $value): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/', $value) === 1
            && ! str_contains(strtoupper($value), 'REPLACE');
    }

    private function sha256(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
    }

    private function failure(string $safeCode): ConversationProviderException
    {
        return new ConversationProviderException(
            safeCode: $safeCode,
            providerKey: self::PROVIDER_KEY,
            modelVersion: $this->modelVersionBase(),
        );
    }
}
