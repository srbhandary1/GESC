<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use App\Curriculum\CanonicalJson;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final readonly class MediaTranscriptionConfiguration
{
    public function __construct(
        private Repository $config,
        private Application $application,
    ) {}

    /** @return array<string, mixed> */
    public function authorize(string $campaignId): array
    {
        if (
            ! Str::isUlid($campaignId)
            || ! $this->application->environment($this->string('required_environment'))
            || $this->config->get('media_transcription.enabled') !== true
            || $this->config->get('media_transcription.live_requests_enabled') !== true
        ) {
            $this->reject();
        }

        $manifest = $this->config->get('media_transcription.manifest');
        $caps = $this->config->get('media_transcription.caps');

        if (! is_array($manifest) || ! is_array($caps)) {
            $this->reject();
        }

        $manifestHash = CanonicalJson::sha256([
            'approval_reference' => $this->string('approval_reference'),
            'assets' => $manifest,
            'locale' => $this->string('provider.locale'),
            'organization_id' => $this->string('organization_id'),
            'program_id' => $this->string('program_id'),
        ]);

        if (
            $this->string('approval_reference') !== 'APP-0052'
            || $this->string('approved_request_sha256') !== 'cb489ec3f034861d256934aa0fcf5128a997af73ec6da4bd461453ebce02bdf4'
            || ! hash_equals($this->string('expected_manifest_sha256'), $manifestHash)
            || $manifest !== self::exactManifest()
            || count($manifest) !== 4
            || $caps !== [
                'logical_assets' => 4,
                'http_attempts' => 8,
                'uploaded_bytes' => 37_520_380,
                'billable_milliseconds' => 2_345_026,
                'estimated_cost_microunits' => 100_000_000,
            ]
            || $this->string('provider.key') !== 'azure-speech'
            || $this->string('provider.model') !== 'fast-transcription-2025-10-15-provider-managed'
            || $this->string('provider.region') !== 'centralindia'
            || $this->string('provider.api_version') !== '2025-10-15'
            || $this->string('provider.route') !== '/speechtotext/transcriptions:transcribe'
            || $this->string('provider.locale') !== 'en-IN'
            || $this->string('provider.profanity_filter_mode') !== 'None'
            || $this->integer('provider.connection_timeout_seconds') !== 3
            || $this->integer('provider.request_timeout_seconds') !== 300
            || $this->integer('provider.maximum_response_bytes') !== 1_048_576
            || $this->integer('candidate.unreconciled_expiry_days') !== 7
            || $this->integer('candidate.cleanup_target_minutes') !== 15
            || $this->integer('candidate.cleanup_hard_minutes') !== 60
            || $this->integer('candidate.deletion_max_attempts') !== 5
            || $this->integer('candidate.deletion_retry_seconds') !== 60
            || $this->integer('provenance_retention_days') !== 365
        ) {
            $this->reject();
        }

        $endpoint = $this->validatedEndpoint();
        $permit = $this->secureExternalFile('evidence.permit_file');
        $secret = $this->secureExternalFile('provider.secret_file');
        $vendor = $this->secureJsonEvidence(
            'evidence.vendor_file',
            'evidence.vendor_sha256',
        );
        $price = $this->secureJsonEvidence(
            'evidence.price_file',
            'evidence.price_sha256',
        );

        if (! hash_equals($campaignId, $permit)) {
            $this->reject();
        }

        $credentialKind = $this->string('provider.credential_kind');
        if (! in_array($credentialKind, ['entra-token', 'subscription-key'], true)) {
            $this->reject();
        }

        $credential = trim($secret);
        if ($credential === '' || str_contains($credential, "\n")) {
            $this->reject();
        }

        $vendorPayload = $this->decode($vendor);
        $pricePayload = $this->decode($price);
        $credentialVersion = $this->evidenceString($vendorPayload, 'credential_version');
        $validUntil = $this->exactUtcInstant(
            $this->evidenceString($vendorPayload, 'valid_until'),
        );
        $priceCapturedOn = $this->exactUtcInstant(
            $this->evidenceString($pricePayload, 'captured_on'),
        );
        $dpaEffectiveDate = $this->exactDate(
            $this->evidenceString($vendorPayload, 'dpa_effective_date'),
        );

        if (
            ($vendorPayload['schema_version'] ?? null) !== 1
            ||
            $this->evidenceString($vendorPayload, 'provider') !== 'azure-speech'
            || $this->evidenceString($vendorPayload, 'service') !== 'fast-transcription'
            || $this->evidenceString($vendorPayload, 'region') !== 'centralindia'
            || $this->evidenceString($vendorPayload, 'api_version') !== '2025-10-15'
            || $this->evidenceString($vendorPayload, 'resource_host') !== parse_url($endpoint, PHP_URL_HOST)
            || $this->evidenceString($vendorPayload, 'data_logging') !== 'disabled'
            || $this->evidenceString($vendorPayload, 'provider_storage') !== 'none'
            || ($vendorPayload['training'] ?? null) !== false
            || ($vendorPayload['model_improvement'] ?? null) !== false
            || ($vendorPayload['human_quality_review'] ?? null) !== false
            || ($vendorPayload['endpoint_logging'] ?? null) !== false
            || ($vendorPayload['candidate_store_application_encryption'] ?? null) !== true
            || ($vendorPayload['candidate_store_public_access'] ?? null) !== false
            || ($vendorPayload['candidate_backup_max_days'] ?? null) !== 30
            || ($vendorPayload['provenance_retention_days'] ?? null) !== 365
            || $dpaEffectiveDate->lt($this->exactDate('2026-05-01'))
            || ! $this->isSha256($vendorPayload['subscription_id_sha256'] ?? null)
            || ! $this->isSha256($vendorPayload['resource_id_sha256'] ?? null)
            || ! $this->isSha256($vendorPayload['agreement_sha256'] ?? null)
            || ! $this->isSha256($vendorPayload['dpa_sha256'] ?? null)
            || ! $this->isSha256($vendorPayload['product_terms_sha256'] ?? null)
            || ! $this->isSha256($vendorPayload['subprocessor_snapshot_sha256'] ?? null)
            || $this->evidenceString($vendorPayload, 'contracting_entity') === ''
            || $this->evidenceString($vendorPayload, 'privacy_review_reference') === ''
            || $this->evidenceString($vendorPayload, 'legal_review_reference') === ''
            || $validUntil->isPast()
        ) {
            $this->reject();
        }

        $rate = $pricePayload['microunits_per_billable_second'] ?? null;
        if (
            $this->evidenceString($pricePayload, 'approval_reference') !== 'APP-0052'
            || $this->evidenceString($pricePayload, 'manifest_sha256') !== $manifestHash
            || $this->evidenceString($pricePayload, 'currency') !== 'INR'
            || trim($this->evidenceString($pricePayload, 'conversion_source')) === ''
            || ! str_starts_with(
                $this->evidenceString($pricePayload, 'official_price_source'),
                'https://azure.microsoft.com/',
            )
            || ! $this->isSha256(
                $pricePayload['conversion_evidence_sha256'] ?? null,
            )
            || ! is_int($rate)
            || $rate < 1
            || $rate > 100_000_000
            || $priceCapturedOn->toDateString()
                !== CarbonImmutable::now('UTC')->toDateString()
            || $priceCapturedOn->isFuture()
            || self::maximumBillableSeconds() * $rate > 100_000_000
        ) {
            $this->reject();
        }

        $safeReportFile = $this->externalOutputPath('evidence.safe_report_file');
        $configuration = [
            'api_version' => '2025-10-15',
            'candidate_backup_max_days' => 30,
            'candidate_cleanup_hard_minutes' => 60,
            'candidate_cleanup_target_minutes' => 15,
            'candidate_disk' => $this->string('candidate.disk'),
            'candidate_unreconciled_expiry_days' => 7,
            'connection_timeout_seconds' => 3,
            'credential_kind' => $credentialKind,
            'endpoint_host' => parse_url($endpoint, PHP_URL_HOST),
            'locale' => 'en-IN',
            'model' => $this->string('provider.model'),
            'maximum_response_bytes' => 1_048_576,
            'profanity_filter_mode' => 'None',
            'provenance_retention_days' => $this->integer(
                'provenance_retention_days',
            ),
            'provider' => 'azure-speech',
            'region' => 'centralindia',
            'request_timeout_seconds' => 300,
            'route' => '/speechtotext/transcriptions:transcribe',
        ];

        return [
            'approval_reference' => 'APP-0052',
            'approved_request_sha256' => $this->string('approved_request_sha256'),
            'auth_header' => $credentialKind === 'entra-token'
                ? ['Authorization' => 'Bearer '.$credential]
                : ['Ocp-Apim-Subscription-Key' => $credential],
            'caps' => $caps,
            'configuration' => $configuration,
            'configuration_sha256' => CanonicalJson::sha256($configuration),
            'credential_version' => $credentialVersion,
            'endpoint' => $endpoint,
            'manifest' => $manifest,
            'manifest_sha256' => $manifestHash,
            'organization_id' => $this->string('organization_id'),
            'price_evidence_sha256' => hash('sha256', $price),
            'rate_microunits_per_second' => $rate,
            'safe_report_file' => $safeReportFile,
            'program_id' => $this->string('program_id'),
            'vendor_evidence_sha256' => hash('sha256', $vendor),
        ];
    }

    private function validatedEndpoint(): string
    {
        $endpoint = rtrim($this->string('provider.endpoint'), '/');
        $allowedHost = strtolower($this->string('provider.allowed_host'));
        $parts = parse_url($endpoint);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';

        if (
            ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || $host === ''
            || ! hash_equals($allowedHost, $host)
            || ! str_ends_with($host, '.cognitiveservices.azure.com')
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (($parts['path'] ?? '') !== '')
        ) {
            $this->reject();
        }

        return $endpoint;
    }

    private function secureJsonEvidence(string $pathKey, string $hashKey): string
    {
        $content = $this->secureExternalFile($pathKey);
        $expected = strtolower($this->string($hashKey));

        if (
            preg_match('/\A[0-9a-f]{64}\z/', $expected) !== 1
            || ! hash_equals($expected, hash('sha256', $content))
        ) {
            $this->reject();
        }

        $this->decode($content);

        return $content;
    }

    private function secureExternalFile(string $key): string
    {
        $path = $this->string($key);
        $real = realpath($path);
        $repository = realpath(base_path('../..'));
        $permissions = $real === false ? false : fileperms($real);

        if (
            $real === false
            || $repository === false
            || is_link($path)
            || ! is_file($real)
            || str_starts_with($real.DIRECTORY_SEPARATOR, $repository.DIRECTORY_SEPARATOR)
            || $permissions === false
            || ($permissions & 0777) !== 0600
        ) {
            $this->reject();
        }

        $content = file_get_contents($real);
        if (! is_string($content) || $content === '') {
            $this->reject();
        }

        return $content;
    }

    private function externalOutputPath(string $key): string
    {
        $path = $this->string($key);
        $parent = realpath(dirname($path));
        $repository = realpath(base_path('../..'));

        if (
            $path === ''
            || ! str_starts_with($path, DIRECTORY_SEPARATOR)
            || $parent === false
            || $repository === false
            || str_starts_with($parent.DIRECTORY_SEPARATOR, $repository.DIRECTORY_SEPARATOR)
            || is_link($path)
        ) {
            $this->reject();
        }

        return $path;
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        try {
            $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->reject();
        }

        if (! is_array($value)) {
            $this->reject();
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function evidenceString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            $this->reject();
        }

        return $value;
    }

    private function string(string $key): string
    {
        $value = $this->config->get('media_transcription.'.$key);

        if (! is_string($value) || $value === '') {
            $this->reject();
        }

        return $value;
    }

    private function integer(string $key): int
    {
        $value = $this->config->get('media_transcription.'.$key);

        if (! is_int($value) || $value < 0) {
            $this->reject();
        }

        return $value;
    }

    private function exactUtcInstant(string $value): CarbonImmutable
    {
        try {
            $instant = CarbonImmutable::createFromFormat(
                '!Y-m-d\\TH:i:s\\Z',
                $value,
                'UTC',
            );
        } catch (Throwable) {
            $this->reject();
        }

        if (
            ! $instant instanceof CarbonImmutable
            || $instant->format('Y-m-d\\TH:i:s\\Z') !== $value
        ) {
            $this->reject();
        }

        return $instant;
    }

    private function exactDate(string $value): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (Throwable) {
            $this->reject();
        }

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            $this->reject();
        }

        return $date;
    }

    private function reject(): never
    {
        throw new MediaTranscriptionRejected('provider_not_authorized');
    }

    private function isSha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[0-9a-f]{64}\z/', $value) === 1;
    }

    /** @return list<array<string, int|string>> */
    private static function exactManifest(): array
    {
        return [
            [
                'sequence' => 1,
                'part_key' => 'introduction',
                'media_asset_id' => '01KZNFYHCJSDWSPK08ZAC7G3AJ',
                'media_rights_revision_id' => '01KZNKNFWGSRDBD57H18YCDPG8',
                'source_sha256' => '6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa',
                'source_bytes' => 4_968_408,
                'source_duration_milliseconds' => 310_526,
            ],
            [
                'sequence' => 2,
                'part_key' => 'practice',
                'media_asset_id' => '01KZNFYJ1M911SB8YVH9P4EZZ0',
                'media_rights_revision_id' => '01KZNKNFXKFRSVXRKPYWHDCF7C',
                'source_sha256' => '75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7',
                'source_bytes' => 7_357_881,
                'source_duration_milliseconds' => 459_868,
            ],
            [
                'sequence' => 3,
                'part_key' => 'conversation',
                'media_asset_id' => '01KZNFYJVSZRPZCEPTTQ4QTMNT',
                'media_rights_revision_id' => '01KZNKNFYDDC31CV2BR155DXVR',
                'source_sha256' => '8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70',
                'source_bytes' => 2_157_215,
                'source_duration_milliseconds' => 134_826,
            ],
            [
                'sequence' => 4,
                'part_key' => 'interaction',
                'media_asset_id' => '01KZNFYK75V9WF8R5VCWRRZHNN',
                'media_rights_revision_id' => '01KZNKNFZ7A5Z9JPSC2AV0QPZQ',
                'source_sha256' => '4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e',
                'source_bytes' => 4_276_686,
                'source_duration_milliseconds' => 267_293,
            ],
        ];
    }

    private static function maximumBillableSeconds(): int
    {
        return array_sum(array_map(
            static fn (array $asset): int => (int) ceil(
                (int) $asset['source_duration_milliseconds'] / 1000,
            ) * 2,
            self::exactManifest(),
        ));
    }
}
