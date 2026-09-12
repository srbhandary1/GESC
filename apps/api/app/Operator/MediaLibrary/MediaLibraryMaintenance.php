<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary;

use App\Media\MediaObjectStore;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

final readonly class MediaLibraryMaintenance
{
    private const int MAX_DELETE_ATTEMPTS = 6;

    private const int DELETE_RETRY_MINUTES = 5;

    public function __construct(private MediaObjectStore $objects) {}

    /**
     * @return array{
     *     expired: int,
     *     deleted: int,
     *     retried: int,
     *     dead_lettered: int,
     *     unavailable: int,
     *     overdue_cleanup_backlog: int,
     *     dead_letter_backlog: int
     * }
     */
    public function run(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $expired = $this->expireDue($limit);
        $deletions = $this->processDueDeletions($limit);
        $now = CarbonImmutable::now();
        $hardMinutes = $this->positiveConfig(
            'media.quarantine_cleanup_hard_minutes',
            60,
        );
        $overdue = DB::table('media_object_deletion_requests')
            ->whereIn('state', ['pending', 'retrying'])
            ->where('created_at', '<=', $now->subMinutes($hardMinutes))
            ->count();
        $deadLetters = DB::table('media_object_deletion_requests')
            ->where('state', 'dead_letter')
            ->count();

        return [
            'expired' => $expired,
            ...$deletions,
            'overdue_cleanup_backlog' => $overdue,
            'dead_letter_backlog' => $deadLetters,
        ];
    }

    private function expireDue(int $limit): int
    {
        $now = CarbonImmutable::now();
        $uploadIds = DB::table('media_uploads')
            ->where(function ($query) use ($now): void {
                $query
                    ->where(function ($uploading) use ($now): void {
                        $uploading
                            ->where('state', 'uploading')
                            ->where('expires_at', '<=', $now);
                    })
                    ->orWhere(function ($review) use ($now): void {
                        $review
                            ->where('state', 'scan_clean')
                            ->whereNotNull('review_expires_at')
                            ->where('review_expires_at', '<=', $now);
                    })
                    ->orWhere(function ($failed) use ($now): void {
                        $failed
                            ->whereIn('state', [
                                'inspection_failed',
                                'scan_failed',
                                'promotion_failed',
                            ])
                            ->where('expires_at', '<=', $now);
                    });
            })
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->filter(static fn (mixed $id): bool => is_string($id))
            ->values()
            ->all();
        $expired = 0;

        foreach ($uploadIds as $uploadId) {
            if ($this->expireOne($uploadId, $now)) {
                $expired++;
            }
        }

        return $expired;
    }

    private function expireOne(
        string $uploadId,
        CarbonInterface $now,
    ): bool {
        return DB::transaction(function () use ($uploadId, $now): bool {
            $upload = DB::table('media_uploads')
                ->where('id', $uploadId)
                ->lockForUpdate()
                ->first([
                    'id',
                    'organization_id',
                    'state',
                    'version',
                    'whole_object_hash',
                    'quarantine_object_key',
                    'selected_scan_id',
                    'expires_at',
                    'review_expires_at',
                ]);

            if (! $upload instanceof stdClass || ! $this->isDue($upload, $now)) {
                return false;
            }

            $organizationId = $this->string($upload, 'organization_id');
            $version = $this->integer($upload, 'version');
            $nextVersion = $version + 1;
            $updated = DB::table('media_uploads')
                ->where('organization_id', $organizationId)
                ->where('id', $uploadId)
                ->where('version', $version)
                ->where('state', $this->string($upload, 'state'))
                ->update([
                    'state' => 'expired',
                    'version' => $nextVersion,
                    'decided_by_membership_id' => null,
                    'decision_reason_key' => 'media_candidate_expired',
                    'decided_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                return false;
            }

            DB::table('media_upload_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'media_upload_id' => $uploadId,
                'event_type' => 'upload.expired',
                'upload_version' => $nextVersion,
                'media_security_scan_id' => $this->nullableString(
                    $upload,
                    'selected_scan_id',
                ),
                'media_asset_id' => null,
                'actor_membership_id' => null,
                'reason_key' => 'media_candidate_expired',
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
            $keys = DB::table('media_upload_parts')
                ->where('organization_id', $organizationId)
                ->where('media_upload_id', $uploadId)
                ->pluck('storage_object_key')
                ->filter(static fn (mixed $key): bool => is_string($key))
                ->values()
                ->all();
            $quarantineKey = $this->nullableString(
                $upload,
                'quarantine_object_key',
            );

            if ($quarantineKey !== null) {
                $keys[] = $quarantineKey;
            }

            foreach (array_values(array_unique($keys)) as $key) {
                DB::table('media_object_deletion_requests')->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationId,
                    'media_upload_id' => $uploadId,
                    'storage_object_key' => $key,
                    'object_hash' => $this->string(
                        $upload,
                        'whole_object_hash',
                    ),
                    'reason_key' => 'media_candidate_expired',
                    'state' => 'pending',
                    'attempt_count' => 0,
                    'last_error_key' => null,
                    'due_at' => $now,
                    'last_attempted_at' => null,
                    'completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return true;
        }, 3);
    }

    /** @return array{deleted: int, retried: int, dead_lettered: int, unavailable: int} */
    private function processDueDeletions(int $limit): array
    {
        $now = CarbonImmutable::now();
        $requestIds = DB::table('media_object_deletion_requests')
            ->whereIn('state', ['pending', 'retrying'])
            ->where('attempt_count', '<', self::MAX_DELETE_ATTEMPTS)
            ->where('due_at', '<=', $now)
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->filter(static fn (mixed $id): bool => is_string($id))
            ->values()
            ->all();
        $result = [
            'deleted' => 0,
            'retried' => 0,
            'dead_lettered' => 0,
            'unavailable' => 0,
        ];

        foreach ($requestIds as $requestId) {
            $outcome = $this->processDeletion($requestId);

            switch ($outcome) {
                case 'deleted':
                    $result['deleted']++;
                    break;
                case 'retried':
                    $result['retried']++;
                    break;
                case 'dead_lettered':
                    $result['dead_lettered']++;
                    break;
                default:
                    $result['unavailable']++;
                    break;
            }
        }

        return $result;
    }

    /** @return 'deleted'|'retried'|'dead_lettered'|'unavailable' */
    private function processDeletion(string $requestId): string
    {
        $claim = $this->claimDeletion($requestId);

        if (! $claim instanceof stdClass) {
            return 'unavailable';
        }

        $deleted = false;

        try {
            $this->objects->deleteQuarantine(
                $this->string($claim, 'storage_object_key'),
            );
            $deleted = true;
        } catch (Throwable) {
            // Failure details and storage keys are intentionally not logged.
        }

        return DB::transaction(function () use ($claim, $deleted): string {
            $now = CarbonImmutable::now();
            $attempt = $this->integer($claim, 'attempt_count');
            $query = DB::table('media_object_deletion_requests')
                ->where('id', $this->string($claim, 'id'))
                ->where('state', 'retrying')
                ->where('attempt_count', $attempt);

            if ($deleted) {
                $updated = $query->update([
                    'state' => 'completed',
                    'last_error_key' => null,
                    'completed_at' => $now,
                    'updated_at' => $now,
                ]);

                return $updated === 1 ? 'deleted' : 'unavailable';
            }

            $deadLettered = $attempt >= self::MAX_DELETE_ATTEMPTS;
            $updated = $query->update([
                'state' => $deadLettered ? 'dead_letter' : 'pending',
                'last_error_key' => 'media_quarantine_delete_failed',
                'due_at' => $now->addMinutes(self::DELETE_RETRY_MINUTES),
                'completed_at' => null,
                'updated_at' => $now,
            ]);

            if ($updated !== 1) {
                return 'unavailable';
            }

            return $deadLettered ? 'dead_lettered' : 'retried';
        }, 3);
    }

    private function claimDeletion(string $requestId): ?stdClass
    {
        return DB::transaction(function () use ($requestId): ?stdClass {
            $now = CarbonImmutable::now();
            $request = DB::table('media_object_deletion_requests')
                ->where('id', $requestId)
                ->whereIn('state', ['pending', 'retrying'])
                ->where('attempt_count', '<', self::MAX_DELETE_ATTEMPTS)
                ->where('due_at', '<=', $now)
                ->lockForUpdate()
                ->first(['id', 'storage_object_key', 'attempt_count']);

            if (! $request instanceof stdClass) {
                return null;
            }

            $attempt = $this->integer($request, 'attempt_count') + 1;
            $leasedUntil = $now->addMinutes(self::DELETE_RETRY_MINUTES);
            $updated = DB::table('media_object_deletion_requests')
                ->where('id', $requestId)
                ->where('attempt_count', $attempt - 1)
                ->update([
                    'state' => 'retrying',
                    'attempt_count' => $attempt,
                    'last_error_key' => null,
                    'due_at' => $leasedUntil,
                    'last_attempted_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                return null;
            }

            $request->attempt_count = $attempt;

            return $request;
        }, 3);
    }

    private function isDue(stdClass $upload, CarbonInterface $now): bool
    {
        $state = $this->string($upload, 'state');

        if ($state === 'uploading') {
            return $this->date($upload, 'expires_at')->lessThanOrEqualTo($now);
        }

        if ($state === 'scan_clean') {
            $reviewExpiry = $this->nullableDate($upload, 'review_expires_at');

            return $reviewExpiry instanceof CarbonInterface
                && $reviewExpiry->lessThanOrEqualTo($now);
        }

        return in_array(
            $state,
            ['inspection_failed', 'scan_failed', 'promotion_failed'],
            true,
        ) && $this->date($upload, 'expires_at')->lessThanOrEqualTo($now);
    }

    private function positiveConfig(string $key, int $fallback): int
    {
        $value = config($key);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new \LogicException('Invalid media maintenance record.');
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        return is_string($value) && $value !== ''
            ? $value
            : throw new \LogicException('Invalid media maintenance record.');
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \LogicException('Invalid media maintenance record.');
    }

    private function date(stdClass $row, string $field): CarbonImmutable
    {
        $value = $row->{$field} ?? null;

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value);
        }

        throw new \LogicException('Invalid media maintenance record.');
    }

    private function nullableDate(
        stdClass $row,
        string $field,
    ): ?CarbonImmutable {
        if (($row->{$field} ?? null) === null) {
            return null;
        }

        return $this->date($row, $field);
    }
}
