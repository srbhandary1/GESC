<?php

declare(strict_types=1);

namespace App\Conversation\Routing;

use App\Conversation\Providers\ConversationProviderException;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use JsonException;

final readonly class SpeechDispatchAuthority
{
    public function __construct(
        private Application $application,
        private Repository $config,
    ) {}

    public function assertDispatchAllowed(string $providerKey, string $stage): void
    {
        if ($this->denialReasons($providerKey, $stage) !== []) {
            throw $this->denied($providerKey, $stage);
        }
    }

    /**
     * @return list<string>
     */
    public function denialReasons(string $providerKey, string $stage): array
    {
        $reasons = [];
        $candidate = $this->config->get('speech_routing.candidates.'.$providerKey);
        if (! is_array($candidate) || ($candidate['stage'] ?? null) !== $stage) {
            $reasons[] = 'candidate_unregistered';
        }
        if (! $this->boolean($this->config->get('speech_routing.live_router_enabled'))) {
            $reasons[] = 'http_enablement_not_authorized';
        }
        if (! $this->boolean(
            is_array($candidate) ? ($candidate['implementation_ready'] ?? false) : false,
        )) {
            $reasons[] = 'implementation_unavailable';
        }
        if ($this->application->environment('production')) {
            $reasons[] = 'production_blocked';
        }
        if (filter_var(
            is_array($candidate) ? ($candidate['kill_switch'] ?? true) : true,
            FILTER_VALIDATE_BOOL,
        )) {
            $reasons[] = 'kill_switch';
        }
        if (! filter_var(
            is_array($candidate) ? ($candidate['dispatch_authorized'] ?? false) : false,
            FILTER_VALIDATE_BOOL,
        )) {
            $reasons[] = 'dispatch_flag_off';
        }
        $evidence = is_array($candidate) ? ($candidate['evidence_file'] ?? '') : '';
        $evidencePath = $this->usableEvidenceFile($evidence);
        if ($evidencePath === null) {
            $reasons[] = 'evidence_missing';
        }
        $secret = is_array($candidate) ? ($candidate['secret_file'] ?? '') : '';
        if (! is_string($secret) || $secret === '' || ! is_file($secret)) {
            $reasons[] = 'secret_unbound';
        }

        if (str_starts_with($providerKey, 'elevenlabs-')) {
            $reasons = [
                ...$reasons,
                ...$this->elevenLabsEvidenceReasons($evidencePath),
            ];
        }

        return array_values(array_unique($reasons));
    }

    /** @return list<string> */
    private function elevenLabsEvidenceReasons(?string $path): array
    {
        if ($path === null) {
            return [];
        }

        $evidence = $this->readJson($path);
        if ($evidence === null) {
            return ['evidence_invalid'];
        }

        $reasons = [];
        if (
            ($evidence['provider'] ?? null) !== 'elevenlabs'
            || ($evidence['api_host'] ?? null)
                !== 'api.in.residency.elevenlabs.io'
            || ($evidence['india_residency_asserted'] ?? null) !== true
        ) {
            $reasons[] = 'india_residency_evidence_missing';
        }
        if (($evidence['zero_retention_asserted'] ?? null) !== true) {
            $reasons[] = 'zero_retention_evidence_missing';
        }
        if (($evidence['approved_for_learner_content'] ?? null) !== true) {
            $reasons[] = 'learner_content_processing_not_approved';
        }
        if (! $this->futureInstant($evidence['valid_until'] ?? null)) {
            $reasons[] = 'vendor_evidence_expired';
        }

        $directory = dirname($path);
        $rotation = $this->readJson($directory.'/elevenlabs-rotation-receipt.json');
        if (
            ($evidence['key_rotated'] ?? null) !== true
            || ($rotation['provider'] ?? null) !== 'elevenlabs'
            || ($rotation['previous_key_revoked'] ?? null) !== true
            || ($rotation['new_secret_outside_git'] ?? null) !== true
        ) {
            $reasons[] = 'live_deployment_key_rotation_required';
        }
        $deletionId = $evidence['deletion_test_evidence_id'] ?? null;
        $deletion = $this->readJson($directory.'/elevenlabs-deletion-test.json');
        if (
            ! is_string($deletionId)
            || trim($deletionId) === ''
            || ($deletion['provider'] ?? null) !== 'elevenlabs'
            || ($deletion['evidence_id'] ?? null) !== $deletionId
            || ($deletion['content_free'] ?? null) !== true
            || ($deletion['audio_retained'] ?? null) !== false
            || ($deletion['transcript_retained'] ?? null) !== false
        ) {
            $reasons[] = 'deletion_test_evidence_missing';
        }

        $spend = $this->readJson($directory.'/elevenlabs-spend-approval.json');
        $cap = $spend['cap_inr'] ?? null;
        if (
            ($spend['provider'] ?? null) !== 'elevenlabs'
            || ($spend['approved'] ?? null) !== true
            || ($spend['content_dispatch_authorized'] ?? null) !== true
            || ! is_numeric($cap)
            || (float) $cap <= 0.0
            || (float) $cap > 500.0
            || ! $this->futureInstant($spend['valid_until'] ?? null)
        ) {
            $reasons[] = 'spend_authorization_missing';
        }

        return $reasons;
    }

    /** @return array<string, mixed>|null */
    private function readJson(string $path): ?array
    {
        $usable = $this->usableEvidenceFile($path);
        if ($usable === null) {
            return null;
        }
        try {
            $payload = json_decode(
                (string) file_get_contents($usable),
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function usableEvidenceFile(mixed $path): ?string
    {
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
            || filesize($realPath) > 65_536
        ) {
            return null;
        }
        if (function_exists('posix_geteuid') && fileowner($realPath) !== posix_geteuid()) {
            return null;
        }

        return $realPath;
    }

    private function futureInstant(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }
        try {
            return new DateTimeImmutable($value) > new DateTimeImmutable('now');
        } catch (\Throwable) {
            return false;
        }
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) === true;
    }

    private function denied(
        string $providerKey,
        string $stage,
    ): ConversationProviderException {
        return new ConversationProviderException(
            safeCode: 'provider_not_authorized',
            providerKey: $providerKey,
            modelVersion: $stage.'-dispatch-blocked',
        );
    }
}
