<?php

declare(strict_types=1);

namespace App\Conversation\Routing;

use DateTimeImmutable;
use JsonException;
use RuntimeException;

final readonly class Wave38EvidenceRecorder
{
    private const int MAXIMUM_PACKET_BYTES = 65536;

    private const int MAXIMUM_SOURCE_DOCUMENT_BYTES = 8_388_608;

    /**
     * @return array{status: 'recorded'|'blocked', safe_error_codes: list<string>}
     */
    public function recordRotation(string $packetDirectory, string $rotatedAt): array
    {
        $directory = $this->usablePacketDirectory($packetDirectory);
        if ($directory === null) {
            return $this->blocked(['wave38_packet_unusable']);
        }
        if ($this->instant($rotatedAt) === null) {
            return $this->blocked(['wave38_timestamp_invalid']);
        }

        $this->writeJson($directory.'/elevenlabs-rotation-receipt.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'rotated_at' => $rotatedAt,
            'previous_key_revoked' => true,
            'new_secret_outside_git' => true,
            'note' => 'Receipt only. The replacement secret value is not stored here.',
        ]);

        $vendor = $this->readJson($directory.'/elevenlabs-vendor-evidence.json') ?? [];
        $vendor['key_rotated'] = true;
        $this->writeJson($directory.'/elevenlabs-vendor-evidence.json', $vendor);

        return ['status' => 'recorded', 'safe_error_codes' => []];
    }

    /**
     * @return array{status: 'recorded'|'blocked', safe_error_codes: list<string>}
     */
    public function recordDeletion(
        string $packetDirectory,
        string $evidenceId,
        string $deletedAt,
    ): array {
        $directory = $this->usablePacketDirectory($packetDirectory);
        if ($directory === null) {
            return $this->blocked(['wave38_packet_unusable']);
        }
        if (! $this->safeId($evidenceId)) {
            return $this->blocked(['wave38_evidence_id_invalid']);
        }
        if ($this->instant($deletedAt) === null) {
            return $this->blocked(['wave38_timestamp_invalid']);
        }

        $this->writeJson($directory.'/elevenlabs-deletion-test.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'products' => ['eleven_v3_conversational', 'scribe_v2'],
            'evidence_id' => $evidenceId,
            'deleted_at' => $deletedAt,
            'content_free' => true,
            'audio_retained' => false,
            'transcript_retained' => false,
        ]);

        $vendor = $this->readJson($directory.'/elevenlabs-vendor-evidence.json') ?? [];
        $vendor['deletion_test_evidence_id'] = $evidenceId;
        $this->writeJson($directory.'/elevenlabs-vendor-evidence.json', $vendor);

        return ['status' => 'recorded', 'safe_error_codes' => []];
    }

    /**
     * @return array{
     *   status: 'recorded'|'blocked',
     *   safe_error_codes: list<string>,
     *   slot?: string,
     *   sha256?: string
     * }
     */
    public function hashDocument(
        string $packetDirectory,
        string $slot,
        string $absolutePath,
        bool $executed,
    ): array {
        $directory = $this->usablePacketDirectory($packetDirectory);
        if ($directory === null) {
            return $this->blocked(['wave38_packet_unusable']);
        }
        if (! in_array($slot, [
            'elevenlabs-dpa',
            'elevenlabs-agreement',
            'elevenlabs-subprocessor',
            'sarvam-dpa',
        ], true)) {
            return $this->blocked(['wave38_hash_slot_invalid']);
        }
        $source = $this->usableSourceDocument($absolutePath);
        if ($source === null) {
            return $this->blocked(['wave38_source_document_unusable']);
        }

        $sha256 = hash_file('sha256', $source);
        if ($sha256 === false) {
            return $this->blocked(['wave38_source_document_unusable']);
        }

        if (! $executed) {
            return [
                'status' => 'recorded',
                'safe_error_codes' => [],
                'slot' => $slot,
                'sha256' => $sha256,
            ];
        }

        if (str_starts_with($slot, 'elevenlabs-')) {
            $vendorPath = $directory.'/elevenlabs-vendor-evidence.json';
            $field = match ($slot) {
                'elevenlabs-dpa' => 'dpa_sha256',
                'elevenlabs-agreement' => 'agreement_sha256',
                default => 'subprocessor_sha256',
            };
            $vendor = $this->readJson($vendorPath) ?? [];
            $vendor[$field] = $sha256;
            $this->writeJson($vendorPath, $vendor);
        } else {
            $vendorPath = $directory.'/sarvam-vendor-evidence.json';
            $vendor = $this->readJson($vendorPath) ?? [];
            $vendor['dpa_sha256'] = $sha256;
            $vendor['executed_dpa_present'] = true;
            $this->writeJson($vendorPath, $vendor);
        }

        return [
            'status' => 'recorded',
            'safe_error_codes' => [],
            'slot' => $slot,
            'sha256' => $sha256,
        ];
    }

    /**
     * @param  list<string>  $codes
     * @return array{status: 'blocked', safe_error_codes: list<string>}
     */
    private function blocked(array $codes): array
    {
        return [
            'status' => 'blocked',
            'safe_error_codes' => $codes,
        ];
    }

    private function usablePacketDirectory(string $path): ?string
    {
        $realPath = realpath($path);
        $repositoryRoot = dirname(dirname(base_path()));

        if (
            $path === ''
            || $path[0] !== '/'
            || $realPath === false
            || is_link($path)
            || ! is_dir($realPath)
            || str_starts_with($realPath.'/', $repositoryRoot.'/')
            || (fileperms($realPath) & 0777) !== 0700
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

    private function usableSourceDocument(string $path): ?string
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
            || ! in_array(fileperms($realPath) & 0777, [0400, 0600], true)
            || filesize($realPath) === false
            || filesize($realPath) < 1
            || filesize($realPath) > self::MAXIMUM_SOURCE_DOCUMENT_BYTES
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

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        if (strlen($json) > self::MAXIMUM_PACKET_BYTES) {
            throw new RuntimeException('wave38_packet_too_large');
        }
        if (file_put_contents($path, $json."\n") === false || ! chmod($path, 0600)) {
            throw new RuntimeException('wave38_packet_write_failed');
        }
    }

    private function instant(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function safeId(string $value): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/', $value) === 1
            && ! str_contains(strtoupper($value), 'REPLACE');
    }
}
