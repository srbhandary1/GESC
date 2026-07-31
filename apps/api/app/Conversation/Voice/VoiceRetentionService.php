<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

final class VoiceRetentionService
{
    /**
     * @return array{recordings_examined: int, recordings_purged: int}
     */
    public function purgeDue(): array
    {
        $now = Date::now()->toImmutable();
        $ids = DB::table('voice_recordings')
            ->where(function ($query) use ($now): void {
                $query
                    ->where(function ($source) use ($now): void {
                        $source
                            ->whereNull('source_deleted_at')
                            ->where('source_delete_by', '<=', $now);
                    })
                    ->orWhere(function ($transcript) use ($now): void {
                        $transcript
                            ->whereNotNull('transcript_ciphertext')
                            ->where('transcript_expires_at', '<=', $now);
                    });
            })
            ->orderBy('id')
            ->limit(500)
            ->pluck('id');
        $purged = 0;

        foreach ($ids as $id) {
            if (
                is_string($id)
                && DB::transaction(fn (): bool => $this->purgeOne($id, $now), 3)
            ) {
                $purged++;
            }
        }

        return [
            'recordings_examined' => $ids->count(),
            'recordings_purged' => $purged,
        ];
    }

    private function purgeOne(
        string $recordingId,
        \DateTimeImmutable $now,
    ): bool {
        $row = DB::table('voice_recordings')
            ->where('id', $recordingId)
            ->lockForUpdate()
            ->first();

        if (! $row instanceof stdClass) {
            return false;
        }

        $sourceDue = $row->source_deleted_at === null
            && $this->isDue($row->source_delete_by, $now);
        $transcriptDue = is_string($row->transcript_ciphertext)
            && $row->transcript_ciphertext !== ''
            && $row->transcript_expires_at !== null
            && $this->isDue($row->transcript_expires_at, $now);
        $sourceDeleted = $row->source_deleted_at !== null;

        if ($sourceDue) {
            $key = $row->source_object_key;
            $sourceDeleted = ! is_string($key)
                || $key === ''
                || Storage::disk(
                    (string) config('voice.storage_disk', 'local'),
                )->delete($key);
        }

        if (! $sourceDeleted && ! $transcriptDue) {
            return false;
        }

        DB::table('voice_recordings')
            ->where('organization_id', $row->organization_id)
            ->where('id', $recordingId)
            ->update([
                'status' => 'expired_deleted',
                'source_object_key' => $sourceDeleted
                    ? null
                    : $row->source_object_key,
                'source_deleted_at' => $sourceDeleted
                    ? ($row->source_deleted_at ?? $now)
                    : null,
                'transcript_ciphertext' => $transcriptDue
                    ? null
                    : $row->transcript_ciphertext,
                'transcript_expires_at' => $transcriptDue
                    ? $now
                    : $row->transcript_expires_at,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);

        $deletion = DB::table('voice_deletion_requests')
            ->where('organization_id', $row->organization_id)
            ->where('voice_recording_id', $recordingId);
        $deletionValues = [
            'status' => $sourceDeleted
                ? 'completed'
                : 'partially_completed',
            'source_deleted' => $sourceDeleted,
            'transcript_deleted' => $transcriptDue
                || $row->transcript_ciphertext === null,
            'provider_deletion_status' => 'not_required',
            'completed_at' => $sourceDeleted ? $now : null,
        ];

        if ($deletion->exists()) {
            $deletion->update($deletionValues);
        } else {
            DB::table('voice_deletion_requests')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $row->organization_id,
                'voice_recording_id' => $recordingId,
                'learner_membership_id' => $row->learner_membership_id,
                'reason' => 'expiry',
                'requested_at' => $now,
                ...$deletionValues,
            ]);
        }

        return true;
    }

    private function isDue(
        mixed $value,
        \DateTimeImmutable $now,
    ): bool {
        return (is_string($value) || $value instanceof \DateTimeInterface)
            && Date::parse($value)->lessThanOrEqualTo($now);
    }
}
