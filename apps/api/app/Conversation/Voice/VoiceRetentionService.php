<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class VoiceRetentionService
{
    public function __construct(private VoiceDeletionService $deletions) {}

    /**
     * @return array{
     *   recordings_examined: int,
     *   recordings_purged: int,
     *   deletion_attempts: int,
     *   deletion_failures: int
     * }
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
        $requestIds = [];

        foreach ($ids as $id) {
            if (! is_string($id)) {
                continue;
            }

            $requestId = $this->deletions->requestExpiry($id, $now);

            if ($requestId !== null) {
                $requestIds[] = $requestId;
            }
        }

        $processing = $this->deletions->processDue(500);
        $purged = $requestIds === []
            ? 0
            : DB::table('voice_deletion_requests')
                ->whereIn('id', array_values(array_unique($requestIds)))
                ->where('status', 'completed')
                ->count();

        return [
            'recordings_examined' => $ids->count(),
            'recordings_purged' => (int) $purged,
            'deletion_attempts' => $processing['attempted'],
            'deletion_failures' => $processing['failed'],
        ];
    }
}
