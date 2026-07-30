<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Enrollment\EnrollmentCurriculumPinner;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\CurriculumPublicationActivator;
use App\Curriculum\Publication\CurriculumPublicationScheduler;
use App\Curriculum\Publication\CurriculumPublisher;
use App\Curriculum\Publication\CurriculumRetirementService;
use App\Curriculum\Publication\CurriculumRollbackService;
use App\Curriculum\Publication\PublicationEvidenceRevalidator;
use App\Curriculum\Sharing\CurriculumSharingService;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CurriculumPublicationLifecycleTest extends TenancyIntegrationTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_unapproved_kannada_blocks_publish_without_residue_then_exact_evidence_publishes_and_replays(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T12:00:00+00:00');
        $organization = $this->createOrganization('Publication Evidence Tenant');
        $authority = $this->createPublicationAuthority($organization);
        $version = $this->createReadyVersion(
            authority: $authority,
            versionKey: 'v1',
            includeKannadaReview: false,
        );
        $publisher = $this->app->make(CurriculumPublisher::class);

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $publisher->publish(
                    actor: $authority['operator'],
                    curriculumVersionId: $version['version_id'],
                    expectedHeadVersion: 0,
                    idempotencyKey: 'publish-v1',
                ),
            ),
        );

        self::assertSame(
            0,
            DB::table('curriculum_publications')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_events')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_heads')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_commands')
                ->where('organization_id', $organization->getKey())
                ->where('operation', 'curriculum.publish')
                ->where('aggregate_id', $authority['program_id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('audit_events')
                ->where('organization_id', $organization->getKey())
                ->where('action', 'curriculum.publication.activated')
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('outbox_events')
                ->where('organization_id', $organization->getKey())
                ->where('event_type', 'curriculum.publication.activated')
                ->count(),
        );

        $kannadaReviewId = $this->insertKannadaReview(
            authority: $authority,
            version: $version,
        );
        $published = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $version['version_id'],
                expectedHeadVersion: 0,
                idempotencyKey: 'publish-v1',
            ),
        );
        $replayed = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $version['version_id'],
                expectedHeadVersion: 0,
                idempotencyKey: 'publish-v1',
            ),
        );

        self::assertFalse($published->replayed);
        self::assertTrue($replayed->replayed);
        self::assertSame($published->publicationId, $replayed->publicationId);
        self::assertSame($published->manifestHash, $replayed->manifestHash);
        self::assertSame(1, $published->headVersion);
        self::assertSame(1, $replayed->headVersion);
        self::assertSame(
            1,
            DB::table('curriculum_publications')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->count(),
        );
        self::assertSame(
            2,
            DB::table('publication_events')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->count(),
        );
        $this->assertDatabaseHas('publication_events', [
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'curriculum_publication_id' => $published->publicationId,
            'event_type' => 'scheduled',
            'reason_key' => 'immediate_publish',
        ]);
        self::assertSame(
            1,
            DB::table('publication_commands')
                ->where('organization_id', $organization->getKey())
                ->where('operation', 'curriculum.publish')
                ->where('aggregate_id', $authority['program_id'])
                ->count(),
        );
        $this->assertDatabaseHas('publication_content_components', [
            'organization_id' => $organization->getKey(),
            'curriculum_publication_id' => $published->publicationId,
            'content_document_id' => $version['document_id'],
            'source_revision_id' => $version['source_revision_id'],
            'source_content_hash' => $version['source_hash'],
            'localized_variant_id' => $version['variant_id'],
            'localized_content_hash' => $version['variant_hash'],
            'kannada_review_id' => $kannadaReviewId,
        ]);
        $this->assertDatabaseHas('publication_graph_components', [
            'organization_id' => $organization->getKey(),
            'curriculum_publication_id' => $published->publicationId,
            'curriculum_graph_revision_id' => $version['graph_revision_id'],
            'graph_hash' => $version['graph_hash'],
            'curriculum_graph_review_id' => $version['graph_review_id'],
        ]);

        try {
            DB::table('learning_objectives')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organization->getKey(),
                'curriculum_version_id' => $version['version_id'],
                'key' => 'forged-after-publication',
                'outcome_code' => 'synthetic.forged',
                'sequence' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            self::fail('A direct mutation of a published graph must fail.');
        } catch (QueryException $exception) {
            self::assertSame('55000', (string) $exception->getCode());
            self::assertStringContainsString(
                'Published curriculum graph is immutable',
                $exception->getMessage(),
            );
        }
    }

    public function test_missing_stale_or_rejected_graph_review_blocks_publication(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T12:30:00+00:00');
        $organization = $this->createOrganization('Graph Review Tenant');
        $authority = $this->createPublicationAuthority($organization);
        $publisher = $this->app->make(CurriculumPublisher::class);

        $missing = $this->createReadyVersion(
            authority: $authority,
            versionKey: 'graph-review-missing',
            includeGraphReview: false,
        );

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $publisher->publish(
                    actor: $authority['operator'],
                    curriculumVersionId: $missing['version_id'],
                    expectedHeadVersion: 0,
                    idempotencyKey: 'publish-without-graph-review',
                ),
            ),
        );

        $stale = $this->createReadyVersion(
            authority: $authority,
            versionKey: 'graph-review-stale',
        );
        DB::table('learning_objectives')
            ->where('organization_id', $organization->getKey())
            ->where('curriculum_version_id', $stale['version_id'])
            ->where('id', $stale['objective_id'])
            ->update([
                'outcome_code' => 'synthetic.graph-review-stale.changed',
                'updated_at' => now(),
            ]);

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $publisher->publish(
                    actor: $authority['operator'],
                    curriculumVersionId: $stale['version_id'],
                    expectedHeadVersion: 0,
                    idempotencyKey: 'publish-with-stale-graph-review',
                ),
            ),
        );

        $rejected = $this->createReadyVersion(
            authority: $authority,
            versionKey: 'graph-review-rejected',
        );
        CarbonImmutable::setTestNow('2026-07-30T12:30:01+00:00');
        DB::table('curriculum_graph_reviews')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'curriculum_version_id' => $rejected['version_id'],
            'curriculum_graph_revision_id' => $rejected['graph_revision_id'],
            'graph_hash' => $rejected['graph_hash'],
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $authority['english_appointment_id'],
            'authority_appointment_event_id' => $authority['english_appointment_event_id'],
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $authority['english_reviewer_membership_id'],
            'decision' => 'rejected',
            'rationale_key' => 'graph_review_rejected',
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $publisher->publish(
                    actor: $authority['operator'],
                    curriculumVersionId: $rejected['version_id'],
                    expectedHeadVersion: 0,
                    idempotencyKey: 'publish-with-rejected-graph-review',
                ),
            ),
        );

        self::assertSame(
            0,
            DB::table('curriculum_publications')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->count(),
        );
    }

    public function test_owner_and_shared_pins_require_the_exact_active_release_and_survive_withdrawal_and_rollback(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T13:00:00+00:00');
        $owner = $this->createOrganization('Curriculum Owner Tenant');
        $authority = $this->createPublicationAuthority($owner);
        $publisher = $this->app->make(CurriculumPublisher::class);
        $versionOne = $this->createReadyVersion(
            authority: $authority,
            versionKey: 'v1',
        );
        $publicationOne = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $versionOne['version_id'],
                expectedHeadVersion: 0,
                idempotencyKey: 'publish-v1',
            ),
        );
        $versionTwo = $this->createReadyVersion(
            authority: $authority,
            versionKey: 'v2',
        );
        $publicationTwo = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $versionTwo['version_id'],
                expectedHeadVersion: 1,
                idempotencyKey: 'publish-v2',
            ),
        );
        $pinner = $this->app->make(EnrollmentCurriculumPinner::class);
        $ownerEnrollmentId = $this->createPendingEnrollment(
            $owner,
            $authority['operator_context'],
        );

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $pinner->activate(
                    actor: $authority['operator'],
                    enrollmentId: $ownerEnrollmentId,
                    publicationId: $publicationOne->publicationId,
                    shareGrantId: null,
                    idempotencyKey: 'owner-pin-inactive-v1',
                ),
            ),
        );
        $ownerPin = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $pinner->activate(
                actor: $authority['operator'],
                enrollmentId: $ownerEnrollmentId,
                publicationId: $publicationTwo->publicationId,
                shareGrantId: null,
                idempotencyKey: 'owner-pin-v2',
            ),
        );

        self::assertSame($owner->getKey(), $ownerPin->organizationId);
        self::assertSame(
            $owner->getKey(),
            $ownerPin->curriculumOwnerOrganizationId,
        );
        self::assertSame($versionTwo['version_id'], $ownerPin->curriculumVersionId);
        self::assertSame($publicationTwo->publicationId, $ownerPin->publicationId);
        self::assertNull($ownerPin->shareGrantId);
        self::assertSame('active', $ownerPin->status);

        $recipient = $this->createOrganization('Shared Curriculum Recipient');
        $recipientOperator = $this->createUser();
        [$recipientMembership, $recipientContext] = $this->createMembership(
            $recipient,
            $recipientOperator,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            membership: $recipientMembership,
            context: $recipientContext,
            permissionKeys: ['enrollments.curriculum.assign'],
        );
        $sharing = $this->app->make(CurriculumSharingService::class);
        $grant = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $sharing->issue(
                actor: $authority['operator'],
                publicationId: $publicationTwo->publicationId,
                recipientOrganizationId: (string) $recipient->getKey(),
                allowedUses: ['enrollment'],
                effectiveAt: now(),
                expiresAt: now()->addDay(),
                idempotencyKey: 'share-v2-enrollment',
            ),
        );
        $sharedEnrollmentId = $this->createPendingEnrollment(
            $recipient,
            $recipientContext,
        );

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $recipientContext,
                fn () => $pinner->activate(
                    actor: $recipientOperator,
                    enrollmentId: $sharedEnrollmentId,
                    publicationId: $publicationTwo->publicationId,
                    shareGrantId: null,
                    idempotencyKey: 'shared-pin-without-grant',
                ),
            ),
        );
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $recipientContext,
                fn () => $pinner->activate(
                    actor: $recipientOperator,
                    enrollmentId: $sharedEnrollmentId,
                    publicationId: $publicationOne->publicationId,
                    shareGrantId: $grant->shareGrantId,
                    idempotencyKey: 'shared-pin-wrong-publication',
                ),
            ),
        );
        $sharedPin = $this->contexts()->run(
            $recipientContext,
            fn () => $pinner->activate(
                actor: $recipientOperator,
                enrollmentId: $sharedEnrollmentId,
                publicationId: $publicationTwo->publicationId,
                shareGrantId: $grant->shareGrantId,
                idempotencyKey: 'shared-pin-v2',
            ),
        );

        self::assertSame($recipient->getKey(), $sharedPin->organizationId);
        self::assertSame(
            $owner->getKey(),
            $sharedPin->curriculumOwnerOrganizationId,
        );
        self::assertSame($versionTwo['version_id'], $sharedPin->curriculumVersionId);
        self::assertSame($publicationTwo->publicationId, $sharedPin->publicationId);
        self::assertSame($grant->shareGrantId, $sharedPin->shareGrantId);

        $withdrawn = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $sharing->withdraw(
                actor: $authority['operator'],
                shareGrantId: $grant->shareGrantId,
                expectedGrantVersion: 1,
                idempotencyKey: 'withdraw-v2-share',
                reasonKey: 'ordinary_owner_withdrawal',
            ),
        );
        self::assertSame('withdrawn', $withdrawn->status);

        $blockedEnrollmentId = $this->createPendingEnrollment(
            $recipient,
            $recipientContext,
        );
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $recipientContext,
                fn () => $pinner->activate(
                    actor: $recipientOperator,
                    enrollmentId: $blockedEnrollmentId,
                    publicationId: $publicationTwo->publicationId,
                    shareGrantId: $grant->shareGrantId,
                    idempotencyKey: 'withdrawn-share-new-pin',
                ),
            ),
        );
        $this->assertDatabaseHas('enrollments', [
            'id' => $blockedEnrollmentId,
            'organization_id' => $recipient->getKey(),
            'status' => 'pending_curriculum',
            'curriculum_publication_id' => null,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'id' => $sharedEnrollmentId,
            'organization_id' => $recipient->getKey(),
            'status' => 'active',
            'curriculum_owner_organization_id' => $owner->getKey(),
            'curriculum_version_id' => $versionTwo['version_id'],
            'curriculum_publication_id' => $publicationTwo->publicationId,
            'curriculum_share_grant_id' => $grant->shareGrantId,
        ]);

        $rollback = $this->app->make(CurriculumRollbackService::class);
        $rollbackResult = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $rollback->rollback(
                actor: $authority['operator'],
                programId: $authority['program_id'],
                targetPublicationId: $publicationOne->publicationId,
                expectedHeadVersion: 2,
                idempotencyKey: 'rollback-to-v1',
                reasonKey: 'verified_release_recovery',
            ),
        );

        self::assertSame($publicationOne->publicationId, $rollbackResult->publicationId);
        self::assertSame($versionOne['version_id'], $rollbackResult->curriculumVersionId);
        self::assertSame(3, $rollbackResult->headVersion);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $owner->getKey(),
            'program_id' => $authority['program_id'],
            'active_curriculum_version_id' => $versionOne['version_id'],
            'active_publication_id' => $publicationOne->publicationId,
            'lock_version' => 3,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'id' => $ownerEnrollmentId,
            'organization_id' => $owner->getKey(),
            'status' => 'active',
            'curriculum_version_id' => $versionTwo['version_id'],
            'curriculum_publication_id' => $publicationTwo->publicationId,
            'curriculum_share_grant_id' => null,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'id' => $sharedEnrollmentId,
            'organization_id' => $recipient->getKey(),
            'status' => 'active',
            'curriculum_owner_organization_id' => $owner->getKey(),
            'curriculum_version_id' => $versionTwo['version_id'],
            'curriculum_publication_id' => $publicationTwo->publicationId,
            'curriculum_share_grant_id' => $grant->shareGrantId,
        ]);

        $retirement = $this->app->make(CurriculumRetirementService::class);
        $retired = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $retirement->retire(
                actor: $authority['operator'],
                programId: $authority['program_id'],
                expectedHeadVersion: 3,
                idempotencyKey: 'retire-after-rollback',
                reasonKey: 'verified_release_retirement',
            ),
        );
        $retiredReplay = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $retirement->retire(
                actor: $authority['operator'],
                programId: $authority['program_id'],
                expectedHeadVersion: 3,
                idempotencyKey: 'retire-after-rollback',
                reasonKey: 'verified_release_retirement',
            ),
        );

        self::assertFalse($retired->replayed);
        self::assertTrue($retiredReplay->replayed);
        self::assertSame(4, $retired->headVersion);
        self::assertSame($publicationOne->publicationId, $retired->publicationId);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $owner->getKey(),
            'program_id' => $authority['program_id'],
            'active_curriculum_version_id' => null,
            'active_publication_id' => null,
            'is_suspended' => false,
            'lock_version' => 4,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'id' => $sharedEnrollmentId,
            'status' => 'active',
            'curriculum_publication_id' => $publicationTwo->publicationId,
        ]);

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $pinner->activate(
                    actor: $authority['operator'],
                    enrollmentId: $ownerEnrollmentId,
                    publicationId: $publicationOne->publicationId,
                    shareGrantId: null,
                    idempotencyKey: 'forbidden-owner-repin',
                ),
            ),
        );

        $restoredAfterRetirement = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $rollback->rollback(
                actor: $authority['operator'],
                programId: $authority['program_id'],
                targetPublicationId: $publicationTwo->publicationId,
                expectedHeadVersion: 4,
                idempotencyKey: 'rollback-after-retirement',
                reasonKey: 'verified_release_restoration',
            ),
        );

        self::assertSame(5, $restoredAfterRetirement->headVersion);
        self::assertSame(
            $publicationTwo->publicationId,
            $restoredAfterRetirement->publicationId,
        );
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $owner->getKey(),
            'program_id' => $authority['program_id'],
            'active_curriculum_version_id' => $versionTwo['version_id'],
            'active_publication_id' => $publicationTwo->publicationId,
            'lock_version' => 5,
        ]);
        $this->assertDatabaseHas('publication_events', [
            'organization_id' => $owner->getKey(),
            'program_id' => $authority['program_id'],
            'curriculum_publication_id' => $publicationTwo->publicationId,
            'event_type' => 'rollback_activated',
            'previous_curriculum_version_id' => null,
            'previous_publication_id' => null,
        ]);

        try {
            DB::table('enrollments')
                ->where('organization_id', $owner->getKey())
                ->where('id', $ownerEnrollmentId)
                ->update([
                    'curriculum_version_id' => $versionOne['version_id'],
                    'curriculum_publication_id' => $publicationOne->publicationId,
                    'updated_at' => now(),
                ]);
            self::fail('An activated enrollment pin must be immutable.');
        } catch (QueryException $exception) {
            self::assertSame('55000', (string) $exception->getCode());
            self::assertStringContainsString(
                'Enrollment curriculum pin is immutable',
                $exception->getMessage(),
            );
        }
    }

    public function test_future_schedule_preserves_the_active_release_until_revalidated_activation_and_replays_exactly(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T12:00:00+00:00');
        $organization = $this->createOrganization('Scheduled Publication Tenant');
        $authority = $this->createPublicationAuthority($organization);
        $versionOne = $this->createReadyVersion($authority, 'scheduled-v1');
        $publisher = $this->app->make(CurriculumPublisher::class);
        $active = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $versionOne['version_id'],
                expectedHeadVersion: 0,
                idempotencyKey: 'scheduled-baseline-publish',
            ),
        );
        $versionTwo = $this->createReadyVersion($authority, 'scheduled-v2');
        $scheduler = $this->app->make(CurriculumPublicationScheduler::class);
        $activationAt = CarbonImmutable::parse(
            '2026-07-30T13:00:00+00:00',
        );
        $scheduled = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $scheduler->schedule(
                actor: $authority['operator'],
                curriculumVersionId: $versionTwo['version_id'],
                activationAt: $activationAt,
                expectedHeadVersion: 1,
                idempotencyKey: 'schedule-v2',
            ),
        );
        $scheduleReplay = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $scheduler->schedule(
                actor: $authority['operator'],
                curriculumVersionId: $versionTwo['version_id'],
                activationAt: $activationAt,
                expectedHeadVersion: 1,
                idempotencyKey: 'schedule-v2',
            ),
        );

        self::assertFalse($scheduled->replayed);
        self::assertTrue($scheduleReplay->replayed);
        self::assertSame($scheduled->publicationId, $scheduleReplay->publicationId);
        self::assertSame($scheduled->manifestHash, $scheduleReplay->manifestHash);
        self::assertTrue($scheduled->activationAt->equalTo($activationAt));
        self::assertSame(2, $scheduled->headVersion);
        self::assertSame(2, $scheduleReplay->headVersion);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'active_curriculum_version_id' => $versionOne['version_id'],
            'active_publication_id' => $active->publicationId,
            'lock_version' => 2,
        ]);
        $this->assertDatabaseHas('publication_events', [
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'curriculum_publication_id' => $scheduled->publicationId,
            'event_type' => 'scheduled',
        ]);

        $this->assertIdempotencyConflict(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $scheduler->schedule(
                    actor: $authority['operator'],
                    curriculumVersionId: $versionTwo['version_id'],
                    activationAt: $activationAt->addHour(),
                    expectedHeadVersion: 1,
                    idempotencyKey: 'schedule-v2',
                ),
            ),
        );

        $activator = $this->app->make(CurriculumPublicationActivator::class);
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $activator->activate(
                    actor: $authority['operator'],
                    publicationId: $scheduled->publicationId,
                    expectedHeadVersion: 2,
                    idempotencyKey: 'activate-v2-too-early',
                ),
            ),
        );
        self::assertSame(
            0,
            DB::table('publication_commands')
                ->where('organization_id', $organization->getKey())
                ->where('operation', 'curriculum.activate')
                ->count(),
        );

        CarbonImmutable::setTestNow($activationAt);
        $activated = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $activator->activate(
                actor: $authority['operator'],
                publicationId: $scheduled->publicationId,
                expectedHeadVersion: 2,
                idempotencyKey: 'activate-v2',
            ),
        );
        $activationReplay = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $activator->activate(
                actor: $authority['operator'],
                publicationId: $scheduled->publicationId,
                expectedHeadVersion: 2,
                idempotencyKey: 'activate-v2',
            ),
        );

        self::assertFalse($activated->replayed);
        self::assertTrue($activationReplay->replayed);
        self::assertSame($scheduled->publicationId, $activated->publicationId);
        self::assertSame($activated->publicationId, $activationReplay->publicationId);
        self::assertSame($activated->manifestHash, $activationReplay->manifestHash);
        self::assertTrue($activated->activatedAt->equalTo($activationAt));
        self::assertSame(3, $activated->headVersion);
        self::assertSame(3, $activationReplay->headVersion);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'active_curriculum_version_id' => $versionTwo['version_id'],
            'active_publication_id' => $scheduled->publicationId,
            'lock_version' => 3,
        ]);
        $this->assertDatabaseHas('publication_events', [
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'curriculum_publication_id' => $scheduled->publicationId,
            'event_type' => 'activated',
            'previous_curriculum_version_id' => $versionOne['version_id'],
            'previous_publication_id' => $active->publicationId,
        ]);
        self::assertSame(
            1,
            DB::table('publication_events')
                ->where('organization_id', $organization->getKey())
                ->where('curriculum_publication_id', $scheduled->publicationId)
                ->where('event_type', 'activated')
                ->count(),
        );

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $activator->activate(
                    actor: $authority['operator'],
                    publicationId: $scheduled->publicationId,
                    expectedHeadVersion: 2,
                    idempotencyKey: 'stale-second-activation',
                ),
            ),
        );
    }

    public function test_review_withdrawal_between_schedule_and_activation_fails_without_activation_residue(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T14:00:00+00:00');
        $organization = $this->createOrganization('Scheduled Review Withdrawal');
        $authority = $this->createPublicationAuthority($organization);
        $version = $this->createReadyVersion($authority, 'withdraw-review');
        $scheduler = $this->app->make(CurriculumPublicationScheduler::class);
        $scheduled = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $scheduler->schedule(
                actor: $authority['operator'],
                curriculumVersionId: $version['version_id'],
                activationAt: CarbonImmutable::parse(
                    '2026-07-30T15:00:00+00:00',
                ),
                expectedHeadVersion: 0,
                idempotencyKey: 'schedule-review-withdrawal',
            ),
        );

        CarbonImmutable::setTestNow('2026-07-30T14:30:00+00:00');
        DB::table('content_reviews')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $version['version_id'],
            'review_kind' => 'academic_en',
            'source_revision_id' => $version['source_revision_id'],
            'localized_variant_id' => null,
            'authority_appointment_id' => $authority['english_appointment_id'],
            'authority_appointment_event_id' => $authority['english_appointment_event_id'],
            'reviewer_membership_id' => $authority['english_reviewer_membership_id'],
            'decision' => 'withdrawn',
            'source_content_hash' => $version['source_hash'],
            'localized_content_hash' => null,
            'rationale_key' => 'scheduled_review_withdrawal',
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CarbonImmutable::setTestNow('2026-07-30T15:00:00+00:00');
        $activator = $this->app->make(CurriculumPublicationActivator::class);
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $activator->activate(
                    actor: $authority['operator'],
                    publicationId: $scheduled->publicationId,
                    expectedHeadVersion: 1,
                    idempotencyKey: 'activate-withdrawn-review',
                ),
            ),
        );
        $this->assertScheduledActivationDidNotAdvance(
            organizationId: (string) $organization->getKey(),
            programId: $authority['program_id'],
            publicationId: $scheduled->publicationId,
        );
    }

    public function test_rights_withdrawal_between_schedule_and_activation_fails_without_activation_residue(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T16:00:00+00:00');
        $organization = $this->createOrganization('Scheduled Rights Withdrawal');
        $authority = $this->createPublicationAuthority($organization);
        $version = $this->createReadyVersion($authority, 'withdraw-rights');
        $rights = $this->attachApprovedMediaRights($authority, $version);
        $scheduler = $this->app->make(CurriculumPublicationScheduler::class);
        $scheduled = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $scheduler->schedule(
                actor: $authority['operator'],
                curriculumVersionId: $version['version_id'],
                activationAt: CarbonImmutable::parse(
                    '2026-07-30T17:00:00+00:00',
                ),
                expectedHeadVersion: 0,
                idempotencyKey: 'schedule-rights-withdrawal',
            ),
        );

        CarbonImmutable::setTestNow('2026-07-30T16:30:00+00:00');
        DB::table('media_rights_revisions')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $authority['organization_id'],
            'program_id' => $authority['program_id'],
            'media_asset_id' => $rights['media_asset_id'],
            'parent_revision_id' => $rights['rights_revision_id'],
            'revision_number' => 2,
            'evidence_status' => 'withdrawn',
            'evidence_hash' => hash(
                'sha256',
                'synthetic-scheduled-rights-withdrawal',
            ),
            'selected_asset_hash' => $rights['asset_hash'],
            'rights_holder_reference' => 'party:SYNTHETIC-RIGHTS-HOLDER',
            'provenance_reference' => 'provenance:SYNTHETIC-AUDIO',
            'rights_basis_key' => 'client_owned',
            'license_or_contract_reference' => 'TEST-RIGHTS-WITHDRAWN',
            'permitted_release_channels' => CanonicalJson::encode([
                'android',
                'web',
            ]),
            'permitted_territories' => CanonicalJson::encode(['IN']),
            'learner_delivery_allowed' => true,
            'sharing_allowed' => false,
            'derivative_use_allowed' => false,
            'effective_at' => now(),
            'expires_at' => null,
            'retention_policy_key' => 'synthetic_test_retention',
            'deletion_constraint_key' => 'synthetic_test_preserve_history',
            'confirmed_by_membership_id' => $rights['confirmer_membership_id'],
            'rights_appointment_id' => $rights['appointment_id'],
            'rights_appointment_event_id' => $rights['appointment_event_id'],
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CarbonImmutable::setTestNow('2026-07-30T17:00:00+00:00');
        $activator = $this->app->make(CurriculumPublicationActivator::class);
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $activator->activate(
                    actor: $authority['operator'],
                    publicationId: $scheduled->publicationId,
                    expectedHeadVersion: 1,
                    idempotencyKey: 'activate-withdrawn-rights',
                ),
            ),
        );
        $this->assertScheduledActivationDidNotAdvance(
            organizationId: (string) $organization->getKey(),
            programId: $authority['program_id'],
            publicationId: $scheduled->publicationId,
        );
    }

    public function test_successor_rights_require_a_new_publication_without_rewriting_prior_evidence(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T18:00:00+00:00');
        $organization = $this->createOrganization('Successor Rights Publication');
        $authority = $this->createPublicationAuthority($organization);
        $version = $this->createReadyVersion($authority, 'successor-rights');
        $rights = $this->attachApprovedMediaRights($authority, $version);
        $publisher = $this->app->make(CurriculumPublisher::class);
        $firstPublication = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $version['version_id'],
                expectedHeadVersion: 0,
                idempotencyKey: 'publish-first-rights-evidence',
            ),
        );

        CarbonImmutable::setTestNow('2026-07-30T18:01:00+00:00');
        $successorRightsId = (string) Str::ulid();
        $successorEvidenceHash = hash(
            'sha256',
            'synthetic-successor-rights-evidence',
        );
        DB::table('media_rights_revisions')->insert([
            'id' => $successorRightsId,
            'organization_id' => $authority['organization_id'],
            'program_id' => $authority['program_id'],
            'media_asset_id' => $rights['media_asset_id'],
            'parent_revision_id' => $rights['rights_revision_id'],
            'revision_number' => 2,
            'evidence_status' => 'approved',
            'evidence_hash' => $successorEvidenceHash,
            'selected_asset_hash' => $rights['asset_hash'],
            'rights_holder_reference' => 'party:SYNTHETIC-RIGHTS-HOLDER',
            'provenance_reference' => 'provenance:SYNTHETIC-AUDIO-SUCCESSOR',
            'rights_basis_key' => 'successor_release',
            'license_or_contract_reference' => 'TEST-RIGHTS-SUCCESSOR',
            'permitted_release_channels' => CanonicalJson::encode([
                'android',
                'web',
            ]),
            'permitted_territories' => CanonicalJson::encode(['IN']),
            'learner_delivery_allowed' => true,
            'sharing_allowed' => true,
            'derivative_use_allowed' => true,
            'effective_at' => now(),
            'expires_at' => now()->addDay(),
            'retention_policy_key' => 'synthetic_test_retention',
            'deletion_constraint_key' => 'synthetic_test_preserve_history',
            'confirmed_by_membership_id' => $rights[
                'confirmer_membership_id'
            ],
            'rights_appointment_id' => $rights['appointment_id'],
            'rights_appointment_event_id' => $rights[
                'appointment_event_id'
            ],
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondPublication = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $version['version_id'],
                expectedHeadVersion: 1,
                idempotencyKey: 'publish-successor-rights-evidence',
            ),
        );

        self::assertNotSame(
            $firstPublication->publicationId,
            $secondPublication->publicationId,
        );
        self::assertNotSame(
            $firstPublication->manifestHash,
            $secondPublication->manifestHash,
        );
        $this->assertDatabaseHas('publication_media_components', [
            'organization_id' => $authority['organization_id'],
            'curriculum_publication_id' => $firstPublication->publicationId,
            'media_asset_id' => $rights['media_asset_id'],
            'media_rights_revision_id' => $rights['rights_revision_id'],
        ]);
        $this->assertDatabaseHas('publication_media_components', [
            'organization_id' => $authority['organization_id'],
            'curriculum_publication_id' => $secondPublication->publicationId,
            'media_asset_id' => $rights['media_asset_id'],
            'media_rights_revision_id' => $successorRightsId,
            'rights_evidence_hash' => $successorEvidenceHash,
        ]);

        $verifiedPrior = $this->app
            ->make(PublicationEvidenceRevalidator::class)
            ->verify(
                ownerOrganizationId: $authority['organization_id'],
                publicationId: $firstPublication->publicationId,
                evaluatedAt: now(),
            );
        self::assertSame(
            $firstPublication->publicationId,
            $verifiedPrior->publicationId,
        );
    }

    public function test_rights_expiry_blocks_activation_at_the_exact_expiry_instant(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T20:00:00+00:00');
        $organization = $this->createOrganization('Scheduled Rights Expiry');
        $authority = $this->createPublicationAuthority($organization);
        $version = $this->createReadyVersion($authority, 'expire-rights');
        $expiry = CarbonImmutable::parse('2026-07-30T21:00:00+00:00');
        $this->attachApprovedMediaRights(
            authority: $authority,
            version: $version,
            expiresAt: $expiry,
        );
        $scheduler = $this->app->make(CurriculumPublicationScheduler::class);
        $scheduled = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $scheduler->schedule(
                actor: $authority['operator'],
                curriculumVersionId: $version['version_id'],
                activationAt: $expiry,
                expectedHeadVersion: 0,
                idempotencyKey: 'schedule-rights-expiry',
            ),
        );

        CarbonImmutable::setTestNow($expiry);
        $activator = $this->app->make(CurriculumPublicationActivator::class);
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $activator->activate(
                    actor: $authority['operator'],
                    publicationId: $scheduled->publicationId,
                    expectedHeadVersion: 1,
                    idempotencyKey: 'activate-at-rights-expiry',
                ),
            ),
        );
        $this->assertScheduledActivationDidNotAdvance(
            organizationId: (string) $organization->getKey(),
            programId: $authority['program_id'],
            publicationId: $scheduled->publicationId,
        );
    }

    public function test_rollback_rejects_a_publication_that_was_only_scheduled_without_residue(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T21:00:00+00:00');
        $organization = $this->createOrganization('Scheduled Rollback Rejection');
        $authority = $this->createPublicationAuthority($organization);
        $publisher = $this->app->make(CurriculumPublisher::class);
        $versionOne = $this->createReadyVersion(
            $authority,
            'scheduled-rollback-active',
        );
        $active = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $versionOne['version_id'],
                expectedHeadVersion: 0,
                idempotencyKey: 'scheduled-rollback-active',
            ),
        );
        $versionTwo = $this->createReadyVersion(
            $authority,
            'scheduled-rollback-target',
        );
        $scheduled = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $this->app
                ->make(CurriculumPublicationScheduler::class)
                ->schedule(
                    actor: $authority['operator'],
                    curriculumVersionId: $versionTwo['version_id'],
                    activationAt: now()->addHour(),
                    expectedHeadVersion: 1,
                    idempotencyKey: 'scheduled-rollback-target',
                ),
        );
        $eventCountBefore = DB::table('publication_events')
            ->where('organization_id', $organization->getKey())
            ->where('program_id', $authority['program_id'])
            ->count();
        $lastEventIdBefore = DB::table('publication_heads')
            ->where('organization_id', $organization->getKey())
            ->where('program_id', $authority['program_id'])
            ->value('last_event_id');

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $this->app
                    ->make(CurriculumRollbackService::class)
                    ->rollback(
                        actor: $authority['operator'],
                        programId: $authority['program_id'],
                        targetPublicationId: $scheduled->publicationId,
                        expectedHeadVersion: 2,
                        idempotencyKey: 'rollback-scheduled-only',
                        reasonKey: 'scheduled_target_must_not_activate',
                    ),
            ),
        );

        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'active_curriculum_version_id' => $versionOne['version_id'],
            'active_publication_id' => $active->publicationId,
            'last_event_id' => $lastEventIdBefore,
            'is_suspended' => false,
            'lock_version' => 2,
        ]);
        self::assertSame(
            $eventCountBefore,
            DB::table('publication_events')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_events')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->where('event_type', 'rollback_activated')
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_commands')
                ->where('organization_id', $organization->getKey())
                ->where('operation', 'curriculum.rollback')
                ->where('aggregate_id', $authority['program_id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('audit_events')
                ->where('organization_id', $organization->getKey())
                ->where('action', 'curriculum.publication.rollback_activated')
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('outbox_events')
                ->where('organization_id', $organization->getKey())
                ->where(
                    'event_type',
                    'curriculum.publication.rollback_activated',
                )
                ->count(),
        );
    }

    public function test_suspended_program_can_publish_a_compliant_replacement_without_restoring_delivery(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T22:00:00+00:00');
        $organization = $this->createOrganization('Suspended Replacement');
        $authority = $this->createPublicationAuthority($organization);
        $publisher = $this->app->make(CurriculumPublisher::class);
        $versionOne = $this->createReadyVersion(
            $authority,
            'suspended-replacement-v1',
        );
        $publicationOne = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $versionOne['version_id'],
                expectedHeadVersion: 0,
                idempotencyKey: 'suspended-replacement-v1',
            ),
        );

        CarbonImmutable::setTestNow('2026-07-30T22:05:00+00:00');
        $authenticationSession = AuthenticationSession::query()->forceCreate([
            'user_id' => $authority['operator']->getKey(),
            'storage_session_digest' => hash(
                'sha256',
                'suspended-replacement-'.Str::ulid(),
            ),
            'storage_session_ciphertext' => 'synthetic-test-session',
            'is_privileged' => true,
            'started_at' => now()->subMinute(),
            'last_seen_at' => now(),
            'idle_expires_at' => now()->addMinutes(30),
            'absolute_expires_at' => now()->addHours(6),
            'mfa_verified_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);
        $suspendedEventId = (string) Str::ulid();
        DB::table('publication_events')->insert([
            'id' => $suspendedEventId,
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'curriculum_version_id' => $versionOne['version_id'],
            'curriculum_publication_id' => $publicationOne->publicationId,
            'event_type' => 'suspended',
            'previous_curriculum_version_id' => null,
            'previous_publication_id' => null,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $authority['operator_membership']->getKey(),
            'platform_actor_user_id' => null,
            'authentication_session_id' => $authenticationSession->getKey(),
            'platform_audit_event_id' => null,
            'reason_key' => 'replace_before_resuming',
            'remediation_reference' => 'runbook.replace-before-resume.v1',
            'correlation_id' => (string) Str::uuid(),
            'effective_at' => now(),
            'created_at' => now(),
        ]);
        self::assertSame(
            1,
            DB::table('publication_heads')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->update([
                    'last_event_id' => $suspendedEventId,
                    'is_suspended' => true,
                    'lock_version' => 2,
                    'updated_at' => now(),
                ]),
        );

        CarbonImmutable::setTestNow('2026-07-30T22:10:00+00:00');
        $versionTwo = $this->createReadyVersion(
            $authority,
            'suspended-replacement-v2',
        );
        $publicationTwo = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $versionTwo['version_id'],
                expectedHeadVersion: 2,
                idempotencyKey: 'suspended-replacement-v2',
            ),
        );

        self::assertSame(3, $publicationTwo->headVersion);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'active_curriculum_version_id' => $versionTwo['version_id'],
            'active_publication_id' => $publicationTwo->publicationId,
            'is_suspended' => true,
            'lock_version' => 3,
        ]);
        self::assertSame(
            0,
            DB::table('publication_heads')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->where('is_suspended', false)
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_events')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->where('event_type', 'resumed')
                ->count(),
        );
        $this->assertDatabaseHas('publication_events', [
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'curriculum_publication_id' => $publicationTwo->publicationId,
            'event_type' => 'activated',
            'previous_curriculum_version_id' => $versionOne['version_id'],
            'previous_publication_id' => $publicationOne->publicationId,
            'reason_key' => 'replacement_publish',
        ]);
    }

    public function test_rollback_rejects_target_with_later_exact_review_withdrawal_without_residue(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T23:00:00+00:00');
        $organization = $this->createOrganization('Rollback Review Withdrawal');
        $authority = $this->createPublicationAuthority($organization);
        $publisher = $this->app->make(CurriculumPublisher::class);
        $versionOne = $this->createReadyVersion(
            $authority,
            'rollback-withdrawn-review-v1',
        );
        $publicationOne = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $versionOne['version_id'],
                expectedHeadVersion: 0,
                idempotencyKey: 'rollback-withdrawn-review-v1',
            ),
        );
        $versionTwo = $this->createReadyVersion(
            $authority,
            'rollback-withdrawn-review-v2',
        );
        $publicationTwo = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $publisher->publish(
                actor: $authority['operator'],
                curriculumVersionId: $versionTwo['version_id'],
                expectedHeadVersion: 1,
                idempotencyKey: 'rollback-withdrawn-review-v2',
            ),
        );
        $pinnedReviewId = DB::table('publication_content_components')
            ->where('organization_id', $organization->getKey())
            ->where(
                'curriculum_publication_id',
                $publicationOne->publicationId,
            )
            ->value('academic_review_id');
        self::assertIsString($pinnedReviewId);
        self::assertNotSame('', $pinnedReviewId);

        CarbonImmutable::setTestNow('2026-07-30T23:30:00+00:00');
        DB::table('content_reviews')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionOne['version_id'],
            'review_kind' => 'academic_en',
            'source_revision_id' => $versionOne['source_revision_id'],
            'localized_variant_id' => null,
            'authority_appointment_id' => $authority['english_appointment_id'],
            'authority_appointment_event_id' => $authority['english_appointment_event_id'],
            'reviewer_membership_id' => $authority['english_reviewer_membership_id'],
            'decision' => 'withdrawn',
            'source_content_hash' => $versionOne['source_hash'],
            'localized_content_hash' => null,
            'rationale_key' => 'rollback_target_review_withdrawn',
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $eventCountBefore = DB::table('publication_events')
            ->where('organization_id', $organization->getKey())
            ->where('program_id', $authority['program_id'])
            ->count();
        $lastEventIdBefore = DB::table('publication_heads')
            ->where('organization_id', $organization->getKey())
            ->where('program_id', $authority['program_id'])
            ->value('last_event_id');

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $this->app
                    ->make(CurriculumRollbackService::class)
                    ->rollback(
                        actor: $authority['operator'],
                        programId: $authority['program_id'],
                        targetPublicationId: $publicationOne->publicationId,
                        expectedHeadVersion: 2,
                        idempotencyKey: 'rollback-withdrawn-exact-review',
                        reasonKey: 'withdrawn_evidence_must_fail_closed',
                    ),
            ),
        );

        $this->assertDatabaseHas('publication_content_components', [
            'organization_id' => $organization->getKey(),
            'curriculum_publication_id' => $publicationOne->publicationId,
            'academic_review_id' => $pinnedReviewId,
        ]);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'active_curriculum_version_id' => $versionTwo['version_id'],
            'active_publication_id' => $publicationTwo->publicationId,
            'last_event_id' => $lastEventIdBefore,
            'is_suspended' => false,
            'lock_version' => 2,
        ]);
        self::assertSame(
            $eventCountBefore,
            DB::table('publication_events')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_events')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $authority['program_id'])
                ->where('event_type', 'rollback_activated')
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_commands')
                ->where('organization_id', $organization->getKey())
                ->where('operation', 'curriculum.rollback')
                ->where('aggregate_id', $authority['program_id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('audit_events')
                ->where('organization_id', $organization->getKey())
                ->where('action', 'curriculum.publication.rollback_activated')
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('outbox_events')
                ->where('organization_id', $organization->getKey())
                ->where(
                    'event_type',
                    'curriculum.publication.rollback_activated',
                )
                ->count(),
        );
    }

    /**
     * @return array{
     *   organization_id: string,
     *   operator: User,
     *   operator_membership: Membership,
     *   operator_context: OrganizationContext,
     *   author_membership_id: string,
     *   translator_membership_id: string,
     *   english_reviewer_membership_id: string,
     *   kannada_reviewer_membership_id: string,
     *   program_id: string,
     *   english_appointment_id: string,
     *   english_appointment_event_id: string,
     *   kannada_appointment_id: string,
     *   kannada_appointment_event_id: string
     * }
     */
    private function createPublicationAuthority(
        Organization $organization,
    ): array {
        $operator = $this->createUser();
        [$operatorMembership, $operatorContext] = $this->createMembership(
            $organization,
            $operator,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            membership: $operatorMembership,
            context: $operatorContext,
            permissionKeys: [
                'curriculum.publish',
                'curriculum.share.manage',
                'enrollments.curriculum.assign',
            ],
        );
        $author = $this->createUser();
        [$authorMembership] = $this->createMembership(
            $organization,
            $author,
            Membership::TYPE_TRAINER,
        );
        $translator = $this->createUser();
        [$translatorMembership] = $this->createMembership(
            $organization,
            $translator,
            Membership::TYPE_TRAINER,
        );
        $englishReviewer = $this->createUser();
        [$englishReviewerMembership] = $this->createMembership(
            $organization,
            $englishReviewer,
            Membership::TYPE_TRAINER,
        );
        $kannadaReviewer = $this->createUser();
        [$kannadaReviewerMembership] = $this->createMembership(
            $organization,
            $kannadaReviewer,
            Membership::TYPE_TRAINER,
        );
        $programId = (string) Str::ulid();
        $organizationId = (string) $organization->getKey();
        $operatorMembershipId = (string) $operatorMembership->getKey();
        $now = now();

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => 'publication-'.strtolower((string) Str::ulid()),
            'internal_name' => 'Synthetic publication lifecycle program',
            'created_by_membership_id' => $operatorMembershipId,
            'created_by_attribution' => 'synthetic-test-operator',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        [$englishAppointmentId, $englishAppointmentEventId]
            = $this->insertAcceptedAppointment(
                organizationId: $organizationId,
                programId: $programId,
                appointeeUserId: (string) $englishReviewer->getKey(),
                appointeeMembershipId: (string) $englishReviewerMembership->getKey(),
                appointedByMembershipId: $operatorMembershipId,
                authorityKind: 'english_academic_reviewer',
            );
        [$kannadaAppointmentId, $kannadaAppointmentEventId]
            = $this->insertAcceptedAppointment(
                organizationId: $organizationId,
                programId: $programId,
                appointeeUserId: (string) $kannadaReviewer->getKey(),
                appointeeMembershipId: (string) $kannadaReviewerMembership->getKey(),
                appointedByMembershipId: $operatorMembershipId,
                authorityKind: 'kannada_reviewer',
            );

        return [
            'organization_id' => $organizationId,
            'operator' => $operator,
            'operator_membership' => $operatorMembership,
            'operator_context' => $operatorContext,
            'author_membership_id' => (string) $authorMembership->getKey(),
            'translator_membership_id' => (string) $translatorMembership->getKey(),
            'english_reviewer_membership_id' => (string) $englishReviewerMembership->getKey(),
            'kannada_reviewer_membership_id' => (string) $kannadaReviewerMembership->getKey(),
            'program_id' => $programId,
            'english_appointment_id' => $englishAppointmentId,
            'english_appointment_event_id' => $englishAppointmentEventId,
            'kannada_appointment_id' => $kannadaAppointmentId,
            'kannada_appointment_event_id' => $kannadaAppointmentEventId,
        ];
    }

    /**
     * @param  array{
     *   organization_id: string,
     *   operator: User,
     *   operator_membership: Membership,
     *   operator_context: OrganizationContext,
     *   author_membership_id: string,
     *   translator_membership_id: string,
     *   english_reviewer_membership_id: string,
     *   kannada_reviewer_membership_id: string,
     *   program_id: string,
     *   english_appointment_id: string,
     *   english_appointment_event_id: string,
     *   kannada_appointment_id: string,
     *   kannada_appointment_event_id: string
     * }  $authority
     * @return array{
     *   version_id: string,
     *   objective_id: string,
     *   document_id: string,
     *   source_revision_id: string,
     *   source_hash: string,
     *   variant_id: string,
     *   variant_hash: string,
     *   graph_revision_id: string,
     *   graph_hash: string,
     *   graph_review_id: string
     * }
     */
    private function createReadyVersion(
        array $authority,
        string $versionKey,
        bool $includeKannadaReview = true,
        bool $includeGraphReview = true,
    ): array {
        $versionId = (string) Str::ulid();
        $objectiveId = (string) Str::ulid();
        $moduleId = (string) Str::ulid();
        $lessonId = (string) Str::ulid();
        $documentId = (string) Str::ulid();
        $sourceRevisionId = (string) Str::ulid();
        $variantId = (string) Str::ulid();
        $itemId = (string) Str::ulid();
        $missionId = (string) Str::ulid();
        $sourcePayload = [
            'instruction' => sprintf(
                'Synthetic English instruction for %s.',
                $versionKey,
            ),
        ];
        $variantPayload = [
            'instruction' => sprintf(
                'ಸಿಂಥೆಟಿಕ್ ಕನ್ನಡ ಸೂಚನೆ %s.',
                $versionKey,
            ),
        ];
        $sourceHash = CanonicalJson::sha256($sourcePayload);
        $variantHash = CanonicalJson::sha256($variantPayload);
        $now = now();

        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $authority['organization_id'],
            'program_id' => $authority['program_id'],
            'key' => $versionKey,
            'created_by_membership_id' => $authority['operator_membership']->getKey(),
            'created_by_attribution' => 'synthetic-test-operator',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_objectives')->insert([
            'id' => $objectiveId,
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'key' => 'objective-'.$versionKey,
            'outcome_code' => 'synthetic.'.$versionKey,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_modules')->insert([
            'id' => $moduleId,
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'key' => 'module-'.$versionKey,
            'sequence' => 1,
            'estimated_minutes' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lessons')->insert([
            'id' => $lessonId,
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'curriculum_module_id' => $moduleId,
            'key' => 'lesson-'.$versionKey,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lesson_objectives')->insert([
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'objective_id' => $objectiveId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_documents')->insert([
            'id' => $documentId,
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'key' => 'instruction-'.$versionKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revisions')->insert([
            'id' => $sourceRevisionId,
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'source_locale' => 'en-IN',
            'content_type' => 'instruction',
            'learner_visible' => true,
            'localization_requirement' => 'kn_required',
            'pedagogically_authoritative' => true,
            'payload' => CanonicalJson::encode($sourcePayload),
            'content_hash' => $sourceHash,
            'authored_by_membership_id' => $authority['author_membership_id'],
            'author_attribution' => 'synthetic-test-author',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revision_heads')->insert([
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'content_revision_id' => $sourceRevisionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('localized_variants')->insert([
            'id' => $variantId,
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'source_revision_id' => $sourceRevisionId,
            'parent_variant_id' => null,
            'locale' => 'kn-IN',
            'revision_number' => 1,
            'source_content_hash' => $sourceHash,
            'payload' => CanonicalJson::encode($variantPayload),
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $authority['translator_membership_id'],
            'translator_attribution' => 'synthetic-test-translator',
            'translated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_items')->insert([
            'id' => $itemId,
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'content_document_id' => $documentId,
            'key' => 'item-'.$versionKey,
            'item_type' => 'speaking_prompt',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_missions')->insert([
            'id' => $missionId,
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'instruction_content_document_id' => $documentId,
            'key' => 'mission-'.$versionKey,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('mission_items')->insert([
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'mission_id' => $missionId,
            'learning_item_id' => $itemId,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $graph = $this->app->make(CurriculumGraphProjector::class)->project(
            $authority['organization_id'],
            $versionId,
        );
        $graphRevisionId = (string) Str::ulid();
        $graphReviewId = (string) Str::ulid();
        DB::table('curriculum_graph_revisions')->insert([
            'id' => $graphRevisionId,
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'parent_revision_id' => null,
            'parent_revision_number' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($graph['graph']),
            'graph_hash' => $graph['graph_hash'],
            'authored_by_membership_id' => $authority['author_membership_id'],
            'author_attribution' => 'synthetic-test-author',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_heads')->insert([
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graph['graph_hash'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($includeGraphReview) {
            DB::table('curriculum_graph_reviews')->insert([
                'id' => $graphReviewId,
                'organization_id' => $authority['organization_id'],
                'curriculum_version_id' => $versionId,
                'curriculum_graph_revision_id' => $graphRevisionId,
                'graph_hash' => $graph['graph_hash'],
                'authority_kind' => 'english_academic_reviewer',
                'authority_appointment_id' => $authority['english_appointment_id'],
                'authority_appointment_event_id' => $authority['english_appointment_event_id'],
                'authority_appointment_event_type' => 'accepted',
                'reviewer_membership_id' => $authority['english_reviewer_membership_id'],
                'decision' => 'approved',
                'rationale_key' => null,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('content_reviews')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $versionId,
            'review_kind' => 'academic_en',
            'source_revision_id' => $sourceRevisionId,
            'localized_variant_id' => null,
            'authority_appointment_id' => $authority['english_appointment_id'],
            'authority_appointment_event_id' => $authority['english_appointment_event_id'],
            'reviewer_membership_id' => $authority['english_reviewer_membership_id'],
            'decision' => 'approved',
            'source_content_hash' => $sourceHash,
            'localized_content_hash' => null,
            'rationale_key' => null,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $version = [
            'version_id' => $versionId,
            'objective_id' => $objectiveId,
            'document_id' => $documentId,
            'source_revision_id' => $sourceRevisionId,
            'source_hash' => $sourceHash,
            'variant_id' => $variantId,
            'variant_hash' => $variantHash,
            'graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graph['graph_hash'],
            'graph_review_id' => $graphReviewId,
        ];

        if ($includeKannadaReview) {
            $this->insertKannadaReview($authority, $version);
        }

        return $version;
    }

    /**
     * @param  array{
     *   organization_id: string,
     *   operator: User,
     *   operator_membership: Membership,
     *   operator_context: OrganizationContext,
     *   author_membership_id: string,
     *   translator_membership_id: string,
     *   english_reviewer_membership_id: string,
     *   kannada_reviewer_membership_id: string,
     *   program_id: string,
     *   english_appointment_id: string,
     *   english_appointment_event_id: string,
     *   kannada_appointment_id: string,
     *   kannada_appointment_event_id: string
     * }  $authority
     * @param  array{
     *   version_id: string,
     *   objective_id: string,
     *   document_id: string,
     *   source_revision_id: string,
     *   source_hash: string,
     *   variant_id: string,
     *   variant_hash: string,
     *   graph_revision_id: string,
     *   graph_hash: string,
     *   graph_review_id: string
     * }  $version
     */
    private function insertKannadaReview(
        array $authority,
        array $version,
    ): string {
        $reviewId = (string) Str::ulid();
        $now = now();

        DB::table('content_reviews')->insert([
            'id' => $reviewId,
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $version['version_id'],
            'review_kind' => 'kannada',
            'source_revision_id' => $version['source_revision_id'],
            'localized_variant_id' => $version['variant_id'],
            'authority_appointment_id' => $authority['kannada_appointment_id'],
            'authority_appointment_event_id' => $authority['kannada_appointment_event_id'],
            'reviewer_membership_id' => $authority['kannada_reviewer_membership_id'],
            'decision' => 'approved',
            'source_content_hash' => $version['source_hash'],
            'localized_content_hash' => $version['variant_hash'],
            'rationale_key' => null,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $reviewId;
    }

    /**
     * @return array{string, string}
     */
    private function insertAcceptedAppointment(
        string $organizationId,
        string $programId,
        string $appointeeUserId,
        string $appointeeMembershipId,
        string $appointedByMembershipId,
        string $authorityKind,
    ): array {
        $appointmentId = (string) Str::ulid();
        $appointedEventId = (string) Str::ulid();
        $acceptedEventId = (string) Str::ulid();
        $now = now();

        DB::table('authority_appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $organizationId,
            'appointee_user_id' => $appointeeUserId,
            'appointee_membership_id' => $appointeeMembershipId,
            'authority_kind' => $authorityKind,
            'scope_type' => 'program',
            'program_id' => $programId,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'qualification_basis' => 'Synthetic reviewer qualification fixture',
            'approval_evidence_key' => 'APP-TEST-PUBLICATION',
            'appointed_by_membership_id' => $appointedByMembershipId,
            'appointed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointment_events')->insert([
            [
                'id' => $appointedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'appointed',
                'actor_membership_id' => $appointedByMembershipId,
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => now()->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $acceptedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'accepted',
                'actor_membership_id' => $appointeeMembershipId,
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => now()->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return [$appointmentId, $acceptedEventId];
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function grantPermissions(
        Membership $membership,
        OrganizationContext $context,
        array $permissionKeys,
    ): void {
        $role = $this->createRole(
            $context,
            'publication-'.strtolower((string) Str::ulid()),
        );
        $now = now();

        DB::table('membership_roles')->insert([
            'organization_id' => $membership->organizationId(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($permissionKeys as $permissionKey) {
            $permission = Permission::query()
                ->where('key', $permissionKey)
                ->first();

            if (! $permission instanceof Permission) {
                throw new RuntimeException(sprintf(
                    'Missing synthetic test permission: %s.',
                    $permissionKey,
                ));
            }

            DB::table('role_permissions')->insert([
                'organization_id' => $membership->organizationId(),
                'role_id' => $role->getKey(),
                'permission_id' => $permission->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function createPendingEnrollment(
        Organization $organization,
        OrganizationContext $operatorContext,
    ): string {
        $learner = $this->createUser();
        [$membership] = $this->createMembership(
            $organization,
            $learner,
        );
        $cohort = $this->createCohort(
            $operatorContext,
            'Synthetic publication cohort',
        );
        $enrollmentId = (string) Str::ulid();
        $now = now();

        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $membership->getKey(),
            'cohort_id' => $cohort->getKey(),
            'status' => 'pending_curriculum',
            'enrolled_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $enrollmentId;
    }

    /**
     * @param  array{
     *   organization_id: string,
     *   operator: User,
     *   operator_membership: Membership,
     *   operator_context: OrganizationContext,
     *   author_membership_id: string,
     *   translator_membership_id: string,
     *   english_reviewer_membership_id: string,
     *   kannada_reviewer_membership_id: string,
     *   program_id: string,
     *   english_appointment_id: string,
     *   english_appointment_event_id: string,
     *   kannada_appointment_id: string,
     *   kannada_appointment_event_id: string
     * }  $authority
     * @param  array{
     *   version_id: string,
     *   objective_id: string,
     *   document_id: string,
     *   source_revision_id: string,
     *   source_hash: string,
     *   variant_id: string,
     *   variant_hash: string,
     *   graph_revision_id: string,
     *   graph_hash: string,
     *   graph_review_id: string
     * }  $version
     * @return array{
     *   media_asset_id: string,
     *   rights_revision_id: string,
     *   asset_hash: string,
     *   confirmer_membership_id: string,
     *   appointment_id: string,
     *   appointment_event_id: string
     * }
     */
    private function attachApprovedMediaRights(
        array $authority,
        array $version,
        ?CarbonInterface $expiresAt = null,
    ): array {
        $organization = Organization::query()->findOrFail(
            $authority['organization_id'],
        );
        $confirmer = $this->createUser();
        [$confirmerMembership] = $this->createMembership(
            $organization,
            $confirmer,
            Membership::TYPE_TRAINER,
        );
        [$appointmentId, $appointmentEventId]
            = $this->insertAcceptedAppointment(
                organizationId: $authority['organization_id'],
                programId: $authority['program_id'],
                appointeeUserId: (string) $confirmer->getKey(),
                appointeeMembershipId: (string) $confirmerMembership->getKey(),
                appointedByMembershipId: (string) $authority[
                    'operator_membership'
                ]->getKey(),
                authorityKind: 'rights_confirmer',
            );
        $mediaAssetId = (string) Str::ulid();
        $rightsRevisionId = (string) Str::ulid();
        $assetHash = hash('sha256', 'synthetic-scheduled-audio');
        $evidenceHash = hash('sha256', 'synthetic-scheduled-rights-evidence');
        $now = now();

        DB::table('media_assets')->insert([
            'id' => $mediaAssetId,
            'organization_id' => $authority['organization_id'],
            'key' => 'scheduled-audio-'.strtolower((string) Str::ulid()),
            'asset_kind' => 'instruction_audio',
            'storage_object_key' => 'synthetic/scheduled-audio.ogg',
            'mime_type' => 'audio/ogg',
            'byte_size' => 128,
            'asset_hash' => $assetHash,
            'uploaded_by_membership_id' => $authority['operator_membership']->getKey(),
            'uploader_attribution' => 'synthetic-test-uploader',
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_rights_revisions')->insert([
            'id' => $rightsRevisionId,
            'organization_id' => $authority['organization_id'],
            'program_id' => $authority['program_id'],
            'media_asset_id' => $mediaAssetId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'evidence_status' => 'approved',
            'evidence_hash' => $evidenceHash,
            'selected_asset_hash' => $assetHash,
            'rights_holder_reference' => 'party:SYNTHETIC-RIGHTS-HOLDER',
            'provenance_reference' => 'provenance:SYNTHETIC-AUDIO',
            'rights_basis_key' => 'client_owned',
            'license_or_contract_reference' => 'TEST-RIGHTS-APPROVED',
            'permitted_release_channels' => CanonicalJson::encode([
                'android',
                'web',
            ]),
            'permitted_territories' => CanonicalJson::encode(['IN']),
            'learner_delivery_allowed' => true,
            'sharing_allowed' => true,
            'derivative_use_allowed' => true,
            'effective_at' => now()->subMinute(),
            'expires_at' => $expiresAt ?? now()->addDay(),
            'retention_policy_key' => 'synthetic_test_retention',
            'deletion_constraint_key' => 'synthetic_test_preserve_history',
            'confirmed_by_membership_id' => $confirmerMembership->getKey(),
            'rights_appointment_id' => $appointmentId,
            'rights_appointment_event_id' => $appointmentEventId,
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revision_media_assets')->insert([
            'organization_id' => $authority['organization_id'],
            'curriculum_version_id' => $version['version_id'],
            'content_revision_id' => $version['source_revision_id'],
            'media_asset_id' => $mediaAssetId,
            'usage_key' => 'instruction_audio',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'media_asset_id' => $mediaAssetId,
            'rights_revision_id' => $rightsRevisionId,
            'asset_hash' => $assetHash,
            'confirmer_membership_id' => (string) $confirmerMembership->getKey(),
            'appointment_id' => $appointmentId,
            'appointment_event_id' => $appointmentEventId,
        ];
    }

    private function assertScheduledActivationDidNotAdvance(
        string $organizationId,
        string $programId,
        string $publicationId,
    ): void {
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'active_curriculum_version_id' => null,
            'active_publication_id' => null,
            'lock_version' => 1,
        ]);
        self::assertSame(
            0,
            DB::table('publication_events')
                ->where('organization_id', $organizationId)
                ->where('curriculum_publication_id', $publicationId)
                ->where('event_type', 'activated')
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_commands')
                ->where('organization_id', $organizationId)
                ->where('operation', 'curriculum.activate')
                ->where('aggregate_id', $programId)
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('audit_events')
                ->where('organization_id', $organizationId)
                ->where('action', 'curriculum.publication.activated')
                ->where('subject_id', $publicationId)
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('outbox_events')
                ->where('organization_id', $organizationId)
                ->where('event_type', 'curriculum.publication.activated')
                ->where('aggregate_id', $publicationId)
                ->count(),
        );
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertIdempotencyConflict(callable $operation): void
    {
        try {
            $operation();
        } catch (CurriculumIdempotencyConflict) {
            return;
        }

        self::fail('The curriculum idempotency key should have conflicted.');
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertOperationRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (CurriculumOperationRejected) {
            return;
        }

        self::fail('The curriculum operation should have failed closed.');
    }
}
