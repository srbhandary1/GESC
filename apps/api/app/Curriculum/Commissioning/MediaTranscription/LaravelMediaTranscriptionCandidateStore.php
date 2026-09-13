<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

use App\Curriculum\CanonicalJson;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final readonly class LaravelMediaTranscriptionCandidateStore implements MediaTranscriptionCandidateStore
{
    public function __construct(
        private FilesystemManager $filesystems,
        private Encrypter $encrypter,
    ) {}

    public function put(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
        MediaTranscriptionCandidate $candidate,
    ): string {
        $key = $this->key(
            $organizationId,
            $campaignId,
            $campaignAssetId,
            $providerAttemptId,
        );
        $plaintext = CanonicalJson::encode($candidate->toArray());
        if (strlen($plaintext) > 1_048_576) {
            throw new MediaTranscriptionRejected('candidate_storage_failed');
        }
        $digest = hash('sha256', $plaintext);

        try {
            $ciphertext = $this->encrypter->encryptString($plaintext);
            if (! $this->disk()->put($key, $ciphertext, ['visibility' => 'private'])) {
                throw new MediaTranscriptionRejected('candidate_storage_failed');
            }

            $stored = $this->read(
                $organizationId,
                $campaignId,
                $campaignAssetId,
                $providerAttemptId,
                $digest,
            );
            if (! hash_equals($digest, CanonicalJson::sha256($stored->toArray()))) {
                throw new MediaTranscriptionRejected('candidate_storage_failed');
            }

            return $digest;
        } catch (MediaTranscriptionRejected $exception) {
            try {
                $this->disk()->delete($key);
            } catch (Throwable) {
                // The campaign runner records a durable deletion request.
            }
            throw $exception;
        } catch (Throwable $exception) {
            try {
                $this->disk()->delete($key);
            } catch (Throwable) {
                // The campaign runner records a durable deletion request.
            }
            throw new MediaTranscriptionRejected(
                'candidate_storage_failed',
                previous: $exception,
            );
        }
    }

    public function read(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
        string $expectedSha256,
    ): MediaTranscriptionCandidate {
        if (preg_match('/\A[0-9a-f]{64}\z/', $expectedSha256) !== 1) {
            throw new MediaTranscriptionRejected('candidate_unavailable');
        }

        try {
            $ciphertext = $this->disk()->get(
                $this->key(
                    $organizationId,
                    $campaignId,
                    $campaignAssetId,
                    $providerAttemptId,
                ),
            );
            if (! is_string($ciphertext) || $ciphertext === '') {
                throw new MediaTranscriptionRejected('candidate_unavailable');
            }

            $plaintext = $this->encrypter->decryptString($ciphertext);
            if (strlen($plaintext) > 1_048_576) {
                throw new MediaTranscriptionRejected('candidate_unavailable');
            }
            if (! hash_equals($expectedSha256, hash('sha256', $plaintext))) {
                throw new MediaTranscriptionRejected('candidate_unavailable');
            }

            return $this->decode($plaintext);
        } catch (MediaTranscriptionRejected $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MediaTranscriptionRejected(
                'candidate_unavailable',
                previous: $exception,
            );
        }
    }

    public function digest(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
    ): ?string {
        $key = $this->key(
            $organizationId,
            $campaignId,
            $campaignAssetId,
            $providerAttemptId,
        );

        try {
            $disk = $this->disk();
            if (! $disk->exists($key)) {
                return null;
            }
            $ciphertext = $disk->get($key);
            if (! is_string($ciphertext) || $ciphertext === '') {
                throw new MediaTranscriptionRejected('candidate_unavailable');
            }
            $plaintext = $this->encrypter->decryptString($ciphertext);
            if (strlen($plaintext) > 1_048_576) {
                throw new MediaTranscriptionRejected('candidate_unavailable');
            }
            $candidate = $this->decode($plaintext);
            if (! hash_equals(
                $plaintext,
                CanonicalJson::encode($candidate->toArray()),
            )) {
                throw new MediaTranscriptionRejected('candidate_unavailable');
            }

            return hash('sha256', $plaintext);
        } catch (MediaTranscriptionRejected $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MediaTranscriptionRejected(
                'candidate_unavailable',
                previous: $exception,
            );
        }
    }

    public function delete(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
    ): void {
        try {
            $disk = $this->disk();
            $key = $this->key(
                $organizationId,
                $campaignId,
                $campaignAssetId,
                $providerAttemptId,
            );

            if ($disk->exists($key) && ! $disk->delete($key)) {
                throw new MediaTranscriptionRejected('candidate_deletion_failed');
            }
        } catch (MediaTranscriptionRejected $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MediaTranscriptionRejected(
                'candidate_deletion_failed',
                previous: $exception,
            );
        }
    }

    public function exists(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
    ): bool {
        try {
            return $this->disk()->exists(
                $this->key(
                    $organizationId,
                    $campaignId,
                    $campaignAssetId,
                    $providerAttemptId,
                ),
            );
        } catch (Throwable $exception) {
            throw new MediaTranscriptionRejected(
                'candidate_storage_failed',
                previous: $exception,
            );
        }
    }

    private function disk(): Filesystem
    {
        $name = config('media_transcription.candidate.disk');

        if (! is_string($name) || $name === '') {
            throw new MediaTranscriptionRejected('candidate_storage_failed');
        }

        return $this->filesystems->disk($name);
    }

    private function key(
        string $organizationId,
        string $campaignId,
        string $campaignAssetId,
        string $providerAttemptId,
    ): string {
        foreach (
            [$organizationId, $campaignId, $campaignAssetId, $providerAttemptId] as $id
        ) {
            if (! Str::isUlid($id)) {
                throw new MediaTranscriptionRejected('candidate_storage_failed');
            }
        }

        return 'v1/'.$organizationId.'/'.$campaignId.'/'.$campaignAssetId
            .'/'.$providerAttemptId.'.candidate';
    }

    private function decode(string $plaintext): MediaTranscriptionCandidate
    {
        try {
            $payload = json_decode($plaintext, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MediaTranscriptionRejected(
                'candidate_unavailable',
                previous: $exception,
            );
        }

        if (
            ! is_array($payload)
            || ! is_string($payload['transcript'] ?? null)
            || ! is_string($payload['locale'] ?? null)
            || ! is_array($payload['segments'] ?? null)
        ) {
            throw new MediaTranscriptionRejected('candidate_unavailable');
        }

        $segments = [];
        foreach ($payload['segments'] as $segment) {
            if (
                ! is_array($segment)
                || ! is_int($segment['sequence'] ?? null)
                || ! is_string($segment['text'] ?? null)
                || ! is_int($segment['offset_milliseconds'] ?? null)
                || ! is_int($segment['duration_milliseconds'] ?? null)
                || ! is_int($segment['confidence_bps'] ?? null)
            ) {
                throw new MediaTranscriptionRejected('candidate_unavailable');
            }

            $segments[] = new MediaTranscriptionSegment(
                sequence: $segment['sequence'],
                text: $segment['text'],
                offsetMilliseconds: $segment['offset_milliseconds'],
                durationMilliseconds: $segment['duration_milliseconds'],
                confidenceBps: $segment['confidence_bps'],
            );
        }

        return new MediaTranscriptionCandidate(
            transcript: $payload['transcript'],
            locale: $payload['locale'],
            segments: $segments,
        );
    }
}
