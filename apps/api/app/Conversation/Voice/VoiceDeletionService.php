<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

use DateInterval;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use stdClass;
use Throwable;

/**
 * Durable, tenant-bound deletion intents and source-object reconciliation.
 *
 * Storage calls deliberately happen after the authoritative intent/outbox
 * transaction commits. Outbox payloads contain no media key or learner data.
 */
final readonly class VoiceDeletionService
{
    private const string OUTBOX_EVENT_TYPE = 'voice.source-deletion.requested';

    private const string OUTBOX_AGGREGATE_TYPE = 'voice_deletion_request';

    /**
     * @var list<string>
     */
    private const array REASONS = [
        'learner_request',
        'withdrawal',
        'expiry',
        'rejection',
    ];

    public function __construct(private VoiceSourceObjectStore $objects) {}

    public function requestAndProcess(
        string $organizationId,
        string $membershipId,
        string $recordingId,
        string $reason,
    ): stdClass {
        $requestId = DB::transaction(function () use (
            $organizationId,
            $membershipId,
            $recordingId,
            $reason,
        ): string {
            $recording = DB::table('voice_recordings')
                ->where('organization_id', $organizationId)
                ->where('learner_membership_id', $membershipId)
                ->where('id', $recordingId)
                ->lockForUpdate()
                ->first();

            if (! $recording instanceof stdClass) {
                throw new VoiceOperationRejected;
            }

            return $this->recordIntent(
                $recording,
                $reason,
                deleteTranscript: true,
                now: Date::now()->toImmutable(),
            );
        }, 3);

        $this->processRequest($requestId);

        $request = DB::table('voice_deletion_requests')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('id', $requestId)
            ->first();

        if (! $request instanceof stdClass) {
            throw new VoiceOperationRejected;
        }

        return $request;
    }

    public function requestExpiry(
        string $recordingId,
        \DateTimeImmutable $now,
    ): ?string {
        return DB::transaction(function () use ($recordingId, $now): ?string {
            $recording = DB::table('voice_recordings')
                ->where('id', $recordingId)
                ->lockForUpdate()
                ->first();

            if (! $recording instanceof stdClass) {
                return null;
            }

            $sourceDue = ! $this->sourceDeletionConfirmed($recording)
                && $this->isDue($recording->source_delete_by ?? null, $now);
            $transcriptDue = $this->hasTranscript($recording)
                && $this->isDue($recording->transcript_expires_at ?? null, $now);

            if (! $sourceDue && ! $transcriptDue) {
                return null;
            }

            return $this->recordIntent(
                $recording,
                'expiry',
                deleteTranscript: $transcriptDue,
                now: $now,
            );
        }, 3);
    }

    /**
     * Record and link one privacy-erasure intent inside the caller's
     * authoritative transaction. The recording row must already be locked.
     */
    public function recordPrivacyIntent(
        stdClass $recording,
        string $privacyErasureRequestId,
        \DateTimeImmutable $now,
    ): string {
        $this->assertTransaction();
        $requestId = $this->recordIntent(
            $recording,
            'learner_request',
            deleteTranscript: true,
            now: $now,
        );

        DB::table('privacy_erasure_voice_deletions')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $this->requiredString(
                $recording,
                'organization_id',
            ),
            'privacy_erasure_request_id' => $privacyErasureRequestId,
            'voice_deletion_request_id' => $requestId,
            'created_at' => $now,
        ]);

        return $requestId;
    }

    /**
     * @return array{attempted: int, succeeded: int, failed: int}
     */
    public function processDue(int $limit = 500): array
    {
        $attempted = 0;
        $succeeded = 0;
        $failed = 0;

        for ($index = 0; $index < max(0, $limit); $index++) {
            $claim = $this->claim();

            if ($claim === null) {
                break;
            }

            $attempted++;

            if ($this->processClaim($claim)) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        return [
            'attempted' => $attempted,
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    public function processRequest(string $requestId): bool
    {
        $claim = $this->claim($requestId);

        return $claim !== null && $this->processClaim($claim);
    }

    public function processForPrivacyErasure(
        string $organizationId,
        string $privacyErasureRequestId,
    ): void {
        $requestIds = DB::table('privacy_erasure_voice_deletions')
            ->where('organization_id', $organizationId)
            ->where('privacy_erasure_request_id', $privacyErasureRequestId)
            ->orderBy('voice_deletion_request_id')
            ->pluck('voice_deletion_request_id');

        foreach ($requestIds as $requestId) {
            if (is_string($requestId)) {
                $this->processRequest($requestId);
            }
        }

        $this->reconcilePrivacyErasure(
            $organizationId,
            $privacyErasureRequestId,
        );
    }

    public function reconcilePrivacyErasure(
        string $organizationId,
        string $privacyErasureRequestId,
    ): void {
        DB::transaction(function () use (
            $organizationId,
            $privacyErasureRequestId,
        ): void {
            $privacy = DB::table('privacy_erasure_requests')
                ->where('organization_id', $organizationId)
                ->where('id', $privacyErasureRequestId)
                ->lockForUpdate()
                ->first();

            if (! $privacy instanceof stdClass) {
                return;
            }

            $statuses = DB::table('privacy_erasure_voice_deletions as links')
                ->join(
                    'voice_deletion_requests as deletions',
                    function ($join): void {
                        $join
                            ->on(
                                'deletions.organization_id',
                                '=',
                                'links.organization_id',
                            )
                            ->on(
                                'deletions.id',
                                '=',
                                'links.voice_deletion_request_id',
                            );
                    },
                )
                ->where('links.organization_id', $organizationId)
                ->where(
                    'links.privacy_erasure_request_id',
                    $privacyErasureRequestId,
                )
                ->pluck('deletions.status');
            $total = $statuses->count();
            $completed = $statuses->filter(
                static fn (mixed $status): bool => $status === 'completed',
            )->count();
            $restricted = $statuses->contains(
                static fn (mixed $status): bool => $status === 'restricted',
            );
            $status = match (true) {
                $restricted => 'restricted',
                $completed === $total => 'completed',
                $statuses->every(
                    static fn (mixed $value): bool => $value === 'pending',
                ) => 'pending',
                default => 'partially_completed',
            };
            $now = Date::now()->toImmutable();

            DB::table('privacy_erasure_requests')
                ->where('organization_id', $organizationId)
                ->where('id', $privacyErasureRequestId)
                ->update([
                    'status' => $status,
                    'voice_recording_count' => $total,
                    'voice_deletion_completed_count' => $completed,
                    'completed_at' => in_array(
                        $status,
                        ['completed', 'restricted'],
                        true,
                    ) ? $now : null,
                    'updated_at' => $now,
                ]);
        }, 3);
    }

    private function recordIntent(
        stdClass $recording,
        string $reason,
        bool $deleteTranscript,
        \DateTimeImmutable $now,
    ): string {
        $this->assertTransaction();

        if (! in_array($reason, self::REASONS, true)) {
            throw new VoiceOperationRejected;
        }

        $organizationId = $this->requiredString($recording, 'organization_id');
        $recordingId = $this->requiredString($recording, 'id');
        $membershipId = $this->requiredString(
            $recording,
            'learner_membership_id',
        );
        $existing = DB::table('voice_deletion_requests')
            ->where('organization_id', $organizationId)
            ->where('voice_recording_id', $recordingId)
            ->lockForUpdate()
            ->first();
        $requestId = $existing instanceof stdClass
            ? $this->requiredString($existing, 'id')
            : (string) Str::ulid();
        $requestReason = $existing instanceof stdClass
            ? $this->requiredString($existing, 'reason')
            : $reason;
        $sourceDeleted = $this->sourceDeletionConfirmed($recording);
        $transcriptDeleted = $deleteTranscript || ! $this->hasTranscript(
            $recording,
        );
        $providerStatus = $existing instanceof stdClass
            ? $this->requiredString($existing, 'provider_deletion_status')
            : 'not_required';
        $status = $this->deletionStatus(
            $sourceDeleted,
            $transcriptDeleted,
            $providerStatus,
        );

        DB::table('voice_recordings')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('id', $recordingId)
            ->update([
                'transcript_ciphertext' => $deleteTranscript
                    ? null
                    : ($recording->transcript_ciphertext ?? null),
                'transcript_expires_at' => $deleteTranscript
                    ? $now
                    : ($recording->transcript_expires_at ?? null),
                'updated_at' => $now,
            ]);

        $values = [
            'status' => $status,
            'source_deleted' => $sourceDeleted,
            'transcript_deleted' => $transcriptDeleted,
            'provider_deletion_status' => $providerStatus,
            'completed_at' => $status === 'completed' ? $now : null,
        ];

        if ($existing instanceof stdClass) {
            DB::table('voice_deletion_requests')
                ->where('organization_id', $organizationId)
                ->where('id', $requestId)
                ->update($values);
        } else {
            DB::table('voice_deletion_requests')->insert([
                'id' => $requestId,
                'organization_id' => $organizationId,
                'voice_recording_id' => $recordingId,
                'learner_membership_id' => $membershipId,
                'reason' => $requestReason,
                'requested_at' => $now,
                ...$values,
            ]);
        }

        if ($status === 'completed') {
            $this->terminalizeRecording(
                $organizationId,
                $recordingId,
                $requestReason,
                $now,
            );
            $this->completeActiveOutbox($organizationId, $requestId, $now);
        } else {
            $this->ensureOutboxWork(
                $organizationId,
                $requestId,
                $sourceDeleted,
                $now,
            );
        }

        return $requestId;
    }

    /**
     * @return array{
     *   outbox_id: string,
     *   organization_id: string,
     *   request_id: string,
     *   recording_id: string,
     *   object_key: ?string,
     *   source_confirmed: bool,
     *   attempt: int
     * }|null
     */
    private function claim(?string $requestId = null): ?array
    {
        return DB::transaction(function () use ($requestId): ?array {
            $now = Date::now()->toImmutable();
            $query = DB::table('outbox_events')
                ->where('event_type', self::OUTBOX_EVENT_TYPE)
                ->where('aggregate_type', self::OUTBOX_AGGREGATE_TYPE)
                ->whereNull('published_at')
                ->whereNull('dead_lettered_at')
                ->where('available_at', '<=', $now);

            if ($requestId !== null) {
                $query->where('aggregate_id', $requestId);
            }

            $query->orderBy('available_at')->orderBy('id');

            if (DB::connection()->getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            $event = $query->first();

            if (! $event instanceof stdClass) {
                return null;
            }

            $attempt = ((int) ($event->attempts ?? 0)) + 1;
            $leaseUntil = $now->addSeconds(max(
                30,
                (int) config('voice.deletion_lease_seconds', 300),
            ));
            DB::table('outbox_events')
                ->where('id', $event->id)
                ->whereNull('published_at')
                ->whereNull('dead_lettered_at')
                ->update([
                    'attempts' => $attempt,
                    'available_at' => $leaseUntil,
                    'last_error_code' => null,
                ]);
            $organizationId = $this->requiredString(
                $event,
                'organization_id',
            );
            $deletionRequestId = $this->requiredString($event, 'aggregate_id');
            $deletion = DB::table('voice_deletion_requests')
                ->where('organization_id', $organizationId)
                ->where('id', $deletionRequestId)
                ->first();

            if (! $deletion instanceof stdClass) {
                $this->deferClaim(
                    $this->requiredString($event, 'id'),
                    $attempt,
                    'voice_deletion_evidence_missing',
                    $now,
                );

                return null;
            }

            $recordingId = $this->requiredString(
                $deletion,
                'voice_recording_id',
            );
            $recording = DB::table('voice_recordings')
                ->where('organization_id', $organizationId)
                ->where('learner_membership_id', $deletion->learner_membership_id)
                ->where('id', $recordingId)
                ->first();

            if (! $recording instanceof stdClass) {
                $this->deferClaim(
                    $this->requiredString($event, 'id'),
                    $attempt,
                    'voice_recording_evidence_missing',
                    $now,
                );

                return null;
            }

            $key = $recording->source_object_key ?? null;

            return [
                'outbox_id' => $this->requiredString($event, 'id'),
                'organization_id' => $organizationId,
                'request_id' => $deletionRequestId,
                'recording_id' => $recordingId,
                'object_key' => is_string($key) && $key !== '' ? $key : null,
                'source_confirmed' => $this->sourceDeletionConfirmed($recording),
                'attempt' => $attempt,
            ];
        }, 3);
    }

    /**
     * @param  array{
     *   outbox_id: string,
     *   organization_id: string,
     *   request_id: string,
     *   recording_id: string,
     *   object_key: ?string,
     *   source_confirmed: bool,
     *   attempt: int
     * }  $claim
     */
    private function processClaim(array $claim): bool
    {
        if ($claim['source_confirmed']) {
            $succeeded = $this->finalizeSuccess($claim);
            $this->reconcileLinkedPrivacy(
                $claim['organization_id'],
                $claim['request_id'],
            );

            return $succeeded;
        }

        if ($claim['object_key'] === null) {
            $this->failClaim($claim, 'voice_source_object_key_missing');

            return false;
        }

        try {
            $deleted = $this->objects->delete($claim['object_key']);
        } catch (Throwable) {
            $deleted = false;
        }

        if (! $deleted) {
            $this->failClaim($claim, 'voice_source_delete_failed');

            return false;
        }

        $succeeded = $this->finalizeSuccess($claim);
        $this->reconcileLinkedPrivacy(
            $claim['organization_id'],
            $claim['request_id'],
        );

        return $succeeded;
    }

    /**
     * @param  array{
     *   outbox_id: string,
     *   organization_id: string,
     *   request_id: string,
     *   recording_id: string,
     *   object_key: ?string,
     *   source_confirmed: bool,
     *   attempt: int
     * }  $claim
     */
    private function finalizeSuccess(array $claim): bool
    {
        return DB::transaction(function () use ($claim): bool {
            $event = DB::table('outbox_events')
                ->where('organization_id', $claim['organization_id'])
                ->where('id', $claim['outbox_id'])
                ->where('attempts', $claim['attempt'])
                ->whereNull('dead_lettered_at')
                ->lockForUpdate()
                ->first();

            if (! $event instanceof stdClass) {
                return false;
            }

            if ($event->published_at !== null) {
                return true;
            }

            $deletion = DB::table('voice_deletion_requests')
                ->where('organization_id', $claim['organization_id'])
                ->where('id', $claim['request_id'])
                ->lockForUpdate()
                ->first();
            $recording = DB::table('voice_recordings')
                ->where('organization_id', $claim['organization_id'])
                ->where('id', $claim['recording_id'])
                ->lockForUpdate()
                ->first();

            if (! $deletion instanceof stdClass || ! $recording instanceof stdClass) {
                return false;
            }

            $currentKey = $recording->source_object_key ?? null;
            $alreadyConfirmed = $this->sourceDeletionConfirmed($recording);

            if (
                ! $alreadyConfirmed
                && (
                    ! is_string($currentKey)
                    || $currentKey === ''
                    || $claim['object_key'] === null
                    || ! hash_equals($currentKey, $claim['object_key'])
                )
            ) {
                $this->deferClaim(
                    $claim['outbox_id'],
                    $claim['attempt'],
                    'voice_source_object_changed',
                    Date::now()->toImmutable(),
                );

                return false;
            }

            $now = Date::now()->toImmutable();
            $providerStatus = $this->requiredString(
                $deletion,
                'provider_deletion_status',
            );
            $status = $this->deletionStatus(
                true,
                (bool) $deletion->transcript_deleted,
                $providerStatus,
            );
            DB::table('voice_recordings')
                ->where('organization_id', $claim['organization_id'])
                ->where('id', $claim['recording_id'])
                ->update([
                    'source_object_key' => null,
                    'source_deleted_at' => $recording->source_deleted_at ?? $now,
                    'status' => $status === 'completed'
                        ? $this->terminalStatus(
                            $this->requiredString($deletion, 'reason'),
                        )
                        : $recording->status,
                    'completed_at' => $status === 'completed'
                        ? $now
                        : $recording->completed_at,
                    'updated_at' => $now,
                ]);
            DB::table('voice_deletion_requests')
                ->where('organization_id', $claim['organization_id'])
                ->where('id', $claim['request_id'])
                ->update([
                    'status' => $status,
                    'source_deleted' => true,
                    'completed_at' => $status === 'completed' ? $now : null,
                ]);
            DB::table('outbox_events')
                ->where('organization_id', $claim['organization_id'])
                ->where('id', $claim['outbox_id'])
                ->where('attempts', $claim['attempt'])
                ->whereNull('dead_lettered_at')
                ->update([
                    'published_at' => $now,
                    'available_at' => $now,
                    'last_error_code' => null,
                    'dead_lettered_at' => null,
                    'dead_letter_reason_code' => null,
                ]);

            return true;
        }, 3);
    }

    /**
     * @param  array{
     *   outbox_id: string,
     *   organization_id: string,
     *   request_id: string,
     *   recording_id: string,
     *   object_key: ?string,
     *   source_confirmed: bool,
     *   attempt: int
     * }  $claim
     */
    private function failClaim(array $claim, string $safeErrorCode): void
    {
        DB::transaction(function () use ($claim, $safeErrorCode): void {
            $event = DB::table('outbox_events')
                ->where('organization_id', $claim['organization_id'])
                ->where('id', $claim['outbox_id'])
                ->where('attempts', $claim['attempt'])
                ->whereNull('published_at')
                ->whereNull('dead_lettered_at')
                ->lockForUpdate()
                ->first();

            if (! $event instanceof stdClass) {
                return;
            }

            $now = Date::now()->toImmutable();
            $this->deferClaim(
                $claim['outbox_id'],
                $claim['attempt'],
                $safeErrorCode,
                $now,
            );
            $deletion = DB::table('voice_deletion_requests')
                ->where('organization_id', $claim['organization_id'])
                ->where('id', $claim['request_id'])
                ->lockForUpdate()
                ->first();

            if ($deletion instanceof stdClass) {
                DB::table('voice_deletion_requests')
                    ->where('organization_id', $claim['organization_id'])
                    ->where('id', $claim['request_id'])
                    ->update([
                        'status' => $this->deletionStatus(
                            false,
                            (bool) $deletion->transcript_deleted,
                            $this->requiredString(
                                $deletion,
                                'provider_deletion_status',
                            ),
                        ),
                        'source_deleted' => false,
                        'completed_at' => null,
                    ]);
            }
        }, 3);

        $this->reconcileLinkedPrivacy(
            $claim['organization_id'],
            $claim['request_id'],
        );
    }

    private function ensureOutboxWork(
        string $organizationId,
        string $requestId,
        bool $sourceDeleted,
        \DateTimeImmutable $now,
    ): void {
        if ($sourceDeleted) {
            $this->completeActiveOutbox($organizationId, $requestId, $now);

            return;
        }

        $exists = DB::table('outbox_events')
            ->where('organization_id', $organizationId)
            ->where('event_type', self::OUTBOX_EVENT_TYPE)
            ->where('aggregate_type', self::OUTBOX_AGGREGATE_TYPE)
            ->where('aggregate_id', $requestId)
            ->whereNull('published_at')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('outbox_events')->insert([
            'id' => (string) Str::ulid(),
            'scope_kind' => 'organization',
            'organization_id' => $organizationId,
            'event_type' => self::OUTBOX_EVENT_TYPE,
            'aggregate_type' => self::OUTBOX_AGGREGATE_TYPE,
            'aggregate_id' => $requestId,
            'payload' => json_encode(new stdClass, JSON_THROW_ON_ERROR),
            'correlation_id' => null,
            'occurred_at' => $now,
            'available_at' => $now,
            'published_at' => null,
            'attempts' => 0,
            'last_error_code' => null,
            'dead_lettered_at' => null,
            'dead_letter_reason_code' => null,
        ]);
    }

    private function completeActiveOutbox(
        string $organizationId,
        string $requestId,
        \DateTimeImmutable $now,
    ): void {
        DB::table('outbox_events')
            ->where('organization_id', $organizationId)
            ->where('event_type', self::OUTBOX_EVENT_TYPE)
            ->where('aggregate_type', self::OUTBOX_AGGREGATE_TYPE)
            ->where('aggregate_id', $requestId)
            ->whereNull('published_at')
            ->update([
                'published_at' => $now,
                'available_at' => $now,
                'last_error_code' => null,
                'dead_lettered_at' => null,
                'dead_letter_reason_code' => null,
            ]);
    }

    private function deferClaim(
        string $outboxId,
        int $attempt,
        string $safeErrorCode,
        \DateTimeImmutable $now,
    ): void {
        $maximumAttempts = max(
            1,
            (int) config('voice.deletion_max_attempts', 5),
        );

        if ($attempt >= $maximumAttempts) {
            DB::table('outbox_events')
                ->where('id', $outboxId)
                ->where('attempts', $attempt)
                ->whereNull('published_at')
                ->whereNull('dead_lettered_at')
                ->update([
                    'available_at' => $now,
                    'last_error_code' => $safeErrorCode,
                    'dead_lettered_at' => $now,
                    'dead_letter_reason_code' => $safeErrorCode,
                ]);

            return;
        }

        $baseSeconds = max(
            1,
            (int) config('voice.deletion_retry_base_seconds', 60),
        );
        $maximumSeconds = max(
            $baseSeconds,
            (int) config('voice.deletion_retry_max_seconds', 3600),
        );
        $multiplier = 2 ** min(max(0, $attempt - 1), 10);
        $delay = min($maximumSeconds, $baseSeconds * $multiplier);

        DB::table('outbox_events')
            ->where('id', $outboxId)
            ->where('attempts', $attempt)
            ->whereNull('published_at')
            ->whereNull('dead_lettered_at')
            ->update([
                'available_at' => $now->add(new DateInterval(sprintf(
                    'PT%dS',
                    $delay,
                ))),
                'last_error_code' => $safeErrorCode,
            ]);
    }

    private function terminalizeRecording(
        string $organizationId,
        string $recordingId,
        string $reason,
        \DateTimeImmutable $now,
    ): void {
        DB::table('voice_recordings')
            ->where('organization_id', $organizationId)
            ->where('id', $recordingId)
            ->update([
                'status' => $this->terminalStatus($reason),
                'source_object_key' => null,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function terminalStatus(string $reason): string
    {
        return match ($reason) {
            'expiry' => 'expired_deleted',
            'rejection' => 'rejected_deleted',
            default => 'withdrawn_deleted',
        };
    }

    private function deletionStatus(
        bool $sourceDeleted,
        bool $transcriptDeleted,
        string $providerStatus,
    ): string {
        $providerDeleted = in_array(
            $providerStatus,
            ['not_required', 'confirmed'],
            true,
        );

        if ($sourceDeleted && $transcriptDeleted && $providerDeleted) {
            return 'completed';
        }

        if ($sourceDeleted || $transcriptDeleted || $providerDeleted) {
            return 'partially_completed';
        }

        return 'pending';
    }

    private function sourceDeletionConfirmed(stdClass $recording): bool
    {
        $key = $recording->source_object_key ?? null;

        return $recording->source_deleted_at !== null
            && (! is_string($key) || $key === '');
    }

    private function hasTranscript(stdClass $recording): bool
    {
        $ciphertext = $recording->transcript_ciphertext ?? null;

        return is_string($ciphertext) && $ciphertext !== '';
    }

    private function isDue(mixed $value, \DateTimeImmutable $now): bool
    {
        return (is_string($value) || $value instanceof \DateTimeInterface)
            && Date::parse($value)->lessThanOrEqualTo($now);
    }

    private function reconcileLinkedPrivacy(
        string $organizationId,
        string $requestId,
    ): void {
        $privacyIds = DB::table('privacy_erasure_voice_deletions')
            ->where('organization_id', $organizationId)
            ->where('voice_deletion_request_id', $requestId)
            ->pluck('privacy_erasure_request_id');

        foreach ($privacyIds as $privacyId) {
            if (is_string($privacyId)) {
                $this->reconcilePrivacyErasure($organizationId, $privacyId);
            }
        }
    }

    private function requiredString(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new LogicException('Voice deletion evidence is invalid.');
        }

        return $value;
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Voice deletion intent must be recorded in a transaction.',
            );
        }
    }
}
