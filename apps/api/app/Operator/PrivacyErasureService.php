<?php

declare(strict_types=1);

namespace App\Operator;

use App\Audit\AuditEventRecorder;
use App\Models\Membership;
use App\Models\User;
use App\Operator\Exceptions\OperatorPrivacyIdempotencyConflict;
use App\Operator\Exceptions\OperatorPrivacyRejected;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

/**
 * Org-scoped account-erasure workflow aligned with ADR-0007 status machine.
 */
final readonly class PrivacyErasureService
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OperatorPrivacyAuthorization $authorization,
        private AuditEventRecorder $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function create(
        User $actor,
        string $targetMembershipId,
        string $idempotencyKey,
    ): array {
        $this->authorization->assertCanRequestErasure($actor);
        $this->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $targetMembershipId,
            $idempotencyKey,
        ): array {
            $context = $this->contexts->current();
            $organizationId = $context->organizationId;
            $actorMembershipId = $context->membershipId;

            if ($actorMembershipId === null) {
                throw new OperatorPrivacyRejected;
            }

            $existing = DB::table('privacy_erasure_requests')
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof stdClass) {
                if (
                    (string) $existing->target_membership_id !== $targetMembershipId
                    || (string) $existing->requested_by_membership_id !== $actorMembershipId
                ) {
                    throw new OperatorPrivacyIdempotencyConflict;
                }

                return $this->present($existing);
            }

            $this->ownedLearnerMembership($organizationId, $targetMembershipId);

            $recordings = DB::table('voice_recordings')
                ->where('organization_id', $organizationId)
                ->where('learner_membership_id', $targetMembershipId)
                ->whereNotIn('status', [
                    'discarded',
                    'expired_deleted',
                    'rejected_deleted',
                    'withdrawn_deleted',
                ])
                ->lockForUpdate()
                ->get();

            $completedDeletions = 0;
            $now = now();

            foreach ($recordings as $recording) {
                if (! $recording instanceof stdClass) {
                    continue;
                }

                if ($this->eraseRecording($recording, $now)) {
                    $completedDeletions++;
                }
            }

            $recordingCount = $recordings->count();
            $status = $this->resolveStatus($recordingCount, $completedDeletions);
            $id = (string) Str::ulid();

            DB::table('privacy_erasure_requests')->insert([
                'id' => $id,
                'organization_id' => $organizationId,
                'target_membership_id' => $targetMembershipId,
                'requested_by_membership_id' => $actorMembershipId,
                'idempotency_key' => $idempotencyKey,
                'status' => $status,
                'voice_recording_count' => $recordingCount,
                'voice_deletion_completed_count' => $completedDeletions,
                'requested_at' => $now,
                'completed_at' => $status === 'pending' ? null : $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->recordTenant(
                'privacy.erasure.requested',
                'privacy_erasure_request',
                $id,
            );

            $row = DB::table('privacy_erasure_requests')
                ->where('organization_id', $organizationId)
                ->where('id', $id)
                ->first();

            if (! $row instanceof stdClass) {
                throw new OperatorPrivacyRejected;
            }

            return $this->present($row);
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $actor, string $erasureRequestId): array
    {
        $this->authorization->assertCanRequestErasure($actor);
        $organizationId = $this->contexts->current()->organizationId;

        $row = DB::table('privacy_erasure_requests')
            ->where('organization_id', $organizationId)
            ->where('id', $erasureRequestId)
            ->first();

        if (! $row instanceof stdClass) {
            throw new OperatorPrivacyRejected;
        }

        return $this->present($row);
    }

    private function eraseRecording(stdClass $row, mixed $now): bool
    {
        $recordingId = (string) $row->id;
        $organizationId = (string) $row->organization_id;
        $membershipId = (string) $row->learner_membership_id;
        $sourceDeleted = $this->deleteSource($row);

        DB::table('voice_recordings')
            ->where('organization_id', $organizationId)
            ->where('id', $recordingId)
            ->update([
                'status' => 'withdrawn_deleted',
                'source_object_key' => null,
                'source_deleted_at' => $sourceDeleted
                    ? ($row->source_deleted_at ?? $now)
                    : $row->source_deleted_at,
                'transcript_ciphertext' => null,
                'transcript_expires_at' => $now,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);

        $existing = DB::table('voice_deletion_requests')
            ->where('organization_id', $organizationId)
            ->where('voice_recording_id', $recordingId)
            ->first();

        $values = [
            'status' => $sourceDeleted ? 'completed' : 'partially_completed',
            'source_deleted' => $sourceDeleted,
            'transcript_deleted' => true,
            'provider_deletion_status' => 'not_required',
            'completed_at' => $sourceDeleted ? $now : null,
        ];

        if ($existing instanceof stdClass) {
            DB::table('voice_deletion_requests')
                ->where('organization_id', $organizationId)
                ->where('id', (string) $existing->id)
                ->update($values);
        } else {
            DB::table('voice_deletion_requests')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'voice_recording_id' => $recordingId,
                'learner_membership_id' => $membershipId,
                'reason' => 'learner_request',
                'requested_at' => $now,
                ...$values,
            ]);
        }

        return $sourceDeleted;
    }

    private function deleteSource(stdClass $row): bool
    {
        $key = $row->source_object_key ?? null;

        if (! is_string($key) || $key === '') {
            return true;
        }

        return Storage::disk((string) config('voice.storage_disk', 'local'))
            ->delete($key);
    }

    private function resolveStatus(int $recordingCount, int $completed): string
    {
        if ($recordingCount === 0) {
            return 'completed';
        }

        if ($completed === $recordingCount) {
            return 'completed';
        }

        if ($completed === 0) {
            return 'partially_completed';
        }

        return 'partially_completed';
    }

    private function ownedLearnerMembership(
        string $organizationId,
        string $membershipId,
    ): void {
        $exists = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->where('membership_type', Membership::TYPE_LEARNER)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            throw new OperatorPrivacyRejected;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(stdClass $row): array
    {
        return [
            'erasure_request_id' => (string) $row->id,
            'target_membership_id' => (string) $row->target_membership_id,
            'status' => (string) $row->status,
            'voice_recording_count' => (int) $row->voice_recording_count,
            'voice_deletion_completed_count' => (int) $row->voice_deletion_completed_count,
            'requested_at' => $this->iso($row->requested_at ?? null),
            'completed_at' => $this->isoNullable($row->completed_at ?? null),
        ];
    }

    private function assertIdempotencyKey(string $key): void
    {
        if (preg_match(
            '/\A(?:[0-9A-HJKMNP-TV-Z]{26}|[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\z/',
            $key,
        ) !== 1) {
            throw new OperatorPrivacyRejected;
        }
    }

    private function iso(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) ? $value : '';
    }

    private function isoNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $formatted = $this->iso($value);

        return $formatted === '' ? null : $formatted;
    }
}
