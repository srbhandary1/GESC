<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Content-free operational health for the voice source-deletion outbox.
 *
 * Counts and ages are deliberately global aggregates. Tenant identifiers,
 * recording identifiers, object keys and payloads never leave this boundary.
 */
final readonly class VoiceDeletionOutboxMonitor
{
    private const string EVENT_TYPE = 'voice.source-deletion.requested';

    private const string AGGREGATE_TYPE = 'voice_deletion_request';

    /**
     * @return array{
     *   status: 'healthy'|'unhealthy',
     *   active: int,
     *   due: int,
     *   retrying: int,
     *   dead_lettered: int,
     *   stale: int,
     *   oldest_active_age_seconds: ?int,
     *   stale_after_seconds: int
     * }
     */
    public function snapshot(): array
    {
        $now = Date::now()->toImmutable();
        $staleAfter = max(
            60,
            (int) config('voice.deletion_outbox_stale_seconds', 3600),
        );
        $active = $this->events()
            ->whereNull('published_at')
            ->whereNull('dead_lettered_at');
        $activeCount = (clone $active)->count();
        $deadLettered = $this->events()
            ->whereNull('published_at')
            ->whereNotNull('dead_lettered_at')
            ->count();
        $stale = (clone $active)
            ->where('occurred_at', '<=', $now->subSeconds($staleAfter))
            ->count();
        $oldest = (clone $active)->min('occurred_at');
        $oldestAge = is_string($oldest) && $oldest !== ''
            ? max(
                0,
                $now->getTimestamp() - Date::parse($oldest)->getTimestamp(),
            )
            : null;

        return [
            'status' => $deadLettered === 0 && $stale === 0
                ? 'healthy'
                : 'unhealthy',
            'active' => $activeCount,
            'due' => (clone $active)
                ->where('available_at', '<=', $now)
                ->count(),
            'retrying' => (clone $active)
                ->where('attempts', '>', 0)
                ->count(),
            'dead_lettered' => $deadLettered,
            'stale' => $stale,
            'oldest_active_age_seconds' => $oldestAge,
            'stale_after_seconds' => $staleAfter,
        ];
    }

    public function requeue(string $outboxEventId): bool
    {
        return DB::transaction(function () use ($outboxEventId): bool {
            $event = $this->events()
                ->where('id', $outboxEventId)
                ->whereNull('published_at')
                ->whereNotNull('dead_lettered_at')
                ->lockForUpdate()
                ->first();

            if (! $event instanceof stdClass) {
                return false;
            }

            return $this->events()
                ->where('id', $outboxEventId)
                ->whereNull('published_at')
                ->whereNotNull('dead_lettered_at')
                ->update([
                    'available_at' => Date::now()->toImmutable(),
                    'attempts' => 0,
                    'last_error_code' => null,
                    'dead_lettered_at' => null,
                    'dead_letter_reason_code' => null,
                ]) === 1;
        }, 3);
    }

    private function events(): Builder
    {
        return DB::table('outbox_events')
            ->where('event_type', self::EVENT_TYPE)
            ->where('aggregate_type', self::AGGREGATE_TYPE);
    }
}
