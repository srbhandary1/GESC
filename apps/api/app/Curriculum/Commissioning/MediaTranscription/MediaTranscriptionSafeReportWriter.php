<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use Illuminate\Support\Str;
use JsonException;

final class MediaTranscriptionSafeReportWriter
{
    /**
     * @param  array<string, int|string>  $report
     */
    public function write(array $report, ?string $authorizedPath = null): string
    {
        $this->assertSafeReport($report);
        $path = $this->externalPath($authorizedPath);

        try {
            $encoded = json_encode(
                $report,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            )."\n";
        } catch (JsonException $exception) {
            throw new MediaTranscriptionRejected(
                'safe_report_write_failed',
                $exception,
            );
        }

        $temporary = $path.'.tmp-'.Str::lower((string) Str::ulid());
        if (
            file_put_contents($temporary, $encoded, LOCK_EX) === false
            || ! chmod($temporary, 0600)
            || ! rename($temporary, $path)
        ) {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            throw new MediaTranscriptionRejected('safe_report_write_failed');
        }

        return hash('sha256', $encoded);
    }

    /** @param array<string, int|string> $report */
    private function assertSafeReport(array $report): void
    {
        $expected = [
            'campaign_id',
            'state',
            'manifest_sha256',
            'configuration_sha256',
            'vendor_evidence_sha256',
            'price_evidence_sha256',
            'logical_assets',
            'logical_asset_cap',
            'reserved_http_attempts',
            'http_attempt_cap',
            'reserved_uploaded_bytes',
            'uploaded_byte_cap',
            'reserved_billable_milliseconds',
            'billable_millisecond_cap',
            'reserved_estimated_cost_microunits',
            'estimated_cost_microunits_cap',
            'provider_attempts',
            'provider_successes',
            'provider_failures',
            'human_reconciliations',
            'candidate_deletions_completed',
            'candidate_deletion_failures',
        ];
        if (array_keys($report) !== $expected) {
            throw new MediaTranscriptionRejected('safe_report_invalid');
        }
        foreach ($report as $key => $value) {
            if (in_array($key, [
                'campaign_id',
                'state',
                'manifest_sha256',
                'configuration_sha256',
                'vendor_evidence_sha256',
                'price_evidence_sha256',
            ], true)) {
                if (! is_string($value) || $value === '') {
                    throw new MediaTranscriptionRejected('safe_report_invalid');
                }

                continue;
            }
            if (! is_int($value) || $value < 0) {
                throw new MediaTranscriptionRejected('safe_report_invalid');
            }
        }
        if (
            ! Str::isUlid((string) $report['campaign_id'])
            || ! in_array($report['state'], [
                'running', 'provider_complete', 'completed', 'failed',
            ], true)
        ) {
            throw new MediaTranscriptionRejected('safe_report_invalid');
        }
        foreach ([
            'manifest_sha256',
            'configuration_sha256',
            'vendor_evidence_sha256',
            'price_evidence_sha256',
        ] as $hash) {
            if (preg_match('/\A[0-9a-f]{64}\z/', (string) $report[$hash]) !== 1) {
                throw new MediaTranscriptionRejected('safe_report_invalid');
            }
        }
    }

    private function externalPath(?string $authorizedPath): string
    {
        $path = $authorizedPath ?? config(
            'media_transcription.evidence.safe_report_file',
        );
        if (! is_string($path) || $path === '') {
            throw new MediaTranscriptionRejected('safe_report_write_failed');
        }
        $parent = realpath(dirname($path));
        $repository = realpath(base_path('../..'));
        $existingPermissions = is_file($path) ? fileperms($path) : null;

        if (
            ! str_starts_with($path, DIRECTORY_SEPARATOR)
            || $parent === false
            || $repository === false
            || str_starts_with(
                $parent.DIRECTORY_SEPARATOR,
                $repository.DIRECTORY_SEPARATOR,
            )
            || is_link($path)
            || (file_exists($path) && ! is_file($path))
            || ($existingPermissions !== null
                && ($existingPermissions & 0777) !== 0600)
        ) {
            throw new MediaTranscriptionRejected('safe_report_write_failed');
        }

        return $path;
    }
}
