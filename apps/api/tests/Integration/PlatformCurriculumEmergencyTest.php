<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Enrollment\EnrollmentCurriculumPinner;
use App\Curriculum\Enrollment\PinnedEnrollmentAccessResolver;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\CurriculumSuspensionService;
use App\Curriculum\Publication\PlatformCurriculumEmergencyService;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\Exceptions\UnauthorizedPlatformOperation;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContext;
use App\Tenancy\PlatformCapability;
use App\Tenancy\PlatformOperationAuthorization;
use App\Tenancy\PlatformOperationAuthorizer;
use App\Tenancy\PlatformOperationExecutor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class PlatformCurriculumEmergencyTest extends TenancyIntegrationTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_named_actor_fresh_mfa_short_lived_signature_and_exact_target_are_required_without_membership_authority(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T15:00:00+00:00');
        $owner = $this->createOrganization('Platform Emergency Target');
        $otherOwner = $this->createOrganization('Other Emergency Target');
        $programId = $this->insertProgram($owner, 'emergency-target');
        $otherProgramId = $this->insertProgram(
            $otherOwner,
            'other-emergency-target',
        );
        $namedActor = $this->createUser();
        $membershipActor = $this->createUser();
        [$namedMembership, $namedMembershipContext] = $this->createMembership(
            $owner,
            $namedActor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermission(
            $namedMembership,
            $namedMembershipContext,
            'curriculum.publish',
        );
        $this->createMembership(
            $owner,
            $membershipActor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        config([
            'platform.content_emergency.actor_user_ids' => [
                (string) $namedActor->getKey(),
            ],
        ]);
        $staleSession = $this->createPrivilegedSession(
            $namedActor,
            now()->subMinutes(16),
        );
        $freshSession = $this->createPrivilegedSession(
            $namedActor,
            now()->subMinute(),
        );
        $membershipSession = $this->createPrivilegedSession(
            $membershipActor,
            now()->subMinute(),
        );
        $authorizer = $this->app->make(
            PlatformOperationAuthorizer::class,
        );

        $this->assertUnauthorizedPlatformOperation(
            fn () => $authorizer->authorizeContentEmergencySuspension(
                targetOrganization: $owner,
                programId: $programId,
                actor: $namedActor,
                authenticationSessionId: (string) $staleSession->getKey(),
                reasonKey: 'content.emergency.safety',
            ),
        );
        $this->assertUnauthorizedPlatformOperation(
            fn () => $authorizer->authorizeContentEmergencySuspension(
                targetOrganization: $owner,
                programId: $programId,
                actor: $membershipActor,
                authenticationSessionId: (string) $membershipSession->getKey(),
                reasonKey: 'content.emergency.safety',
            ),
        );
        $this->assertUnauthorizedPlatformOperation(
            fn () => $authorizer->authorizeContentEmergencySuspension(
                targetOrganization: $owner,
                programId: $programId,
                actor: $namedActor,
                authenticationSessionId: (string) $freshSession->getKey(),
                reasonKey: 'operator-entered-sensitive-description',
            ),
        );
        self::assertDatabaseCount('audit_events', 0);

        $authorization = $authorizer
            ->authorizeContentEmergencySuspension(
                targetOrganization: $owner,
                programId: $programId,
                actor: $namedActor,
                authenticationSessionId: (string) $freshSession->getKey(),
                reasonKey: 'content.emergency.safety',
            );
        $contextAuthority = $this->app
            ->make(PlatformOperationExecutor::class)
            ->run(
                $authorization,
                fn (): array => [
                    $this->contexts()->current()->membershipId,
                    $this->app->make(OrganizationAuthorization::class)->allows(
                        $namedActor,
                        'curriculum.publish',
                    ),
                ],
            );

        self::assertSame([null, false], $contextAuthority);
        self::assertDatabaseHas('audit_events', [
            'scope_kind' => 'platform',
            'target_organization_id' => $owner->getKey(),
            'actor_user_id' => $namedActor->getKey(),
            'actor_membership_id' => null,
            'capability_key' => PlatformCapability::ContentEmergencySuspend->value,
            'reason_key' => 'content.emergency.safety',
            'action' => 'curriculum.publication.suspended',
            'subject_type' => 'program',
            'subject_id' => $programId,
            'correlation_id' => $authorization->correlationId,
            'authorization_nonce' => $authorization->nonce,
        ]);

        $forgedTarget = new PlatformOperationAuthorization(
            capability: $authorization->capability,
            reasonKey: $authorization->reasonKey,
            targetOrganizationId: (string) $otherOwner->getKey(),
            actorUserId: $authorization->actorUserId,
            action: $authorization->action,
            subjectType: $authorization->subjectType,
            subjectId: $otherProgramId,
            correlationId: $authorization->correlationId,
            issuedAtEpoch: $authorization->issuedAtEpoch,
            nonce: $authorization->nonce,
            signature: $authorization->signature,
            authenticationSessionId: $authorization
                ->authenticationSessionId,
        );
        $this->assertUnauthorizedPlatformOperation(
            fn () => $this->app
                ->make(PlatformCurriculumEmergencyService::class)
                ->suspend(
                    $forgedTarget,
                    0,
                    'forged-cross-target',
                    'runbook.content-emergency.v1',
                ),
        );

        CarbonImmutable::setTestNow(now()->addSeconds(301));
        $this->assertUnauthorizedPlatformOperation(
            fn () => $this->app
                ->make(PlatformCurriculumEmergencyService::class)
                ->suspend(
                    $authorization,
                    0,
                    'expired-authorization',
                    'runbook.content-emergency.v1',
                ),
        );
        self::assertSame(1, DB::table('audit_events')->count());
        self::assertDatabaseCount('publication_events', 0);
    }

    public function test_platform_suspend_and_resume_are_append_only_idempotent_and_stop_new_and_existing_delivery(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T16:00:00+00:00');
        $fixture = $this->createPublishedFixture();
        $platformActor = $this->createUser();
        $session = $this->createPrivilegedSession(
            $platformActor,
            now()->subMinute(),
        );
        config([
            'platform.content_emergency.actor_user_ids' => [
                (string) $platformActor->getKey(),
            ],
        ]);
        $authorizer = $this->app->make(
            PlatformOperationAuthorizer::class,
        );
        $emergencies = $this->app->make(
            PlatformCurriculumEmergencyService::class,
        );
        $access = $this->app->make(PinnedEnrollmentAccessResolver::class);
        $pinner = $this->app->make(EnrollmentCurriculumPinner::class);

        $before = $this->contexts()->run(
            $fixture['learner_context'],
            fn () => $access->resolve(
                $fixture['learner'],
                $fixture['active_enrollment_id'],
            ),
        );
        self::assertSame(
            $fixture['publication_id'],
            $before->publication->publicationId,
        );

        $suspendAuthorization = $authorizer
            ->authorizeContentEmergencySuspension(
                targetOrganization: $fixture['organization'],
                programId: $fixture['program_id'],
                actor: $platformActor,
                authenticationSessionId: (string) $session->getKey(),
                reasonKey: 'content.emergency.legal',
            );
        $suspended = $emergencies->suspend(
            $suspendAuthorization,
            1,
            'platform-suspend-release',
            'runbook.legal-suspension.v1',
        );
        $replayed = $emergencies->suspend(
            $suspendAuthorization,
            1,
            'platform-suspend-release',
            'runbook.legal-suspension.v1',
        );

        self::assertTrue($suspended->suspended);
        self::assertFalse($suspended->replayed);
        self::assertTrue($replayed->replayed);
        self::assertSame(2, $suspended->headVersion);
        self::assertSame(2, $replayed->headVersion);
        self::assertSame(
            1,
            DB::table('audit_events')
                ->where('scope_kind', 'platform')
                ->where(
                    'authorization_nonce',
                    $suspendAuthorization->nonce,
                )
                ->count(),
        );
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'active_publication_id' => $fixture['publication_id'],
            'is_suspended' => true,
            'lock_version' => 2,
        ]);
        $this->assertDatabaseHas('publication_events', [
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'curriculum_publication_id' => $fixture['publication_id'],
            'event_type' => 'suspended',
            'actor_type' => 'platform_user',
            'actor_membership_id' => null,
            'platform_actor_user_id' => $platformActor->getKey(),
            'authentication_session_id' => $session->getKey(),
            'reason_key' => 'content.emergency.legal',
            'remediation_reference' => 'runbook.legal-suspension.v1',
            'correlation_id' => $suspendAuthorization->correlationId,
        ]);
        self::assertNotNull(
            DB::table('publication_events')
                ->where('organization_id', $fixture['organization']->getKey())
                ->where('program_id', $fixture['program_id'])
                ->where('event_type', 'suspended')
                ->value('platform_audit_event_id'),
        );
        self::assertSame(
            1,
            DB::table('publication_events')
                ->where('organization_id', $fixture['organization']->getKey())
                ->where('program_id', $fixture['program_id'])
                ->where('event_type', 'suspended')
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('publication_commands')
                ->where('organization_id', $fixture['organization']->getKey())
                ->where(
                    'operation',
                    'curriculum.platform_emergency.suspend',
                )
                ->where('aggregate_id', $fixture['program_id'])
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('outbox_events')
                ->where('organization_id', $fixture['organization']->getKey())
                ->where('event_type', 'curriculum.publication.suspended')
                ->where('aggregate_id', $fixture['publication_id'])
                ->count(),
        );
        $this->assertCurriculumOperationRejected(
            fn () => $this->contexts()->run(
                $fixture['learner_context'],
                fn () => $access->resolve(
                    $fixture['learner'],
                    $fixture['active_enrollment_id'],
                ),
            ),
        );
        $this->assertCurriculumOperationRejected(
            fn () => $this->contexts()->run(
                $fixture['operator_context'],
                fn () => $pinner->activate(
                    actor: $fixture['operator'],
                    enrollmentId: $fixture['pending_enrollment_id'],
                    publicationId: $fixture['publication_id'],
                    shareGrantId: null,
                    idempotencyKey: 'blocked-new-enrollment',
                ),
            ),
        );

        $resumeAuthorization = $authorizer
            ->authorizeContentEmergencyResumption(
                targetOrganization: $fixture['organization'],
                programId: $fixture['program_id'],
                actor: $platformActor,
                authenticationSessionId: (string) $session->getKey(),
                reasonKey: 'content.emergency.legal_resolved',
            );
        $resumed = $emergencies->resume(
            $resumeAuthorization,
            2,
            'platform-resume-release',
            'runbook.legal-resumption.v1',
        );

        self::assertFalse($resumed->suspended);
        self::assertSame(3, $resumed->headVersion);
        $this->assertUnauthorizedPlatformOperation(
            fn () => $emergencies->suspend(
                $suspendAuthorization,
                3,
                'reused-platform-authorization',
                'runbook.legal-suspension.v1',
            ),
        );
        $this->assertDatabaseHas('publication_events', [
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'event_type' => 'resumed',
            'actor_type' => 'platform_user',
            'platform_actor_user_id' => $platformActor->getKey(),
            'authentication_session_id' => $session->getKey(),
            'reason_key' => 'content.emergency.legal_resolved',
            'remediation_reference' => 'runbook.legal-resumption.v1',
            'correlation_id' => $resumeAuthorization->correlationId,
        ]);
        self::assertSame(
            1,
            DB::table('audit_events')
                ->where('scope_kind', 'platform')
                ->where(
                    'authorization_nonce',
                    $suspendAuthorization->nonce,
                )
                ->count(),
        );
        $after = $this->contexts()->run(
            $fixture['learner_context'],
            fn () => $access->resolve(
                $fixture['learner'],
                $fixture['active_enrollment_id'],
            ),
        );
        self::assertSame(
            $fixture['publication_id'],
            $after->publication->publicationId,
        );
    }

    public function test_platform_resume_revalidates_exact_publication_evidence_and_rolls_back_a_failed_attempt(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T17:00:00+00:00');
        $fixture = $this->createPublishedFixture();
        $platformActor = $this->createUser();
        $session = $this->createPrivilegedSession(
            $platformActor,
            now()->subMinute(),
        );
        config([
            'platform.content_emergency.actor_user_ids' => [
                (string) $platformActor->getKey(),
            ],
        ]);
        $authorizer = $this->app->make(
            PlatformOperationAuthorizer::class,
        );
        $emergencies = $this->app->make(
            PlatformCurriculumEmergencyService::class,
        );
        $suspendAuthorization = $authorizer
            ->authorizeContentEmergencySuspension(
                targetOrganization: $fixture['organization'],
                programId: $fixture['program_id'],
                actor: $platformActor,
                authenticationSessionId: (string) $session->getKey(),
                reasonKey: 'content.emergency.safety',
            );
        $emergencies->suspend(
            $suspendAuthorization,
            1,
            'suspend-before-review-invalidation',
            'runbook.safety-remediation.v1',
        );

        CarbonImmutable::setTestNow(now()->addSecond());
        DB::table('curriculum_graph_reviews')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $fixture['organization']->getKey(),
            'curriculum_version_id' => $fixture['curriculum_version_id'],
            'curriculum_graph_revision_id' => $fixture['graph_revision_id'],
            'graph_hash' => $fixture['graph_hash'],
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $fixture['appointment_id'],
            'authority_appointment_event_id' => $fixture[
                'appointment_event_id'
            ],
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $fixture[
                'reviewer_membership_id'
            ],
            'decision' => 'rejected',
            'rationale_key' => 'content.graph.invalidated',
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $resumeAuthorization = $authorizer
            ->authorizeContentEmergencyResumption(
                targetOrganization: $fixture['organization'],
                programId: $fixture['program_id'],
                actor: $platformActor,
                authenticationSessionId: (string) $session->getKey(),
                reasonKey: 'content.emergency.safety_remediated',
            );

        $this->assertCurriculumOperationRejected(
            fn () => $emergencies->resume(
                $resumeAuthorization,
                2,
                'resume-with-invalid-evidence',
                'runbook.safety-remediation.v1',
            ),
        );

        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'is_suspended' => true,
            'lock_version' => 2,
        ]);
        self::assertSame(
            0,
            DB::table('publication_events')
                ->where('organization_id', $fixture['organization']->getKey())
                ->where('program_id', $fixture['program_id'])
                ->where('event_type', 'resumed')
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_commands')
                ->where('organization_id', $fixture['organization']->getKey())
                ->where(
                    'operation',
                    'curriculum.platform_emergency.resume',
                )
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('audit_events')
                ->where('scope_kind', 'platform')
                ->where('action', 'curriculum.publication.resumed')
                ->where('subject_id', $fixture['program_id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('outbox_events')
                ->where('organization_id', $fixture['organization']->getKey())
                ->where('event_type', 'curriculum.publication.resumed')
                ->count(),
        );
    }

    public function test_platform_emergency_suspends_and_resumes_existing_pins_after_retirement_without_reactivating_the_release(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T18:00:00+00:00');
        $fixture = $this->createPublishedFixture();
        $retiredEventId = (string) Str::ulid();
        DB::table('publication_events')->insert([
            'id' => $retiredEventId,
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'curriculum_version_id' => $fixture['curriculum_version_id'],
            'curriculum_publication_id' => $fixture['publication_id'],
            'event_type' => 'retired',
            'previous_curriculum_version_id' => null,
            'previous_publication_id' => null,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $fixture['operator_membership_id'],
            'platform_actor_user_id' => null,
            'reason_key' => 'synthetic_retirement',
            'correlation_id' => (string) Str::uuid(),
            'effective_at' => now(),
            'created_at' => now(),
        ]);
        DB::table('publication_heads')
            ->where('organization_id', $fixture['organization']->getKey())
            ->where('program_id', $fixture['program_id'])
            ->update([
                'active_curriculum_version_id' => null,
                'active_publication_id' => null,
                'last_event_id' => $retiredEventId,
                'is_suspended' => false,
                'lock_version' => 2,
                'updated_at' => now(),
            ]);
        $access = $this->app->make(PinnedEnrollmentAccessResolver::class);
        $before = $this->contexts()->run(
            $fixture['learner_context'],
            fn () => $access->resolve(
                $fixture['learner'],
                $fixture['active_enrollment_id'],
            ),
        );
        self::assertSame(
            $fixture['publication_id'],
            $before->publication->publicationId,
        );

        $platformActor = $this->createUser();
        $session = $this->createPrivilegedSession(
            $platformActor,
            now()->subMinute(),
        );
        config([
            'platform.content_emergency.actor_user_ids' => [
                (string) $platformActor->getKey(),
            ],
        ]);
        $authorizer = $this->app->make(
            PlatformOperationAuthorizer::class,
        );
        $emergencies = $this->app->make(
            PlatformCurriculumEmergencyService::class,
        );
        $suspended = $emergencies->suspend(
            $authorizer->authorizeContentEmergencySuspension(
                targetOrganization: $fixture['organization'],
                programId: $fixture['program_id'],
                actor: $platformActor,
                authenticationSessionId: (string) $session->getKey(),
                reasonKey: 'content.emergency.rights',
            ),
            2,
            'suspend-retired-pins',
            'runbook.rights-suspension.v1',
        );

        self::assertTrue($suspended->suspended);
        self::assertSame($fixture['publication_id'], $suspended->publicationId);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'active_curriculum_version_id' => null,
            'active_publication_id' => null,
            'is_suspended' => true,
            'lock_version' => 3,
        ]);
        $this->assertCurriculumOperationRejected(
            fn () => $this->contexts()->run(
                $fixture['learner_context'],
                fn () => $access->resolve(
                    $fixture['learner'],
                    $fixture['active_enrollment_id'],
                ),
            ),
        );

        $resumed = $emergencies->resume(
            $authorizer->authorizeContentEmergencyResumption(
                targetOrganization: $fixture['organization'],
                programId: $fixture['program_id'],
                actor: $platformActor,
                authenticationSessionId: (string) $session->getKey(),
                reasonKey: 'content.emergency.rights_restored',
            ),
            3,
            'resume-retired-pins',
            'runbook.rights-restoration.v1',
        );

        self::assertFalse($resumed->suspended);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'active_curriculum_version_id' => null,
            'active_publication_id' => null,
            'is_suspended' => false,
            'lock_version' => 4,
        ]);
        $after = $this->contexts()->run(
            $fixture['learner_context'],
            fn () => $access->resolve(
                $fixture['learner'],
                $fixture['active_enrollment_id'],
            ),
        );
        self::assertSame(
            $fixture['publication_id'],
            $after->publication->publicationId,
        );
    }

    public function test_owner_emergency_requires_permission_and_fresh_mfa_and_covers_retired_pins(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T19:00:00+00:00');
        $fixture = $this->createPublishedFixture();
        $operatorMembership = $this->contexts()->run(
            $fixture['operator_context'],
            fn () => Membership::query()
                ->find($fixture['operator_membership_id']),
        );

        if (! $operatorMembership instanceof Membership) {
            self::fail('Missing synthetic owner membership.');
        }

        $this->grantPermission(
            $operatorMembership,
            $fixture['operator_context'],
            'curriculum.suspend',
        );
        $staleSession = $this->createPrivilegedSession(
            $fixture['operator'],
            now()->subMinutes(16),
        );
        $freshSession = $this->createPrivilegedSession(
            $fixture['operator'],
            now()->subMinute(),
        );
        $emergencies = $this->app->make(CurriculumSuspensionService::class);

        $this->assertCurriculumOperationRejected(
            fn () => $this->contexts()->run(
                $fixture['operator_context'],
                fn () => $emergencies->suspend(
                    actor: $fixture['operator'],
                    programId: $fixture['program_id'],
                    expectedHeadVersion: 1,
                    authenticationSessionId: (string) $staleSession->getKey(),
                    idempotencyKey: 'owner-stale-mfa',
                    reasonKey: 'content_emergency_safety',
                    remediationReference: 'runbook.safety-remediation.v1',
                ),
            ),
        );

        $retiredEventId = (string) Str::ulid();
        DB::table('publication_events')->insert([
            'id' => $retiredEventId,
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'curriculum_version_id' => $fixture['curriculum_version_id'],
            'curriculum_publication_id' => $fixture['publication_id'],
            'event_type' => 'retired',
            'previous_curriculum_version_id' => null,
            'previous_publication_id' => null,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $fixture['operator_membership_id'],
            'platform_actor_user_id' => null,
            'reason_key' => 'synthetic_retirement',
            'correlation_id' => (string) Str::uuid(),
            'effective_at' => now(),
            'created_at' => now(),
        ]);
        DB::table('publication_heads')
            ->where('organization_id', $fixture['organization']->getKey())
            ->where('program_id', $fixture['program_id'])
            ->update([
                'active_curriculum_version_id' => null,
                'active_publication_id' => null,
                'last_event_id' => $retiredEventId,
                'is_suspended' => false,
                'lock_version' => 2,
                'updated_at' => now(),
            ]);

        $suspended = $this->contexts()->run(
            $fixture['operator_context'],
            fn () => $emergencies->suspend(
                actor: $fixture['operator'],
                programId: $fixture['program_id'],
                expectedHeadVersion: 2,
                authenticationSessionId: (string) $freshSession->getKey(),
                idempotencyKey: 'owner-suspend-retired-pins',
                reasonKey: 'content_emergency_safety',
                remediationReference: 'runbook.safety-remediation.v1',
            ),
        );
        $replayed = $this->contexts()->run(
            $fixture['operator_context'],
            fn () => $emergencies->suspend(
                actor: $fixture['operator'],
                programId: $fixture['program_id'],
                expectedHeadVersion: 2,
                authenticationSessionId: (string) $freshSession->getKey(),
                idempotencyKey: 'owner-suspend-retired-pins',
                reasonKey: 'content_emergency_safety',
                remediationReference: 'runbook.safety-remediation.v1',
            ),
        );

        self::assertTrue($suspended->suspended);
        self::assertFalse($suspended->replayed);
        self::assertTrue($replayed->replayed);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'active_curriculum_version_id' => null,
            'active_publication_id' => null,
            'is_suspended' => true,
            'lock_version' => 3,
        ]);
        $this->assertDatabaseHas('publication_events', [
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'event_type' => 'suspended',
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $fixture['operator_membership_id'],
            'platform_actor_user_id' => null,
            'authentication_session_id' => $freshSession->getKey(),
            'reason_key' => 'content_emergency_safety',
            'remediation_reference' => 'runbook.safety-remediation.v1',
            'platform_audit_event_id' => null,
        ]);

        $resumed = $this->contexts()->run(
            $fixture['operator_context'],
            fn () => $emergencies->resume(
                actor: $fixture['operator'],
                programId: $fixture['program_id'],
                expectedHeadVersion: 3,
                authenticationSessionId: (string) $freshSession->getKey(),
                idempotencyKey: 'owner-resume-retired-pins',
                reasonKey: 'content_emergency_safety_resolved',
                remediationReference: 'runbook.safety-resumption.v1',
            ),
        );

        self::assertFalse($resumed->suspended);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $fixture['organization']->getKey(),
            'program_id' => $fixture['program_id'],
            'active_curriculum_version_id' => null,
            'active_publication_id' => null,
            'is_suspended' => false,
            'lock_version' => 4,
        ]);
    }

    private function insertProgram(
        Organization $organization,
        string $key,
        ?string $creatorMembershipId = null,
    ): string {
        $programId = (string) Str::ulid();

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organization->getKey(),
            'key' => $key,
            'internal_name' => Str::headline($key),
            'created_by_membership_id' => $creatorMembershipId,
            'created_by_attribution' => 'synthetic-platform-emergency-test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $programId;
    }

    private function createPrivilegedSession(
        User $user,
        CarbonInterface $mfaVerifiedAt,
    ): AuthenticationSession {
        return AuthenticationSession::query()->forceCreate([
            'user_id' => $user->getKey(),
            'storage_session_digest' => hash(
                'sha256',
                (string) Str::uuid(),
            ),
            'storage_session_ciphertext' => 'synthetic-test-session',
            'is_privileged' => true,
            'started_at' => now()->subHour(),
            'last_seen_at' => now(),
            'idle_expires_at' => now()->addMinutes(30),
            'absolute_expires_at' => now()->addHours(6),
            'mfa_verified_at' => $mfaVerifiedAt,
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);
    }

    /**
     * @return array{
     *   organization: Organization,
     *   program_id: string,
     *   curriculum_version_id: string,
     *   publication_id: string,
     *   graph_revision_id: string,
     *   graph_hash: string,
     *   appointment_id: string,
     *   appointment_event_id: string,
     *   reviewer_membership_id: string,
     *   operator: User,
     *   operator_membership_id: string,
     *   operator_context: OrganizationContext,
     *   learner: User,
     *   learner_context: OrganizationContext,
     *   active_enrollment_id: string,
     *   pending_enrollment_id: string
     * }
     */
    private function createPublishedFixture(): array
    {
        $organization = $this->createOrganization(
            'Published Platform Emergency Tenant',
        );
        $operator = $this->createUser();
        [$operatorMembership, $operatorContext] = $this->createMembership(
            $organization,
            $operator,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $reviewer = $this->createUser();
        [$reviewerMembership] = $this->createMembership(
            $organization,
            $reviewer,
            Membership::TYPE_TRAINER,
        );
        $programId = $this->insertProgram(
            $organization,
            'published-emergency-program',
            (string) $operatorMembership->getKey(),
        );
        $curriculumVersionId = (string) Str::ulid();
        $now = now();

        DB::table('curriculum_versions')->insert([
            'id' => $curriculumVersionId,
            'organization_id' => $organization->getKey(),
            'program_id' => $programId,
            'key' => 'published-v1',
            'created_by_membership_id' => $operatorMembership->getKey(),
            'created_by_attribution' => 'synthetic-platform-emergency-test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $appointmentId = (string) Str::ulid();
        $appointmentEventId = (string) Str::ulid();
        DB::table('authority_appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $organization->getKey(),
            'appointee_user_id' => $reviewer->getKey(),
            'appointee_membership_id' => $reviewerMembership->getKey(),
            'authority_kind' => 'english_academic_reviewer',
            'scope_type' => 'program',
            'program_id' => $programId,
            'valid_from' => $now->copy()->subDay(),
            'valid_until' => $now->copy()->addMonth(),
            'qualification_basis' => 'Synthetic test qualification',
            'approval_evidence_key' => 'APP-TEST-PLATFORM-EMERGENCY',
            'appointed_by_membership_id' => $operatorMembership->getKey(),
            'appointed_at' => $now->copy()->subMinutes(5),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointment_events')->insert([
            'id' => $appointmentEventId,
            'organization_id' => $organization->getKey(),
            'authority_appointment_id' => $appointmentId,
            'event_type' => 'accepted',
            'actor_membership_id' => $reviewerMembership->getKey(),
            'reason_key' => null,
            'invalidates_prior_approvals' => false,
            'effective_at' => $now->copy()->subMinutes(4),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $graph = [
            'curriculum_version_id' => $curriculumVersionId,
            'fixture_notice' => 'Synthetic empty graph for emergency tests.',
        ];
        $graphHash = CanonicalJson::sha256($graph);
        $graphRevisionId = (string) Str::ulid();
        DB::table('curriculum_graph_revisions')->insert([
            'id' => $graphRevisionId,
            'organization_id' => $organization->getKey(),
            'curriculum_version_id' => $curriculumVersionId,
            'parent_revision_id' => null,
            'parent_revision_number' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($graph),
            'graph_hash' => $graphHash,
            'authored_by_membership_id' => $operatorMembership->getKey(),
            'author_attribution' => 'synthetic-platform-emergency-test',
            'authored_at' => $now->copy()->subMinutes(3),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_heads')->insert([
            'organization_id' => $organization->getKey(),
            'curriculum_version_id' => $curriculumVersionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graphHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $graphReviewId = (string) Str::ulid();
        DB::table('curriculum_graph_reviews')->insert([
            'id' => $graphReviewId,
            'organization_id' => $organization->getKey(),
            'curriculum_version_id' => $curriculumVersionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graphHash,
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $appointmentId,
            'authority_appointment_event_id' => $appointmentEventId,
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $reviewerMembership->getKey(),
            'decision' => 'approved',
            'rationale_key' => null,
            'reviewed_at' => $now->copy()->subMinutes(2),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $manifest = [
            'schema_version' => 1,
            'organization_id' => (string) $organization->getKey(),
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'graph_revision' => [
                'id' => $graphRevisionId,
                'graph_hash' => $graphHash,
                'graph' => $graph,
                'review' => [
                    'id' => $graphReviewId,
                    'appointment_id' => $appointmentId,
                    'appointment_event_id' => $appointmentEventId,
                    'reviewer_membership_id' => (string) $reviewerMembership
                        ->getKey(),
                ],
            ],
            'content' => [],
        ];
        $publicationId = (string) Str::ulid();
        DB::table('curriculum_publications')->insert([
            'id' => $publicationId,
            'organization_id' => $organization->getKey(),
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'publication_number' => 1,
            'manifest_schema_version' => 1,
            'manifest' => CanonicalJson::encode($manifest),
            'manifest_hash' => CanonicalJson::sha256($manifest),
            'created_by_membership_id' => $operatorMembership->getKey(),
            'created_at' => $now,
        ]);
        DB::table('publication_graph_components')->insert([
            'organization_id' => $organization->getKey(),
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'curriculum_publication_id' => $publicationId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graphHash,
            'curriculum_graph_review_id' => $graphReviewId,
            'graph_review_decision' => 'approved',
            'academic_appointment_id' => $appointmentId,
            'academic_appointment_event_id' => $appointmentEventId,
            'reviewer_membership_id' => $reviewerMembership->getKey(),
            'created_at' => $now,
        ]);
        $activatedEventId = (string) Str::ulid();
        DB::table('publication_events')->insert([
            'id' => $activatedEventId,
            'organization_id' => $organization->getKey(),
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'curriculum_publication_id' => $publicationId,
            'event_type' => 'activated',
            'previous_curriculum_version_id' => null,
            'previous_publication_id' => null,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $operatorMembership->getKey(),
            'platform_actor_user_id' => null,
            'reason_key' => 'initial_publish',
            'correlation_id' => (string) Str::uuid(),
            'effective_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('publication_heads')->insert([
            'organization_id' => $organization->getKey(),
            'program_id' => $programId,
            'active_curriculum_version_id' => $curriculumVersionId,
            'active_publication_id' => $publicationId,
            'last_event_id' => $activatedEventId,
            'is_suspended' => false,
            'lock_version' => 1,
            'updated_at' => $now,
        ]);

        $this->grantPermission(
            $operatorMembership,
            $operatorContext,
            'enrollments.curriculum.assign',
        );
        $learner = $this->createUser();
        [$learnerMembership, $learnerContext] = $this->createMembership(
            $organization,
            $learner,
        );
        $pendingLearner = $this->createUser();
        [$pendingMembership] = $this->createMembership(
            $organization,
            $pendingLearner,
        );
        $cohort = $this->createCohort(
            $operatorContext,
            'Platform emergency test cohort',
        );
        $activeEnrollmentId = (string) Str::ulid();
        $pendingEnrollmentId = (string) Str::ulid();
        DB::table('enrollments')->insert([
            [
                'id' => $activeEnrollmentId,
                'organization_id' => $organization->getKey(),
                'learner_membership_id' => $learnerMembership->getKey(),
                'cohort_id' => $cohort->getKey(),
                'curriculum_owner_organization_id' => $organization->getKey(),
                'curriculum_version_id' => $curriculumVersionId,
                'curriculum_publication_id' => $publicationId,
                'curriculum_share_grant_id' => null,
                'status' => 'active',
                'enrolled_at' => $now,
                'activated_at' => $now,
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $pendingEnrollmentId,
                'organization_id' => $organization->getKey(),
                'learner_membership_id' => $pendingMembership->getKey(),
                'cohort_id' => $cohort->getKey(),
                'curriculum_owner_organization_id' => null,
                'curriculum_version_id' => null,
                'curriculum_publication_id' => null,
                'curriculum_share_grant_id' => null,
                'status' => 'pending_curriculum',
                'enrolled_at' => $now,
                'activated_at' => null,
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return [
            'organization' => $organization,
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'publication_id' => $publicationId,
            'graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graphHash,
            'appointment_id' => $appointmentId,
            'appointment_event_id' => $appointmentEventId,
            'reviewer_membership_id' => (string) $reviewerMembership->getKey(),
            'operator' => $operator,
            'operator_membership_id' => (string) $operatorMembership->getKey(),
            'operator_context' => $operatorContext,
            'learner' => $learner,
            'learner_context' => $learnerContext,
            'active_enrollment_id' => $activeEnrollmentId,
            'pending_enrollment_id' => $pendingEnrollmentId,
        ];
    }

    private function grantPermission(
        Membership $membership,
        OrganizationContext $context,
        string $permissionKey,
    ): void {
        $role = $this->createRole(
            $context,
            'platform-emergency-'.strtolower((string) Str::ulid()),
        );
        $permission = Permission::query()
            ->where('key', $permissionKey)
            ->first();

        if (! $permission instanceof Permission) {
            throw new RuntimeException('Missing synthetic test permission.');
        }

        DB::table('membership_roles')->insert([
            'organization_id' => $membership->organizationId(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $membership->organizationId(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertUnauthorizedPlatformOperation(
        callable $operation,
    ): void {
        try {
            $operation();
        } catch (UnauthorizedPlatformOperation) {
            return;
        }

        self::fail('The platform operation should have failed closed.');
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertCurriculumOperationRejected(
        callable $operation,
    ): void {
        try {
            $operation();
        } catch (CurriculumOperationRejected) {
            return;
        }

        self::fail('The curriculum operation should have failed closed.');
    }
}
