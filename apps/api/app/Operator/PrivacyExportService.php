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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;

/**
 * Bounded org-scoped PII export: identifier digests only — never audio/transcripts.
 */
final readonly class PrivacyExportService
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
        $this->authorization->assertCanRequestExport($actor);
        $this->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $targetMembershipId,
            $idempotencyKey,
        ): array {
            $context = $this->contexts->current();
            $organizationId = $context->organizationId;
            $actorMembershipId = $context->membershipId;

            if ($actorMembershipId === null) {
                throw new OperatorPrivacyRejected;
            }

            $existing = DB::table('privacy_export_requests')
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

            $membership = $this->ownedMembership(
                $organizationId,
                $targetMembershipId,
            );
            $summary = $this->buildSummary($organizationId, $membership);
            $id = (string) Str::ulid();
            $now = now();

            DB::table('privacy_export_requests')->insert([
                'id' => $id,
                'organization_id' => $organizationId,
                'target_membership_id' => $targetMembershipId,
                'requested_by_membership_id' => $actorMembershipId,
                'idempotency_key' => $idempotencyKey,
                'status' => 'completed',
                'summary_json' => json_encode($summary, JSON_THROW_ON_ERROR),
                'requested_at' => $now,
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->recordTenant(
                'privacy.export.requested',
                'privacy_export_request',
                $id,
            );

            $row = DB::table('privacy_export_requests')
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
    public function show(User $actor, string $exportRequestId): array
    {
        $this->authorization->assertCanRequestExport($actor);
        $organizationId = $this->contexts->current()->organizationId;

        $row = DB::table('privacy_export_requests')
            ->where('organization_id', $organizationId)
            ->where('id', $exportRequestId)
            ->first();

        if (! $row instanceof stdClass) {
            throw new OperatorPrivacyRejected;
        }

        return $this->present($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(
        string $organizationId,
        stdClass $membership,
    ): array {
        $membershipId = (string) $membership->id;

        return [
            'membership_id' => $membershipId,
            'membership_type' => (string) $membership->membership_type,
            'membership_status' => (string) $membership->status,
            'consent_event_ids' => $this->ids(
                'voice_consent_events',
                $organizationId,
                'learner_membership_id',
                $membershipId,
            ),
            'voice_recording_ids' => $this->ids(
                'voice_recordings',
                $organizationId,
                'learner_membership_id',
                $membershipId,
            ),
            'voice_deletion_receipt_ids' => $this->ids(
                'voice_deletion_requests',
                $organizationId,
                'learner_membership_id',
                $membershipId,
            ),
            'conversation_session_ids' => $this->ids(
                'conversation_sessions',
                $organizationId,
                'learner_membership_id',
                $membershipId,
            ),
            'learner_attempt_ids' => $this->attemptIds(
                $organizationId,
                $membershipId,
            ),
            'content_note' => 'identifier_digests_only_no_audio_or_transcripts',
        ];
    }

    /**
     * @return list<string>
     */
    private function ids(
        string $table,
        string $organizationId,
        string $membershipColumn,
        string $membershipId,
    ): array {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->where('organization_id', $organizationId)
            ->where($membershipColumn, $membershipId)
            ->orderBy('id')
            ->limit(500)
            ->pluck('id')
            ->filter(static fn ($id): bool => is_string($id))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function attemptIds(
        string $organizationId,
        string $membershipId,
    ): array {
        $enrollmentIds = DB::table('enrollments')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->pluck('id')
            ->filter(static fn ($id): bool => is_string($id))
            ->values()
            ->all();

        if ($enrollmentIds === []) {
            return [];
        }

        return DB::table('learner_attempts')
            ->where('organization_id', $organizationId)
            ->whereIn('enrollment_id', $enrollmentIds)
            ->orderBy('id')
            ->limit(500)
            ->pluck('id')
            ->filter(static fn ($id): bool => is_string($id))
            ->values()
            ->all();
    }

    private function ownedMembership(
        string $organizationId,
        string $membershipId,
    ): stdClass {
        $row = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->where('membership_type', Membership::TYPE_LEARNER)
            ->lockForUpdate()
            ->first([
                'id',
                'membership_type',
                'status',
            ]);

        if (! $row instanceof stdClass) {
            throw new OperatorPrivacyRejected;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(stdClass $row): array
    {
        $summary = $row->summary_json;

        if (is_string($summary)) {
            $decoded = json_decode($summary, true);
            $summary = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($summary)) {
            $summary = [];
        }

        return [
            'export_request_id' => (string) $row->id,
            'target_membership_id' => (string) $row->target_membership_id,
            'status' => (string) $row->status,
            'summary' => $summary,
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
