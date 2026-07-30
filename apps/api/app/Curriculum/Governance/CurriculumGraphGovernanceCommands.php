<?php

declare(strict_types=1);

namespace App\Curriculum\Governance;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class CurriculumGraphGovernanceCommands
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private AuthorityEvidenceGate $authority,
        private GovernanceCommandIdempotency $idempotency,
        private CurriculumGraphProjector $projector,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function snapshot(
        User $actor,
        string $curriculumVersionId,
        int $expectedRevisionNumber,
        string $expectedGraphHash,
        string $idempotencyKey,
        string $authorAttribution,
    ): string {
        GovernanceInput::ulid($curriculumVersionId);
        GovernanceInput::sha256($expectedGraphHash);
        GovernanceInput::idempotencyKey($idempotencyKey);
        $authorAttribution = GovernanceInput::text($authorAttribution, 120);

        if ($expectedRevisionNumber < 0) {
            throw new CurriculumOperationRejected;
        }

        return DB::transaction(function () use (
            $actor,
            $curriculumVersionId,
            $expectedRevisionNumber,
            $expectedGraphHash,
            $idempotencyKey,
            $authorAttribution,
        ): string {
            $actorMembershipId = $this->assertActorPermission(
                $actor,
                'curriculum.author',
            );
            $context = $this->contexts->current();
            $version = DB::table('curriculum_versions')
                ->where('organization_id', $context->organizationId)
                ->where('id', $curriculumVersionId)
                ->lockForUpdate()
                ->first(['program_id']);

            if (! $version instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $fingerprint = CanonicalJson::sha256([
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => (string) $actor->getKey(),
                'author_attribution' => $authorAttribution,
                'curriculum_version_id' => $curriculumVersionId,
                'expected_revision_number' => $expectedRevisionNumber,
                'expected_graph_hash' => $expectedGraphHash,
                'operation' => 'curriculum.graph.snapshot',
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: 'curriculum.graph.snapshot',
                aggregateType: 'curriculum',
                aggregateId: $curriculumVersionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            $projected = $this->projector->project(
                $context->organizationId,
                $curriculumVersionId,
            );

            if (
                ! hash_equals(
                    $expectedGraphHash,
                    $projected['graph_hash'],
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $head = DB::table('curriculum_graph_heads')
                ->join('curriculum_graph_revisions', function ($join): void {
                    $join
                        ->on(
                            'curriculum_graph_revisions.organization_id',
                            '=',
                            'curriculum_graph_heads.organization_id',
                        )
                        ->on(
                            'curriculum_graph_revisions.curriculum_version_id',
                            '=',
                            'curriculum_graph_heads.curriculum_version_id',
                        )
                        ->on(
                            'curriculum_graph_revisions.id',
                            '=',
                            'curriculum_graph_heads.curriculum_graph_revision_id',
                        );
                })
                ->where(
                    'curriculum_graph_heads.organization_id',
                    $context->organizationId,
                )
                ->where(
                    'curriculum_graph_heads.curriculum_version_id',
                    $curriculumVersionId,
                )
                ->lockForUpdate()
                ->first([
                    'curriculum_graph_revisions.id',
                    'curriculum_graph_revisions.revision_number',
                    'curriculum_graph_revisions.graph_hash',
                ]);
            $currentRevisionNumber = $head instanceof stdClass
                ? $this->integer($head, 'revision_number')
                : 0;

            if (
                $currentRevisionNumber !== $expectedRevisionNumber
                || (
                    $head instanceof stdClass
                    && hash_equals(
                        $this->string($head, 'graph_hash'),
                        $projected['graph_hash'],
                    )
                )
                || DB::table('curriculum_publications')
                    ->where('organization_id', $context->organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->exists()
            ) {
                throw new CurriculumOperationRejected;
            }

            $revisionId = (string) Str::ulid();
            $now = Date::now();
            $parentRevisionId = $head instanceof stdClass
                ? $this->string($head, 'id')
                : null;
            $revisionNumber = $currentRevisionNumber + 1;

            DB::table('curriculum_graph_revisions')->insert([
                'id' => $revisionId,
                'organization_id' => $context->organizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'parent_revision_id' => $parentRevisionId,
                'parent_revision_number' => $parentRevisionId === null
                    ? null
                    : $currentRevisionNumber,
                'revision_number' => $revisionNumber,
                'graph' => CanonicalJson::encode($projected['graph']),
                'graph_hash' => $projected['graph_hash'],
                'authored_by_membership_id' => $actorMembershipId,
                'author_attribution' => $authorAttribution,
                'authored_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($head instanceof stdClass) {
                DB::table('curriculum_graph_heads')
                    ->where('organization_id', $context->organizationId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->update([
                        'curriculum_graph_revision_id' => $revisionId,
                        'graph_hash' => $projected['graph_hash'],
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('curriculum_graph_heads')->insert([
                    'organization_id' => $context->organizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'curriculum_graph_revision_id' => $revisionId,
                    'graph_hash' => $projected['graph_hash'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->recordCommand(
                actor: $actor,
                actorMembershipId: $actorMembershipId,
                operation: 'curriculum.graph.snapshot',
                aggregateId: $curriculumVersionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'curriculum_graph_revision',
                resultId: $revisionId,
            );
            $this->recordEvidence(
                action: 'curriculum.graph.snapshot_recorded',
                aggregateType: 'curriculum_graph_revision',
                aggregateId: $revisionId,
                curriculumVersionId: $curriculumVersionId,
                membershipId: $actorMembershipId,
            );

            return $revisionId;
        }, 3);
    }

    public function review(
        User $actor,
        string $curriculumGraphRevisionId,
        string $graphHash,
        string $authorityAppointmentId,
        string $decision,
        string $idempotencyKey,
        ?string $rationaleKey = null,
    ): string {
        GovernanceInput::ulid($curriculumGraphRevisionId);
        GovernanceInput::sha256($graphHash);
        GovernanceInput::ulid($authorityAppointmentId);
        GovernanceInput::idempotencyKey($idempotencyKey);
        $rationaleKey = $rationaleKey === null
            ? null
            : GovernanceInput::key($rationaleKey);

        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new CurriculumOperationRejected;
        }

        return DB::transaction(function () use (
            $actor,
            $curriculumGraphRevisionId,
            $graphHash,
            $authorityAppointmentId,
            $decision,
            $idempotencyKey,
            $rationaleKey,
        ): string {
            $actorMembershipId = $this->assertActorPermission(
                $actor,
                'curriculum.review.en',
            );
            $context = $this->contexts->current();
            $revision = DB::table('curriculum_graph_revisions')
                ->join('curriculum_versions', function ($join): void {
                    $join
                        ->on(
                            'curriculum_versions.organization_id',
                            '=',
                            'curriculum_graph_revisions.organization_id',
                        )
                        ->on(
                            'curriculum_versions.id',
                            '=',
                            'curriculum_graph_revisions.curriculum_version_id',
                        );
                })
                ->join('curriculum_graph_heads', function ($join): void {
                    $join
                        ->on(
                            'curriculum_graph_heads.organization_id',
                            '=',
                            'curriculum_graph_revisions.organization_id',
                        )
                        ->on(
                            'curriculum_graph_heads.curriculum_version_id',
                            '=',
                            'curriculum_graph_revisions.curriculum_version_id',
                        )
                        ->on(
                            'curriculum_graph_heads.curriculum_graph_revision_id',
                            '=',
                            'curriculum_graph_revisions.id',
                        );
                })
                ->where(
                    'curriculum_graph_revisions.organization_id',
                    $context->organizationId,
                )
                ->where(
                    'curriculum_graph_revisions.id',
                    $curriculumGraphRevisionId,
                )
                ->where('curriculum_graph_revisions.graph_hash', $graphHash)
                ->lockForUpdate()
                ->first([
                    'curriculum_graph_revisions.curriculum_version_id',
                    'curriculum_graph_revisions.authored_by_membership_id',
                    'curriculum_versions.program_id',
                ]);

            if (! $revision instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $curriculumVersionId = $this->string(
                $revision,
                'curriculum_version_id',
            );
            $programId = $this->string($revision, 'program_id');
            $now = Date::now();
            $appointment = $this->authority->acceptedForNewEvidence(
                actor: $actor,
                appointmentId: $authorityAppointmentId,
                authorityKind: AuthorityKind::EnglishAcademicReviewer,
                programId: $programId,
                at: $now,
            );
            $authorMembershipId = $this->nullableString(
                $revision,
                'authored_by_membership_id',
            );

            if (
                $authorMembershipId !== null
                && $this->sameHuman(
                    $context->organizationId,
                    $authorMembershipId,
                    $actorMembershipId,
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $fingerprint = CanonicalJson::sha256([
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => (string) $actor->getKey(),
                'authority_appointment_id' => $authorityAppointmentId,
                'curriculum_graph_revision_id' => $curriculumGraphRevisionId,
                'decision' => $decision,
                'graph_hash' => $graphHash,
                'operation' => 'curriculum.graph.review',
                'rationale_key' => $rationaleKey,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: 'curriculum.graph.review',
                aggregateType: 'curriculum',
                aggregateId: $curriculumGraphRevisionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            $reviewId = (string) Str::ulid();
            DB::table('curriculum_graph_reviews')->insert([
                'id' => $reviewId,
                'organization_id' => $context->organizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'curriculum_graph_revision_id' => $curriculumGraphRevisionId,
                'graph_hash' => $graphHash,
                'authority_kind' => AuthorityKind::EnglishAcademicReviewer->value,
                'authority_appointment_id' => $appointment->appointmentId,
                'authority_appointment_event_id' => $appointment->acceptanceEventId,
                'authority_appointment_event_type' => 'accepted',
                'reviewer_membership_id' => $appointment->appointeeMembershipId,
                'decision' => $decision,
                'rationale_key' => $rationaleKey,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->recordCommand(
                actor: $actor,
                actorMembershipId: $actorMembershipId,
                operation: 'curriculum.graph.review',
                aggregateId: $curriculumGraphRevisionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'curriculum_graph_review',
                resultId: $reviewId,
            );
            $this->recordEvidence(
                action: 'curriculum.graph.review_recorded',
                aggregateType: 'curriculum_graph_review',
                aggregateId: $reviewId,
                curriculumVersionId: $curriculumVersionId,
                membershipId: $actorMembershipId,
            );

            return $reviewId;
        }, 3);
    }

    public function withdraw(
        User $actor,
        string $curriculumGraphReviewId,
        string $authorityAppointmentId,
        string $rationaleKey,
        string $idempotencyKey,
    ): string {
        GovernanceInput::ulid($curriculumGraphReviewId);
        GovernanceInput::ulid($authorityAppointmentId);
        $rationaleKey = GovernanceInput::key($rationaleKey);
        GovernanceInput::idempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $curriculumGraphReviewId,
            $authorityAppointmentId,
            $rationaleKey,
            $idempotencyKey,
        ): string {
            $actorMembershipId = $this->assertActorPermission(
                $actor,
                'curriculum.review.en',
            );
            $context = $this->contexts->current();
            $target = DB::table('curriculum_graph_reviews')
                ->join('curriculum_versions', function ($join): void {
                    $join
                        ->on(
                            'curriculum_versions.organization_id',
                            '=',
                            'curriculum_graph_reviews.organization_id',
                        )
                        ->on(
                            'curriculum_versions.id',
                            '=',
                            'curriculum_graph_reviews.curriculum_version_id',
                        );
                })
                ->join('curriculum_graph_revisions', function ($join): void {
                    $join
                        ->on(
                            'curriculum_graph_revisions.organization_id',
                            '=',
                            'curriculum_graph_reviews.organization_id',
                        )
                        ->on(
                            'curriculum_graph_revisions.curriculum_version_id',
                            '=',
                            'curriculum_graph_reviews.curriculum_version_id',
                        )
                        ->on(
                            'curriculum_graph_revisions.id',
                            '=',
                            'curriculum_graph_reviews.curriculum_graph_revision_id',
                        );
                })
                ->where(
                    'curriculum_graph_reviews.organization_id',
                    $context->organizationId,
                )
                ->where(
                    'curriculum_graph_reviews.id',
                    $curriculumGraphReviewId,
                )
                ->lockForUpdate()
                ->first([
                    'curriculum_graph_reviews.curriculum_version_id',
                    'curriculum_graph_reviews.curriculum_graph_revision_id',
                    'curriculum_graph_reviews.graph_hash',
                    'curriculum_graph_reviews.decision',
                    'curriculum_graph_reviews.reviewed_at',
                    'curriculum_graph_revisions.authored_by_membership_id',
                    'curriculum_versions.program_id',
                ]);

            if (
                ! $target instanceof stdClass
                || $this->string($target, 'decision') !== 'approved'
            ) {
                throw new CurriculumOperationRejected;
            }

            $curriculumVersionId = $this->string(
                $target,
                'curriculum_version_id',
            );
            $graphRevisionId = $this->string(
                $target,
                'curriculum_graph_revision_id',
            );
            $graphHash = $this->string($target, 'graph_hash');
            $operation = 'curriculum.graph_review.withdraw';
            $fingerprint = CanonicalJson::sha256([
                'actor_membership_id' => $actorMembershipId,
                'actor_user_id' => (string) $actor->getKey(),
                'authority_appointment_id' => $authorityAppointmentId,
                'curriculum_graph_review_id' => $curriculumGraphReviewId,
                'operation' => $operation,
                'rationale_key' => $rationaleKey,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'curriculum',
                aggregateId: $curriculumGraphReviewId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            $latest = DB::table('curriculum_graph_reviews')
                ->where('organization_id', $context->organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->where('curriculum_graph_revision_id', $graphRevisionId)
                ->where('graph_hash', $graphHash)
                ->orderByDesc('reviewed_at')
                ->orderByDesc('id')
                ->first(['id']);

            if (
                ! $latest instanceof stdClass
                || ! hash_equals(
                    $this->string($latest, 'id'),
                    $curriculumGraphReviewId,
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $authorMembershipId = $this->nullableString(
                $target,
                'authored_by_membership_id',
            );

            if (
                $authorMembershipId !== null
                && $this->sameHuman(
                    $context->organizationId,
                    $authorMembershipId,
                    $actorMembershipId,
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $now = Date::now()->toImmutable();
            $targetReviewedAt = CarbonImmutable::parse(
                $this->string($target, 'reviewed_at'),
            );

            if (! $now->greaterThan($targetReviewedAt)) {
                $now = $targetReviewedAt->addMicrosecond();
            }
            $appointment = $this->authority->acceptedForNewEvidence(
                actor: $actor,
                appointmentId: $authorityAppointmentId,
                authorityKind: AuthorityKind::EnglishAcademicReviewer,
                programId: $this->string($target, 'program_id'),
                at: $now,
            );
            $withdrawalId = (string) Str::ulid();

            DB::table('curriculum_graph_reviews')->insert([
                'id' => $withdrawalId,
                'organization_id' => $context->organizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'curriculum_graph_revision_id' => $graphRevisionId,
                'graph_hash' => $graphHash,
                'authority_kind' => AuthorityKind::EnglishAcademicReviewer->value,
                'authority_appointment_id' => $appointment->appointmentId,
                'authority_appointment_event_id' => $appointment->acceptanceEventId,
                'authority_appointment_event_type' => 'accepted',
                'reviewer_membership_id' => $appointment->appointeeMembershipId,
                'decision' => 'withdrawn',
                'rationale_key' => $rationaleKey,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->recordCommand(
                actor: $actor,
                actorMembershipId: $actorMembershipId,
                operation: $operation,
                aggregateId: $curriculumGraphReviewId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'curriculum_graph_review',
                resultId: $withdrawalId,
            );
            $this->recordEvidence(
                action: 'curriculum.graph_review.withdrawn',
                aggregateType: 'curriculum_graph_review',
                aggregateId: $withdrawalId,
                curriculumVersionId: $curriculumVersionId,
                membershipId: $actorMembershipId,
            );

            return $withdrawalId;
        }, 3);
    }

    private function assertActorPermission(
        User $actor,
        string $permission,
    ): string {
        $context = $this->contexts->current();

        if (
            $context->membershipId === null
            || ! hash_equals($context->actorUserId, (string) $actor->getKey())
            || ! $this->authorization->allows($actor, $permission)
        ) {
            throw new CurriculumOperationRejected;
        }

        return $context->membershipId;
    }

    private function sameHuman(
        string $organizationId,
        string $leftMembershipId,
        string $rightMembershipId,
    ): bool {
        $userIds = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->whereIn('id', [$leftMembershipId, $rightMembershipId])
            ->pluck('user_id', 'id');
        $leftUserId = $userIds->get($leftMembershipId);
        $rightUserId = $userIds->get($rightMembershipId);

        if (! is_string($leftUserId) || ! is_string($rightUserId)) {
            throw new CurriculumOperationRejected;
        }

        return hash_equals($leftUserId, $rightUserId);
    }

    private function recordCommand(
        User $actor,
        string $actorMembershipId,
        string $operation,
        string $aggregateId,
        string $idempotencyKey,
        string $fingerprint,
        string $resultType,
        string $resultId,
    ): void {
        $now = Date::now();

        DB::table('publication_commands')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $this->contexts->current()->organizationId,
            'operation' => $operation,
            'aggregate_type' => 'curriculum',
            'aggregate_id' => $aggregateId,
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $fingerprint,
            'result_type' => $resultType,
            'result_id' => $resultId,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $actorMembershipId,
            'actor_user_id' => $actor->getKey(),
            'completed_at' => $now,
            'created_at' => $now,
        ]);
    }

    private function recordEvidence(
        string $action,
        string $aggregateType,
        string $aggregateId,
        string $curriculumVersionId,
        string $membershipId,
    ): void {
        $context = $this->contexts->current();
        $this->audit->recordTenant($action, $aggregateType, $aggregateId);
        $this->outbox->recordTenant(
            $action,
            $aggregateType,
            $aggregateId,
            [
                'curriculum_version_id' => $curriculumVersionId,
                'membership_id' => $membershipId,
                'organization_id' => $context->organizationId,
            ],
        );
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
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

        throw new CurriculumOperationRejected;
    }
}
