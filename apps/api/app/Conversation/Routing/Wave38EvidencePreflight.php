<?php

declare(strict_types=1);

namespace App\Conversation\Routing;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use JsonException;

final readonly class Wave38EvidencePreflight
{
    private const int MAXIMUM_EVIDENCE_BYTES = 65536;

    private const string REQUIRED_ELEVENLABS_HOST = 'api.in.residency.elevenlabs.io';

    public function __construct(
        private Repository $config,
    ) {}

    /**
     * @return array{
     *   status: 'blocked'|'ready',
     *   checkpoint_closed: bool,
     *   dispatch_authorized: false,
     *   safe_error_codes: list<string>,
     *   deferred_until_live_deployment: list<string>,
     *   recorded_assertions: array{
     *     india_residency_asserted: bool,
     *     zero_retention_asserted: bool,
     *     elevenlabs_cap_inr: int|null,
     *     sarvam_cap_inr: int|null
     *   }
     * }
     */
    public function evaluate(?string $statusFileOverride = null): array
    {
        $assertions = [
            'india_residency_asserted' => false,
            'zero_retention_asserted' => false,
            'elevenlabs_cap_inr' => null,
            'sarvam_cap_inr' => null,
        ];
        $configured = $statusFileOverride ?? $this->string(
            $this->config->get('speech_routing.wave38.status_file'),
        );
        $statusPath = $this->usableEvidenceFile($configured);
        if ($statusPath === null) {
            return $this->result(false, ['wave38_status_unusable'], $assertions, []);
        }

        $status = $this->readJson($statusPath);
        if ($status === null || ($status['schema_version'] ?? null) !== 1) {
            return $this->result(false, ['wave38_status_unusable'], $assertions, []);
        }

        $codes = [];
        if (($status['dispatch_authorized'] ?? false) === true) {
            $codes[] = 'unexpected_dispatch_authorization';
        }

        $directory = dirname($statusPath);
        $elevenVendor = $this->readSibling($directory, 'elevenlabs-vendor-evidence.json');
        $elevenSpend = $this->readSibling($directory, 'elevenlabs-spend-approval.json');
        $elevenRotation = $this->readSibling($directory, 'elevenlabs-rotation-receipt.json');
        $elevenDeletion = $this->readSibling($directory, 'elevenlabs-deletion-test.json');
        $sarvamVendor = $this->readSibling($directory, 'sarvam-vendor-evidence.json');
        $sarvamSpend = $this->readSibling($directory, 'sarvam-spend-approval.json');

        if ($elevenVendor === null) {
            $codes[] = 'elevenlabs_vendor_evidence_unusable';
        } else {
            $assertions['india_residency_asserted'] = ($elevenVendor['india_residency_asserted'] ?? false) === true;
            $assertions['zero_retention_asserted'] = ($elevenVendor['zero_retention_asserted'] ?? false) === true;
            $codes = [
                ...$codes,
                ...$this->elevenLabsVendorGaps($elevenVendor),
            ];
        }

        if ($elevenSpend === null) {
            $codes[] = 'elevenlabs_spend_unusable';
        } else {
            $assertions['elevenlabs_cap_inr'] = $this->positiveInt($elevenSpend['cap_inr'] ?? null);
            $codes = [
                ...$codes,
                ...$this->spendGaps(
                    $elevenSpend,
                    'elevenlabs',
                    $this->requiredCap('elevenlabs'),
                ),
            ];
        }

        if ($elevenRotation === null || ($elevenRotation['previous_key_revoked'] ?? false) !== true) {
            $codes[] = 'elevenlabs_key_not_rotated';
        }

        if ($elevenDeletion === null || ! $this->nonEmptyId($elevenDeletion['evidence_id'] ?? null)) {
            $codes[] = 'elevenlabs_deletion_test_missing';
        }

        if ($sarvamVendor === null) {
            $codes[] = 'sarvam_vendor_evidence_unusable';
        } else {
            $codes = [...$codes, ...$this->sarvamVendorGaps($sarvamVendor)];
        }

        if ($sarvamSpend === null) {
            $codes[] = 'sarvam_spend_unusable';
        } else {
            $assertions['sarvam_cap_inr'] = $this->positiveInt($sarvamSpend['cap_inr'] ?? null);
            $codes = [
                ...$codes,
                ...$this->spendGaps(
                    $sarvamSpend,
                    'sarvam',
                    $this->requiredCap('sarvam'),
                ),
            ];
        }

        $azurePath = $this->string($status['inherited_azure']['speech_vendor_evidence'] ?? '');
        $azure = $azurePath === '' ? null : $this->readJsonFromUsablePath($azurePath);
        if ($azure === null) {
            $codes[] = 'azure_speech_vendor_evidence_unusable';
        } else {
            $codes = [...$codes, ...$this->azureSpeechGaps($azure)];
        }

        $codes = array_values(array_unique($codes));
        $deferred = [];
        if ($this->liveDeploymentDeferralAccepted($status)) {
            foreach ([
                'elevenlabs_key_not_rotated',
                'elevenlabs_deletion_test_missing',
            ] as $code) {
                if (in_array($code, $codes, true)) {
                    $deferred[] = $code;
                }
            }
            $codes = array_values(array_diff($codes, $deferred));
        }

        return $this->result($codes === [], $codes, $assertions, $deferred);
    }

    /**
     * @param  list<string>  $codes
     * @param  array{
     *   india_residency_asserted: bool,
     *   zero_retention_asserted: bool,
     *   elevenlabs_cap_inr: int|null,
     *   sarvam_cap_inr: int|null
     * }  $assertions
     * @param  list<string>  $deferred
     * @return array{
     *   status: 'blocked'|'ready',
     *   checkpoint_closed: bool,
     *   dispatch_authorized: false,
     *   safe_error_codes: list<string>,
     *   deferred_until_live_deployment: list<string>,
     *   recorded_assertions: array{
     *     india_residency_asserted: bool,
     *     zero_retention_asserted: bool,
     *     elevenlabs_cap_inr: int|null,
     *     sarvam_cap_inr: int|null
     *   }
     * }
     */
    private function result(bool $ready, array $codes, array $assertions, array $deferred): array
    {
        return [
            'status' => $ready ? 'ready' : 'blocked',
            'checkpoint_closed' => $ready,
            'dispatch_authorized' => false,
            'safe_error_codes' => $ready ? [] : $codes,
            'deferred_until_live_deployment' => $deferred,
            'recorded_assertions' => $assertions,
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function liveDeploymentDeferralAccepted(array $status): bool
    {
        return ($status['rotation_deferred_until'] ?? null) === 'live_deployment'
            && ($status['deletion_test_deferred_until'] ?? null) === 'live_deployment';
    }

    /**
     * @param  array<string, mixed>  $vendor
     * @return list<string>
     */
    private function elevenLabsVendorGaps(array $vendor): array
    {
        $codes = [];
        $products = $vendor['products'] ?? [];
        if (
            ! is_array($products)
            || ! in_array('eleven_v3_conversational', $products, true)
            || ! in_array('scribe_v2', $products, true)
        ) {
            $codes[] = 'elevenlabs_products_incomplete';
        }
        if (($vendor['api_host'] ?? null) !== self::REQUIRED_ELEVENLABS_HOST) {
            $codes[] = 'elevenlabs_host_not_india_residency';
        }
        if (($vendor['india_residency_asserted'] ?? false) !== true) {
            $codes[] = 'elevenlabs_india_residency_unproven';
        }
        if (($vendor['zero_retention_asserted'] ?? false) !== true) {
            $codes[] = 'elevenlabs_zero_retention_unproven';
        }
        if (($vendor['key_rotated'] ?? false) !== true) {
            $codes[] = 'elevenlabs_key_not_rotated';
        }
        if (! $this->nonEmptyId($vendor['deletion_test_evidence_id'] ?? null)) {
            $codes[] = 'elevenlabs_deletion_test_missing';
        }
        if (
            ! $this->sha256($vendor['dpa_sha256'] ?? null)
            || ! $this->sha256($vendor['agreement_sha256'] ?? null)
            || ! $this->sha256($vendor['subprocessor_sha256'] ?? null)
        ) {
            $codes[] = 'elevenlabs_executed_dpa_missing';
        }
        if (
            ! $this->timestampInFuture($vendor['valid_until'] ?? null)
            || ! $this->reviewedBeforeValid(
                $vendor['reviewed_at'] ?? null,
                $vendor['valid_until'] ?? null,
            )
        ) {
            $codes[] = 'elevenlabs_vendor_evidence_expired';
        }
        if (($vendor['approved_for_learner_content'] ?? false) === true) {
            $codes[] = 'unexpected_learner_content_approval';
        }

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $vendor
     * @return list<string>
     */
    private function sarvamVendorGaps(array $vendor): array
    {
        $codes = [];
        if (($vendor['executed_dpa_present'] ?? false) !== true || ! $this->sha256($vendor['dpa_sha256'] ?? null)) {
            $codes[] = 'sarvam_executed_dpa_missing';
        }

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $spend
     * @return list<string>
     */
    private function spendGaps(array $spend, string $provider, int $requiredCap): array
    {
        $codes = [];
        $cap = $this->positiveInt($spend['cap_inr'] ?? null);
        if (($spend['approved'] ?? false) !== true || $cap !== $requiredCap) {
            $codes[] = $provider.'_spend_unusable';
        }
        if (
            ! $this->timestampInFuture($spend['valid_until'] ?? null)
            || ! $this->reviewedBeforeValid(
                $spend['reviewed_at'] ?? null,
                $spend['valid_until'] ?? null,
            )
        ) {
            $codes[] = $provider.'_spend_expired';
        }
        if (($spend['content_dispatch_authorized'] ?? false) === true) {
            $codes[] = 'unexpected_dispatch_authorization';
        }

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $azure
     * @return list<string>
     */
    private function azureSpeechGaps(array $azure): array
    {
        if (
            ($azure['provider'] ?? null) !== 'azure-speech'
            || ! $this->sha256($azure['dpa_sha256'] ?? null)
            || ! $this->sha256($azure['agreement_sha256'] ?? null)
            || ! $this->sha256($azure['subprocessor_sha256'] ?? null)
        ) {
            return ['azure_speech_vendor_evidence_unusable'];
        }

        if (
            ! $this->timestampInFuture($azure['valid_until'] ?? null)
            || ! $this->reviewedBeforeValid(
                $azure['reviewed_at'] ?? null,
                $azure['valid_until'] ?? null,
            )
        ) {
            return ['azure_speech_vendor_evidence_expired'];
        }

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readSibling(string $directory, string $name): ?array
    {
        return $this->readJsonFromUsablePath($directory.DIRECTORY_SEPARATOR.$name);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJsonFromUsablePath(string $path): ?array
    {
        $usable = $this->usableEvidenceFile($path);

        return $usable === null ? null : $this->readJson($usable);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function usableEvidenceFile(string $path): ?string
    {
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
            || filesize($realPath) > self::MAXIMUM_EVIDENCE_BYTES
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

    private function requiredCap(string $provider): int
    {
        $key = $provider === 'sarvam'
            ? 'speech_routing.wave38.sarvam_cap_inr'
            : 'speech_routing.wave38.elevenlabs_cap_inr';
        $value = $this->positiveInt($this->config->get($key));

        return $value ?? 500;
    }

    private function timestampInFuture(mixed $value): bool
    {
        $instant = $this->instant($value);

        return $instant instanceof DateTimeImmutable && $instant > new DateTimeImmutable('now');
    }

    private function reviewedBeforeValid(mixed $reviewedAt, mixed $validUntil): bool
    {
        $reviewed = $this->instant($reviewedAt);
        $until = $this->instant($validUntil);

        return $reviewed instanceof DateTimeImmutable
            && $until instanceof DateTimeImmutable
            && $until > $reviewed;
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/', $value) === 1
            && ! str_contains(strtoupper($value), 'REPLACE');
    }

    private function nonEmptyId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/', $value) === 1
            && ! str_contains(strtoupper($value), 'REPLACE');
    }

    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
