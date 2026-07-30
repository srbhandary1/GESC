<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\AuthorityAppointmentCommands;
use App\Curriculum\Governance\AuthorityEvidenceValidator;
use App\Curriculum\Governance\AuthorityKind;
use App\Curriculum\Governance\ContentReviewCommands;
use App\Curriculum\Governance\CurriculumGraphGovernanceCommands;
use App\Curriculum\Governance\MediaRightsConfirmationCommands;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CurriculumGovernanceCommandsTest extends TenancyIntegrationTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_appointment_requires_manager_authority_and_self_appointment_requires_trusted_consolidated_evidence(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');
        $organization = $this->createOrganization('Appointment Tenant');
        $manager = $this->createUser();
        [$managerMembership, $managerContext] = $this->createMembership(
            $organization,
            $manager,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $reviewer = $this->createUser();
        [$reviewerMembership, $reviewerContext] = $this->createMembership(
            $organization,
            $reviewer,
            Membership::TYPE_TRAINER,
        );
        $commands = $this->app->make(AuthorityAppointmentCommands::class);

        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $managerContext,
                fn (): string => $commands->appoint(
                    actor: $manager,
                    appointeeUserId: (string) $reviewer->getKey(),
                    appointeeMembershipId: (string) $reviewerMembership->getKey(),
                    authorityKind: AuthorityKind::EnglishAcademicReviewer,
                    scopeType: 'organization',
                    programId: null,
                    validFrom: now(),
                    validUntil: now()->addMonth(),
                    qualificationBasis: 'Synthetic academic reviewer fixture',
                    approvalEvidenceKey: 'APP-TEST-0001',
                    idempotencyKey: 'appoint-without-permission',
                ),
            ),
        );
        $this->assertDatabaseCount('authority_appointments', 0);

        $this->grantPermission(
            $managerMembership,
            $managerContext,
            'curriculum.authority.manage',
        );
        $appointmentId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->appoint(
                actor: $manager,
                appointeeUserId: (string) $reviewer->getKey(),
                appointeeMembershipId: (string) $reviewerMembership->getKey(),
                authorityKind: AuthorityKind::EnglishAcademicReviewer,
                scopeType: 'organization',
                programId: null,
                validFrom: now(),
                validUntil: now()->addMonth(),
                qualificationBasis: 'Synthetic academic reviewer fixture',
                approvalEvidenceKey: 'APP-TEST-0001',
                idempotencyKey: 'appoint-academic-reviewer',
            ),
        );
        $replayedAppointmentId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->appoint(
                actor: $manager,
                appointeeUserId: (string) $reviewer->getKey(),
                appointeeMembershipId: (string) $reviewerMembership->getKey(),
                authorityKind: AuthorityKind::EnglishAcademicReviewer,
                scopeType: 'organization',
                programId: null,
                validFrom: now(),
                validUntil: now()->addMonth(),
                qualificationBasis: 'Synthetic academic reviewer fixture',
                approvalEvidenceKey: 'APP-TEST-0001',
                idempotencyKey: 'appoint-academic-reviewer',
            ),
        );
        self::assertSame($appointmentId, $replayedAppointmentId);
        $this->assertIdempotencyConflict(
            fn (): string => $this->contexts()->run(
                $managerContext,
                fn (): string => $commands->appoint(
                    actor: $manager,
                    appointeeUserId: (string) $reviewer->getKey(),
                    appointeeMembershipId: (string) $reviewerMembership->getKey(),
                    authorityKind: AuthorityKind::EnglishAcademicReviewer,
                    scopeType: 'organization',
                    programId: null,
                    validFrom: now(),
                    validUntil: now()->addMonth(),
                    qualificationBasis: 'Conflicting synthetic basis',
                    approvalEvidenceKey: 'APP-TEST-0001',
                    idempotencyKey: 'appoint-academic-reviewer',
                ),
            ),
        );
        $acceptanceEventId = $this->contexts()->run(
            $reviewerContext,
            fn (): string => $commands->accept(
                $reviewer,
                $appointmentId,
                'accept-academic-reviewer',
            ),
        );
        $replayedAcceptanceEventId = $this->contexts()->run(
            $reviewerContext,
            fn (): string => $commands->accept(
                $reviewer,
                $appointmentId,
                'accept-academic-reviewer',
            ),
        );
        self::assertSame($acceptanceEventId, $replayedAcceptanceEventId);

        $this->assertDatabaseHas('authority_appointments', [
            'id' => $appointmentId,
            'organization_id' => $organization->getKey(),
            'appointee_user_id' => $reviewer->getKey(),
            'appointee_membership_id' => $reviewerMembership->getKey(),
            'authority_kind' => AuthorityKind::EnglishAcademicReviewer->value,
            'scope_type' => 'organization',
            'program_id' => null,
            'appointed_by_membership_id' => $managerMembership->getKey(),
        ]);
        $this->assertDatabaseHas('authority_appointment_events', [
            'id' => $acceptanceEventId,
            'authority_appointment_id' => $appointmentId,
            'event_type' => 'accepted',
            'actor_membership_id' => $reviewerMembership->getKey(),
            'invalidates_prior_approvals' => false,
        ]);
        $this->assertSame(
            2,
            DB::table('audit_events')
                ->where('subject_id', $appointmentId)
                ->count(),
        );
        $this->assertSame(
            2,
            DB::table('outbox_events')
                ->where('aggregate_id', $appointmentId)
                ->count(),
        );

        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $managerContext,
                fn (): string => $commands->appoint(
                    actor: $manager,
                    appointeeUserId: (string) $manager->getKey(),
                    appointeeMembershipId: (string) $managerMembership->getKey(),
                    authorityKind: AuthorityKind::RightsConfirmer,
                    scopeType: 'organization',
                    programId: null,
                    validFrom: now(),
                    validUntil: null,
                    qualificationBasis: 'Caller-claimed evidence is insufficient',
                    approvalEvidenceKey: 'APP-0004',
                    idempotencyKey: 'reject-untrusted-self-appointment',
                ),
            ),
        );
        $this->assertSame(
            1,
            DB::table('authority_appointments')->count(),
        );

        config([
            'content_governance.consolidated_self_appointments.APP-0004.actor_user_id' => (string) $manager->getKey(),
        ]);
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $managerContext,
                fn (): string => $commands->appoint(
                    actor: $manager,
                    appointeeUserId: (string) $manager->getKey(),
                    appointeeMembershipId: (string) $managerMembership->getKey(),
                    authorityKind: AuthorityKind::EnglishAcademicReviewer,
                    scopeType: 'organization',
                    programId: null,
                    validFrom: now(),
                    validUntil: null,
                    qualificationBasis: 'Caller supplied a different basis',
                    approvalEvidenceKey: 'APP-0004',
                    idempotencyKey: 'reject-mismatched-approved-basis',
                ),
            ),
        );
        $selfAppointmentId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->appoint(
                actor: $manager,
                appointeeUserId: (string) $manager->getKey(),
                appointeeMembershipId: (string) $managerMembership->getKey(),
                authorityKind: AuthorityKind::EnglishAcademicReviewer,
                scopeType: 'organization',
                programId: null,
                validFrom: now(),
                validUntil: null,
                qualificationBasis: 'MA',
                approvalEvidenceKey: 'APP-0004',
                idempotencyKey: 'appoint-approved-consolidated-role',
            ),
        );
        $this->assertDatabaseHas('authority_appointments', [
            'id' => $selfAppointmentId,
            'appointee_user_id' => $manager->getKey(),
            'appointee_membership_id' => $managerMembership->getKey(),
            'authority_kind' => AuthorityKind::EnglishAcademicReviewer->value,
            'qualification_basis' => 'MA',
            'approval_evidence_key' => 'APP-0004',
            'appointed_by_membership_id' => $managerMembership->getKey(),
        ]);
        $this->assertSame(2, DB::table('authority_appointments')->count());
    }

    public function test_english_and_kannada_reviews_require_exact_hashes_scope_and_distinct_humans(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T11:00:00+00:00');
        $organization = $this->createOrganization('Review Tenant');
        $manager = $this->createUser();
        [$managerMembership, $managerContext] = $this->createMembership(
            $organization,
            $manager,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $author = $this->createUser();
        [$authorMembership, $authorContext] = $this->createMembership(
            $organization,
            $author,
            Membership::TYPE_TRAINER,
        );
        $translator = $this->createUser();
        [$translatorMembership, $translatorContext] = $this->createMembership(
            $organization,
            $translator,
            Membership::TYPE_TRAINER,
        );
        $englishReviewer = $this->createUser();
        [$englishMembership, $englishContext] = $this->createMembership(
            $organization,
            $englishReviewer,
            Membership::TYPE_TRAINER,
        );
        $kannadaReviewer = $this->createUser();
        [$kannadaMembership, $kannadaContext] = $this->createMembership(
            $organization,
            $kannadaReviewer,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermission(
            $managerMembership,
            $managerContext,
            'curriculum.authority.manage',
        );
        $this->grantPermission(
            $englishMembership,
            $englishContext,
            'curriculum.review.en',
        );
        $this->grantPermission(
            $kannadaMembership,
            $kannadaContext,
            'curriculum.review.kn',
        );
        $this->grantPermission(
            $authorMembership,
            $authorContext,
            'curriculum.review.en',
        );
        $this->grantPermission(
            $translatorMembership,
            $translatorContext,
            'curriculum.review.kn',
        );
        $content = $this->createContentFixture(
            organizationId: (string) $organization->getKey(),
            authorMembershipId: (string) $authorMembership->getKey(),
            translatorMembershipId: (string) $translatorMembership->getKey(),
        );
        $otherProgramId = $this->insertProgram(
            organizationId: (string) $organization->getKey(),
            creatorMembershipId: (string) $managerMembership->getKey(),
            key: 'other-program',
        );
        $englishAppointment = $this->appointAndAccept(
            manager: $manager,
            managerContext: $managerContext,
            reviewer: $englishReviewer,
            reviewerMembership: $englishMembership,
            reviewerContext: $englishContext,
            kind: AuthorityKind::EnglishAcademicReviewer,
            programId: $content['program_id'],
        );
        $kannadaAppointment = $this->appointAndAccept(
            manager: $manager,
            managerContext: $managerContext,
            reviewer: $kannadaReviewer,
            reviewerMembership: $kannadaMembership,
            reviewerContext: $kannadaContext,
            kind: AuthorityKind::KannadaReviewer,
            programId: $content['program_id'],
        );
        $authorAppointment = $this->appointAndAccept(
            manager: $manager,
            managerContext: $managerContext,
            reviewer: $author,
            reviewerMembership: $authorMembership,
            reviewerContext: $authorContext,
            kind: AuthorityKind::EnglishAcademicReviewer,
            programId: $content['program_id'],
        );
        $translatorAppointment = $this->appointAndAccept(
            manager: $manager,
            managerContext: $managerContext,
            reviewer: $translator,
            reviewerMembership: $translatorMembership,
            reviewerContext: $translatorContext,
            kind: AuthorityKind::KannadaReviewer,
            programId: $content['program_id'],
        );
        $wrongScopeAppointment = $this->appointAndAccept(
            manager: $manager,
            managerContext: $managerContext,
            reviewer: $englishReviewer,
            reviewerMembership: $englishMembership,
            reviewerContext: $englishContext,
            kind: AuthorityKind::EnglishAcademicReviewer,
            programId: $otherProgramId,
        );
        $reviews = $this->app->make(ContentReviewCommands::class);

        $englishReviewId = $this->contexts()->run(
            $englishContext,
            fn (): string => $reviews->reviewEnglish(
                actor: $englishReviewer,
                sourceRevisionId: $content['source_revision_id'],
                sourceContentHash: $content['source_hash'],
                authorityAppointmentId: $englishAppointment,
                decision: 'approved',
                idempotencyKey: 'approve-english-content',
            ),
        );
        $kannadaReviewId = $this->contexts()->run(
            $kannadaContext,
            fn (): string => $reviews->reviewKannada(
                actor: $kannadaReviewer,
                sourceRevisionId: $content['source_revision_id'],
                sourceContentHash: $content['source_hash'],
                localizedVariantId: $content['variant_id'],
                localizedContentHash: $content['variant_hash'],
                authorityAppointmentId: $kannadaAppointment,
                decision: 'approved',
                idempotencyKey: 'approve-kannada-content',
            ),
        );

        $this->assertDatabaseHas('content_reviews', [
            'id' => $englishReviewId,
            'review_kind' => 'academic_en',
            'source_revision_id' => $content['source_revision_id'],
            'source_content_hash' => $content['source_hash'],
            'localized_variant_id' => null,
            'reviewer_membership_id' => $englishMembership->getKey(),
            'decision' => 'approved',
        ]);
        $this->assertDatabaseHas('content_reviews', [
            'id' => $kannadaReviewId,
            'review_kind' => 'kannada',
            'source_revision_id' => $content['source_revision_id'],
            'localized_variant_id' => $content['variant_id'],
            'source_content_hash' => $content['source_hash'],
            'localized_content_hash' => $content['variant_hash'],
            'reviewer_membership_id' => $kannadaMembership->getKey(),
            'decision' => 'approved',
        ]);
        $withdrawalId = $this->contexts()->run(
            $englishContext,
            fn (): string => $reviews->withdraw(
                actor: $englishReviewer,
                contentReviewId: $englishReviewId,
                authorityAppointmentId: $englishAppointment,
                rationaleKey: 'source_review_superseded',
                idempotencyKey: 'withdraw-english-review',
            ),
        );
        $replayedWithdrawalId = $this->contexts()->run(
            $englishContext,
            fn (): string => $reviews->withdraw(
                actor: $englishReviewer,
                contentReviewId: $englishReviewId,
                authorityAppointmentId: $englishAppointment,
                rationaleKey: 'source_review_superseded',
                idempotencyKey: 'withdraw-english-review',
            ),
        );
        self::assertSame($withdrawalId, $replayedWithdrawalId);
        $this->assertDatabaseHas('content_reviews', [
            'id' => $withdrawalId,
            'review_kind' => 'academic_en',
            'source_revision_id' => $content['source_revision_id'],
            'source_content_hash' => $content['source_hash'],
            'localized_variant_id' => null,
            'reviewer_membership_id' => $englishMembership->getKey(),
            'decision' => 'withdrawn',
            'rationale_key' => 'source_review_superseded',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'curriculum.content_review.withdrawn',
            'aggregate_id' => $withdrawalId,
        ]);
        $this->assertIdempotencyConflict(
            fn (): string => $this->contexts()->run(
                $englishContext,
                fn (): string => $reviews->withdraw(
                    actor: $englishReviewer,
                    contentReviewId: $englishReviewId,
                    authorityAppointmentId: $englishAppointment,
                    rationaleKey: 'different_withdrawal_reason',
                    idempotencyKey: 'withdraw-english-review',
                ),
            ),
        );

        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $authorContext,
                fn (): string => $reviews->reviewEnglish(
                    actor: $author,
                    sourceRevisionId: $content['source_revision_id'],
                    sourceContentHash: $content['source_hash'],
                    authorityAppointmentId: $authorAppointment,
                    decision: 'approved',
                    idempotencyKey: 'reject-author-self-review',
                ),
            ),
        );
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $translatorContext,
                fn (): string => $reviews->reviewKannada(
                    actor: $translator,
                    sourceRevisionId: $content['source_revision_id'],
                    sourceContentHash: $content['source_hash'],
                    localizedVariantId: $content['variant_id'],
                    localizedContentHash: $content['variant_hash'],
                    authorityAppointmentId: $translatorAppointment,
                    decision: 'approved',
                    idempotencyKey: 'reject-translator-self-review',
                ),
            ),
        );
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $englishContext,
                fn (): string => $reviews->reviewEnglish(
                    actor: $englishReviewer,
                    sourceRevisionId: $content['source_revision_id'],
                    sourceContentHash: str_repeat('f', 64),
                    authorityAppointmentId: $englishAppointment,
                    decision: 'approved',
                    idempotencyKey: 'reject-wrong-source-hash',
                ),
            ),
        );
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $englishContext,
                fn (): string => $reviews->reviewEnglish(
                    actor: $englishReviewer,
                    sourceRevisionId: $content['source_revision_id'],
                    sourceContentHash: $content['source_hash'],
                    authorityAppointmentId: $wrongScopeAppointment,
                    decision: 'approved',
                    idempotencyKey: 'reject-wrong-review-scope',
                ),
            ),
        );
        $this->assertSame(3, DB::table('content_reviews')->count());
    }

    public function test_ordinary_expiry_and_surrender_preserve_review_but_for_cause_revocation_invalidates_it(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T12:00:00+00:00');
        $organization = $this->createOrganization('Historical Review Tenant');
        $manager = $this->createUser();
        [$managerMembership, $managerContext] = $this->createMembership(
            $organization,
            $manager,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $author = $this->createUser();
        [$authorMembership] = $this->createMembership(
            $organization,
            $author,
            Membership::TYPE_TRAINER,
        );
        $reviewer = $this->createUser();
        [$reviewerMembership, $reviewerContext] = $this->createMembership(
            $organization,
            $reviewer,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermission(
            $managerMembership,
            $managerContext,
            'curriculum.authority.manage',
        );
        $this->grantPermission(
            $reviewerMembership,
            $reviewerContext,
            'curriculum.review.en',
        );
        $content = $this->createContentFixture(
            organizationId: (string) $organization->getKey(),
            authorMembershipId: (string) $authorMembership->getKey(),
            translatorMembershipId: (string) $authorMembership->getKey(),
        );
        $commands = $this->app->make(AuthorityAppointmentCommands::class);
        $appointmentId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->appoint(
                actor: $manager,
                appointeeUserId: (string) $reviewer->getKey(),
                appointeeMembershipId: (string) $reviewerMembership->getKey(),
                authorityKind: AuthorityKind::EnglishAcademicReviewer,
                scopeType: 'program',
                programId: $content['program_id'],
                validFrom: now()->subMinute(),
                validUntil: now()->addHour(),
                qualificationBasis: 'Synthetic time-bound reviewer',
                approvalEvidenceKey: 'APP-TEST-0002',
                idempotencyKey: 'appoint-time-bound-reviewer',
            ),
        );
        $this->contexts()->run(
            $reviewerContext,
            fn (): string => $commands->accept(
                $reviewer,
                $appointmentId,
                'accept-time-bound-reviewer',
            ),
        );
        $reviewId = $this->contexts()->run(
            $reviewerContext,
            fn (): string => $this->app
                ->make(ContentReviewCommands::class)
                ->reviewEnglish(
                    actor: $reviewer,
                    sourceRevisionId: $content['source_revision_id'],
                    sourceContentHash: $content['source_hash'],
                    authorityAppointmentId: $appointmentId,
                    decision: 'approved',
                    idempotencyKey: 'approve-time-bound-review',
                ),
        );
        $ordinaryAppointmentId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->appoint(
                actor: $manager,
                appointeeUserId: (string) $reviewer->getKey(),
                appointeeMembershipId: (string) $reviewerMembership->getKey(),
                authorityKind: AuthorityKind::EnglishAcademicReviewer,
                scopeType: 'program',
                programId: $content['program_id'],
                validFrom: now()->subMinute(),
                validUntil: now()->addMonth(),
                qualificationBasis: 'Synthetic ordinarily revocable reviewer',
                approvalEvidenceKey: 'APP-TEST-0002',
                idempotencyKey: 'appoint-ordinarily-revocable-reviewer',
            ),
        );
        $this->contexts()->run(
            $reviewerContext,
            fn (): string => $commands->accept(
                $reviewer,
                $ordinaryAppointmentId,
                'accept-ordinarily-revocable-reviewer',
            ),
        );
        $ordinaryReviewId = $this->contexts()->run(
            $reviewerContext,
            fn (): string => $this->app
                ->make(ContentReviewCommands::class)
                ->reviewEnglish(
                    actor: $reviewer,
                    sourceRevisionId: $content['source_revision_id'],
                    sourceContentHash: $content['source_hash'],
                    authorityAppointmentId: $ordinaryAppointmentId,
                    decision: 'approved',
                    idempotencyKey: 'approve-ordinary-review',
                ),
        );

        CarbonImmutable::setTestNow('2026-07-30T12:30:00+00:00');
        $validator = $this->app->make(AuthorityEvidenceValidator::class);
        $this->contexts()->run(
            $managerContext,
            fn (): null => $this->assertReviewAuthority(
                $validator,
                $reviewId,
            ),
        );
        $this->contexts()->run(
            $reviewerContext,
            fn (): string => $commands->surrender(
                $reviewer,
                $appointmentId,
                'surrender-time-bound-reviewer',
            ),
        );
        $ordinaryRevocationId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->revoke(
                actor: $manager,
                appointmentId: $ordinaryAppointmentId,
                reasonKey: 'ordinary_role_removal',
                idempotencyKey: 'revoke-ordinary-reviewer',
            ),
        );
        $replayedOrdinaryRevocationId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->revoke(
                actor: $manager,
                appointmentId: $ordinaryAppointmentId,
                reasonKey: 'ordinary_role_removal',
                idempotencyKey: 'revoke-ordinary-reviewer',
            ),
        );
        self::assertSame(
            $ordinaryRevocationId,
            $replayedOrdinaryRevocationId,
        );
        $this->assertDatabaseHas('authority_appointment_events', [
            'id' => $ordinaryRevocationId,
            'authority_appointment_id' => $ordinaryAppointmentId,
            'event_type' => 'revoked',
            'reason_key' => 'ordinary_role_removal',
            'invalidates_prior_approvals' => false,
        ]);
        $this->contexts()->run(
            $managerContext,
            fn (): null => $this->assertReviewAuthority(
                $validator,
                $reviewId,
            ),
        );
        $this->contexts()->run(
            $managerContext,
            fn (): null => $this->assertReviewAuthority(
                $validator,
                $ordinaryReviewId,
            ),
        );
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $reviewerContext,
                fn (): string => $this->app
                    ->make(ContentReviewCommands::class)
                    ->reviewEnglish(
                        actor: $reviewer,
                        sourceRevisionId: $content['source_revision_id'],
                        sourceContentHash: $content['source_hash'],
                        authorityAppointmentId: $ordinaryAppointmentId,
                        decision: 'approved',
                        idempotencyKey: 'reject-review-after-revocation',
                    ),
            ),
        );

        CarbonImmutable::setTestNow('2026-07-30T14:00:00+00:00');
        $expiryEventId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->recordExpiry(
                actor: $manager,
                appointmentId: $appointmentId,
                idempotencyKey: 'record-reviewer-expiry',
            ),
        );
        $replayedExpiryEventId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->recordExpiry(
                actor: $manager,
                appointmentId: $appointmentId,
                idempotencyKey: 'record-reviewer-expiry',
            ),
        );
        self::assertSame($expiryEventId, $replayedExpiryEventId);
        $this->assertDatabaseHas('authority_appointment_events', [
            'id' => $expiryEventId,
            'authority_appointment_id' => $appointmentId,
            'event_type' => 'expired',
            'reason_key' => 'appointment_term_elapsed',
            'invalidates_prior_approvals' => false,
        ]);
        $expiryEffectiveAt = DB::table('authority_appointment_events')
            ->where('id', $expiryEventId)
            ->value('effective_at');
        self::assertIsString($expiryEffectiveAt);
        self::assertTrue(
            CarbonImmutable::parse($expiryEffectiveAt)->equalTo(
                CarbonImmutable::parse('2026-07-30T13:00:00+00:00'),
            ),
        );
        $this->contexts()->run(
            $managerContext,
            fn (): null => $this->assertReviewAuthority(
                $validator,
                $reviewId,
            ),
        );

        $staleSession = $this->createPrivilegedSession(
            $manager,
            now()->subMinutes(16),
        );
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $managerContext,
                fn (): string => $commands->revokeForCause(
                    actor: $manager,
                    appointmentId: $appointmentId,
                    authenticationSessionId: (string) $staleSession->getKey(),
                    reasonKey: 'reviewer_authority_compromised',
                    idempotencyKey: 'reject-stale-mfa-revocation',
                ),
            ),
        );
        $this->assertSame(
            0,
            DB::table('authority_appointment_events')
                ->where('authority_appointment_id', $appointmentId)
                ->where('event_type', 'revoked_for_cause')
                ->count(),
        );

        $freshSession = $this->createPrivilegedSession(
            $manager,
            now()->subMinutes(5),
        );
        $revocationId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->revokeForCause(
                actor: $manager,
                appointmentId: $appointmentId,
                authenticationSessionId: (string) $freshSession->getKey(),
                reasonKey: 'reviewer_authority_compromised',
                idempotencyKey: 'revoke-compromised-reviewer',
            ),
        );
        $replayedRevocationId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->revokeForCause(
                actor: $manager,
                appointmentId: $appointmentId,
                authenticationSessionId: (string) $freshSession->getKey(),
                reasonKey: 'reviewer_authority_compromised',
                idempotencyKey: 'revoke-compromised-reviewer',
            ),
        );
        self::assertSame($revocationId, $replayedRevocationId);
        $this->assertDatabaseHas('authority_appointment_events', [
            'id' => $revocationId,
            'authority_appointment_id' => $appointmentId,
            'event_type' => 'revoked_for_cause',
            'reason_key' => 'reviewer_authority_compromised',
            'invalidates_prior_approvals' => true,
        ]);
        $this->assertRejected(
            fn (): null => $this->contexts()->run(
                $managerContext,
                fn (): null => $this->assertReviewAuthority(
                    $validator,
                    $reviewId,
                ),
            ),
        );
        $this->contexts()->run(
            $managerContext,
            fn (): null => $this->assertReviewAuthority(
                $validator,
                $ordinaryReviewId,
            ),
        );

        try {
            DB::table('content_reviews')
                ->where('id', $reviewId)
                ->update(['decision' => 'rejected']);
            self::fail('Review evidence must reject direct updates.');
        } catch (QueryException $exception) {
            self::assertSame('55000', (string) $exception->getCode());
        }
    }

    public function test_media_rights_confirmation_requires_exact_asset_org_scope_and_append_only_successors(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T15:00:00+00:00');
        $organization = $this->createOrganization('Rights Tenant');
        $manager = $this->createUser();
        [$managerMembership, $managerContext] = $this->createMembership(
            $organization,
            $manager,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $uploader = $this->createUser();
        [$uploaderMembership] = $this->createMembership(
            $organization,
            $uploader,
            Membership::TYPE_TRAINER,
        );
        $confirmer = $this->createUser();
        [$confirmerMembership, $confirmerContext] = $this->createMembership(
            $organization,
            $confirmer,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermission(
            $managerMembership,
            $managerContext,
            'curriculum.authority.manage',
        );
        $this->grantPermission(
            $confirmerMembership,
            $confirmerContext,
            'media.rights.approve',
        );
        $programId = $this->insertProgram(
            (string) $organization->getKey(),
            (string) $managerMembership->getKey(),
            'rights-program',
        );
        $asset = $this->createMediaAsset(
            organizationId: (string) $organization->getKey(),
            uploaderMembershipId: (string) $uploaderMembership->getKey(),
        );
        $appointmentId = $this->contexts()->run(
            $managerContext,
            fn (): string => $this->app
                ->make(AuthorityAppointmentCommands::class)
                ->appoint(
                    actor: $manager,
                    appointeeUserId: (string) $confirmer->getKey(),
                    appointeeMembershipId: (string) $confirmerMembership->getKey(),
                    authorityKind: AuthorityKind::RightsConfirmer,
                    scopeType: 'program',
                    programId: $programId,
                    validFrom: now()->subMinute(),
                    validUntil: now()->addHour(),
                    qualificationBasis: 'Synthetic accountable rights basis',
                    approvalEvidenceKey: 'APP-TEST-0003',
                    idempotencyKey: 'appoint-rights-confirmer',
                ),
        );
        $this->contexts()->run(
            $confirmerContext,
            fn (): string => $this->app
                ->make(AuthorityAppointmentCommands::class)
                ->accept(
                    $confirmer,
                    $appointmentId,
                    'accept-rights-confirmer',
                ),
        );
        $rights = $this->app->make(MediaRightsConfirmationCommands::class);
        $otherProgramId = $this->insertProgram(
            (string) $organization->getKey(),
            (string) $managerMembership->getKey(),
            'other-rights-program',
        );
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $confirmerContext,
                fn (): string => $rights->confirm(
                    actor: $confirmer,
                    programId: $otherProgramId,
                    mediaAssetId: $asset['id'],
                    selectedAssetHash: $asset['hash'],
                    parentRevisionId: null,
                    evidenceHash: hash(
                        'sha256',
                        'wrong-program-rights-evidence',
                    ),
                    rightsHolderReference: 'party:SYNTHETIC-RIGHTS-HOLDER',
                    provenanceReference: 'provenance:SYNTHETIC-UPLOAD-001',
                    rightsBasisKey: 'client_owned',
                    licenseOrContractReference: 'contract:SYNTHETIC-OTHER',
                    permittedReleaseChannels: ['android', 'web'],
                    permittedTerritories: ['IN'],
                    learnerDeliveryAllowed: true,
                    sharingAllowed: false,
                    derivativeUseAllowed: false,
                    effectiveAt: now()->subDay(),
                    expiresAt: now()->addYear(),
                    retentionPolicyKey: 'retain_while_published',
                    deletionConstraintKey: 'rights_evidence_required',
                    authorityAppointmentId: $appointmentId,
                    idempotencyKey: 'reject-rights-wrong-program-scope',
                ),
            ),
        );
        $rightsRevisionId = $this->contexts()->run(
            $confirmerContext,
            fn (): string => $rights->confirm(
                actor: $confirmer,
                programId: $programId,
                mediaAssetId: $asset['id'],
                selectedAssetHash: $asset['hash'],
                parentRevisionId: null,
                evidenceHash: hash('sha256', 'synthetic-rights-evidence-1'),
                rightsHolderReference: 'party:SYNTHETIC-RIGHTS-HOLDER',
                provenanceReference: 'provenance:SYNTHETIC-UPLOAD-001',
                rightsBasisKey: 'client_owned',
                licenseOrContractReference: 'contract:SYNTHETIC-001',
                permittedReleaseChannels: ['android', 'web'],
                permittedTerritories: ['IN'],
                learnerDeliveryAllowed: true,
                sharingAllowed: true,
                derivativeUseAllowed: false,
                effectiveAt: now()->subDay(),
                expiresAt: now()->addYear(),
                retentionPolicyKey: 'retain_while_published',
                deletionConstraintKey: 'rights_evidence_required',
                authorityAppointmentId: $appointmentId,
                idempotencyKey: 'approve-rights-revision-one',
            ),
        );

        $this->assertDatabaseHas('media_rights_revisions', [
            'id' => $rightsRevisionId,
            'program_id' => $programId,
            'media_asset_id' => $asset['id'],
            'revision_number' => 1,
            'evidence_status' => 'approved',
            'selected_asset_hash' => $asset['hash'],
            'rights_holder_reference' => 'party:SYNTHETIC-RIGHTS-HOLDER',
            'provenance_reference' => 'provenance:SYNTHETIC-UPLOAD-001',
            'rights_basis_key' => 'client_owned',
            'learner_delivery_allowed' => true,
            'confirmed_by_membership_id' => $confirmerMembership->getKey(),
            'rights_appointment_id' => $appointmentId,
        ]);
        $replayedRightsRevisionId = $this->contexts()->run(
            $confirmerContext,
            fn (): string => $rights->confirm(
                actor: $confirmer,
                programId: $programId,
                mediaAssetId: $asset['id'],
                selectedAssetHash: $asset['hash'],
                parentRevisionId: null,
                evidenceHash: hash(
                    'sha256',
                    'synthetic-rights-evidence-1',
                ),
                rightsHolderReference: 'party:SYNTHETIC-RIGHTS-HOLDER',
                provenanceReference: 'provenance:SYNTHETIC-UPLOAD-001',
                rightsBasisKey: 'client_owned',
                licenseOrContractReference: 'contract:SYNTHETIC-001',
                permittedReleaseChannels: ['web', 'android'],
                permittedTerritories: ['IN'],
                learnerDeliveryAllowed: true,
                sharingAllowed: true,
                derivativeUseAllowed: false,
                effectiveAt: now()->subDay(),
                expiresAt: now()->addYear(),
                retentionPolicyKey: 'retain_while_published',
                deletionConstraintKey: 'rights_evidence_required',
                authorityAppointmentId: $appointmentId,
                idempotencyKey: 'approve-rights-revision-one',
            ),
        );
        self::assertSame($rightsRevisionId, $replayedRightsRevisionId);
        $serializedOutbox = json_encode(
            DB::table('outbox_events')
                ->where('aggregate_id', $rightsRevisionId)
                ->sole(),
            JSON_THROW_ON_ERROR,
        );
        self::assertStringNotContainsString(
            'synthetic-rights-evidence',
            $serializedOutbox,
        );
        self::assertStringNotContainsString(
            'SYNTHETIC-001',
            $serializedOutbox,
        );

        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $confirmerContext,
                fn (): string => $rights->confirm(
                    actor: $confirmer,
                    programId: $programId,
                    mediaAssetId: $asset['id'],
                    selectedAssetHash: str_repeat('f', 64),
                    parentRevisionId: $rightsRevisionId,
                    evidenceHash: hash(
                        'sha256',
                        'synthetic-rights-evidence-2',
                    ),
                    rightsHolderReference: 'party:SYNTHETIC-RIGHTS-HOLDER',
                    provenanceReference: 'provenance:SYNTHETIC-UPLOAD-001',
                    rightsBasisKey: 'client_owned',
                    licenseOrContractReference: 'contract:SYNTHETIC-002',
                    permittedReleaseChannels: ['web'],
                    permittedTerritories: ['IN'],
                    learnerDeliveryAllowed: true,
                    sharingAllowed: false,
                    derivativeUseAllowed: false,
                    effectiveAt: now(),
                    expiresAt: null,
                    retentionPolicyKey: 'retain_while_published',
                    deletionConstraintKey: 'rights_evidence_required',
                    authorityAppointmentId: $appointmentId,
                    idempotencyKey: 'reject-wrong-asset-hash',
                ),
            ),
        );
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $confirmerContext,
                fn (): string => $rights->confirm(
                    actor: $confirmer,
                    programId: $programId,
                    mediaAssetId: $asset['id'],
                    selectedAssetHash: $asset['hash'],
                    parentRevisionId: null,
                    evidenceHash: hash(
                        'sha256',
                        'synthetic-rights-evidence-2',
                    ),
                    rightsHolderReference: 'party:SYNTHETIC-RIGHTS-HOLDER',
                    provenanceReference: 'provenance:SYNTHETIC-UPLOAD-001',
                    rightsBasisKey: 'client_owned',
                    licenseOrContractReference: 'contract:SYNTHETIC-002',
                    permittedReleaseChannels: ['web'],
                    permittedTerritories: ['IN'],
                    learnerDeliveryAllowed: true,
                    sharingAllowed: false,
                    derivativeUseAllowed: false,
                    effectiveAt: now(),
                    expiresAt: null,
                    retentionPolicyKey: 'retain_while_published',
                    deletionConstraintKey: 'rights_evidence_required',
                    authorityAppointmentId: $appointmentId,
                    idempotencyKey: 'reject-stale-rights-parent',
                ),
            ),
        );
        $withdrawnRightsRevisionId = $this->contexts()->run(
            $confirmerContext,
            fn (): string => $rights->withdraw(
                actor: $confirmer,
                programId: $programId,
                parentRevisionId: $rightsRevisionId,
                evidenceHash: hash(
                    'sha256',
                    'synthetic-rights-withdrawal-evidence',
                ),
                licenseOrContractReference: 'withdrawal:SYNTHETIC-001',
                authorityAppointmentId: $appointmentId,
                idempotencyKey: 'withdraw-approved-rights',
            ),
        );
        $replayedWithdrawnRightsRevisionId = $this->contexts()->run(
            $confirmerContext,
            fn (): string => $rights->withdraw(
                actor: $confirmer,
                programId: $programId,
                parentRevisionId: $rightsRevisionId,
                evidenceHash: hash(
                    'sha256',
                    'synthetic-rights-withdrawal-evidence',
                ),
                licenseOrContractReference: 'withdrawal:SYNTHETIC-001',
                authorityAppointmentId: $appointmentId,
                idempotencyKey: 'withdraw-approved-rights',
            ),
        );
        self::assertSame(
            $withdrawnRightsRevisionId,
            $replayedWithdrawnRightsRevisionId,
        );
        $this->assertDatabaseHas('media_rights_revisions', [
            'id' => $withdrawnRightsRevisionId,
            'media_asset_id' => $asset['id'],
            'parent_revision_id' => $rightsRevisionId,
            'revision_number' => 2,
            'evidence_status' => 'withdrawn',
            'selected_asset_hash' => $asset['hash'],
            'confirmed_by_membership_id' => $confirmerMembership->getKey(),
            'rights_appointment_id' => $appointmentId,
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'curriculum.media_rights.withdrawn',
            'aggregate_id' => $withdrawnRightsRevisionId,
        ]);

        $pendingAsset = $this->createMediaAsset(
            organizationId: (string) $organization->getKey(),
            uploaderMembershipId: (string) $uploaderMembership->getKey(),
        );
        $pendingRevisionId = (string) Str::ulid();
        DB::table('media_rights_revisions')->insert([
            'id' => $pendingRevisionId,
            'organization_id' => $organization->getKey(),
            'program_id' => $programId,
            'media_asset_id' => $pendingAsset['id'],
            'parent_revision_id' => null,
            'revision_number' => 1,
            'evidence_status' => 'pending',
            'evidence_hash' => hash(
                'sha256',
                'synthetic-pending-rights-evidence',
            ),
            'selected_asset_hash' => $pendingAsset['hash'],
            'rights_holder_reference' => 'party:SYNTHETIC-RIGHTS-HOLDER',
            'provenance_reference' => 'provenance:SYNTHETIC-PENDING',
            'rights_basis_key' => 'client_owned',
            'license_or_contract_reference' => 'contract:SYNTHETIC-PENDING',
            'permitted_release_channels' => json_encode(
                ['web'],
                JSON_THROW_ON_ERROR,
            ),
            'permitted_territories' => json_encode(
                ['IN'],
                JSON_THROW_ON_ERROR,
            ),
            'learner_delivery_allowed' => true,
            'sharing_allowed' => false,
            'derivative_use_allowed' => false,
            'effective_at' => now(),
            'expires_at' => null,
            'retention_policy_key' => 'retain_while_pending',
            'deletion_constraint_key' => 'rights_evidence_required',
            'confirmed_by_membership_id' => null,
            'rights_appointment_id' => null,
            'rights_appointment_event_id' => null,
            'confirmed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rejectedRightsRevisionId = $this->contexts()->run(
            $confirmerContext,
            fn (): string => $rights->reject(
                actor: $confirmer,
                programId: $programId,
                parentRevisionId: $pendingRevisionId,
                evidenceHash: hash(
                    'sha256',
                    'synthetic-rights-rejection-evidence',
                ),
                licenseOrContractReference: 'rejection:SYNTHETIC-PENDING',
                authorityAppointmentId: $appointmentId,
                idempotencyKey: 'reject-pending-rights',
            ),
        );
        $this->assertDatabaseHas('media_rights_revisions', [
            'id' => $rejectedRightsRevisionId,
            'media_asset_id' => $pendingAsset['id'],
            'parent_revision_id' => $pendingRevisionId,
            'revision_number' => 2,
            'evidence_status' => 'rejected',
            'selected_asset_hash' => $pendingAsset['hash'],
            'confirmed_by_membership_id' => $confirmerMembership->getKey(),
            'rights_appointment_id' => $appointmentId,
        ]);

        CarbonImmutable::setTestNow('2026-07-30T17:00:00+00:00');
        $this->contexts()->run(
            $managerContext,
            function () use ($rightsRevisionId): void {
                $this->app
                    ->make(AuthorityEvidenceValidator::class)
                    ->assertRightsConfirmationAuthorityRemainsValid(
                        $rightsRevisionId,
                        now(),
                    );
            },
        );

        try {
            DB::table('media_rights_revisions')
                ->where('id', $rightsRevisionId)
                ->delete();
            self::fail('Rights evidence must reject direct deletion.');
        } catch (QueryException $exception) {
            self::assertSame('55000', (string) $exception->getCode());
        }
    }

    public function test_graph_snapshot_and_review_bind_exact_structure_authority_and_human_separation(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T18:00:00+00:00');
        $organization = $this->createOrganization('Graph Governance Tenant');
        $manager = $this->createUser();
        [$managerMembership, $managerContext] = $this->createMembership(
            $organization,
            $manager,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $author = $this->createUser();
        [$authorMembership, $authorContext] = $this->createMembership(
            $organization,
            $author,
            Membership::TYPE_TRAINER,
        );
        $alternateAuthor = $this->createUser();
        [$alternateAuthorMembership, $alternateAuthorContext]
            = $this->createMembership(
                $organization,
                $alternateAuthor,
                Membership::TYPE_TRAINER,
            );
        $reviewer = $this->createUser();
        [$reviewerMembership, $reviewerContext] = $this->createMembership(
            $organization,
            $reviewer,
            Membership::TYPE_TRAINER,
        );
        $alternateReviewer = $this->createUser();
        [$alternateReviewerMembership, $alternateReviewerContext]
            = $this->createMembership(
                $organization,
                $alternateReviewer,
                Membership::TYPE_TRAINER,
            );
        $this->grantPermission(
            $managerMembership,
            $managerContext,
            'curriculum.authority.manage',
        );
        $this->grantPermission(
            $authorMembership,
            $authorContext,
            'curriculum.author',
        );
        $this->grantPermission(
            $alternateAuthorMembership,
            $alternateAuthorContext,
            'curriculum.author',
        );
        $this->grantPermission(
            $reviewerMembership,
            $reviewerContext,
            'curriculum.review.en',
        );
        $this->grantPermission(
            $alternateReviewerMembership,
            $alternateReviewerContext,
            'curriculum.review.en',
        );
        $content = $this->createContentFixture(
            organizationId: (string) $organization->getKey(),
            authorMembershipId: (string) $authorMembership->getKey(),
            translatorMembershipId: (string) $authorMembership->getKey(),
        );
        $appointmentId = $this->appointAndAccept(
            manager: $manager,
            managerContext: $managerContext,
            reviewer: $reviewer,
            reviewerMembership: $reviewerMembership,
            reviewerContext: $reviewerContext,
            kind: AuthorityKind::EnglishAcademicReviewer,
            programId: $content['program_id'],
        );
        $alternateAppointmentId = $this->appointAndAccept(
            manager: $manager,
            managerContext: $managerContext,
            reviewer: $alternateReviewer,
            reviewerMembership: $alternateReviewerMembership,
            reviewerContext: $alternateReviewerContext,
            kind: AuthorityKind::EnglishAcademicReviewer,
            programId: $content['program_id'],
        );
        $projection = $this->app->make(CurriculumGraphProjector::class)->project(
            (string) $organization->getKey(),
            $content['curriculum_version_id'],
        );
        $commands = $this->app->make(
            CurriculumGraphGovernanceCommands::class,
        );
        $graphRevisionId = $this->contexts()->run(
            $authorContext,
            fn (): string => $commands->snapshot(
                actor: $author,
                curriculumVersionId: $content['curriculum_version_id'],
                expectedRevisionNumber: 0,
                expectedGraphHash: $projection['graph_hash'],
                idempotencyKey: 'snapshot-graph-v1',
                authorAttribution: 'synthetic-test-author',
            ),
        );
        $replayedGraphRevisionId = $this->contexts()->run(
            $authorContext,
            fn (): string => $commands->snapshot(
                actor: $author,
                curriculumVersionId: $content['curriculum_version_id'],
                expectedRevisionNumber: 0,
                expectedGraphHash: $projection['graph_hash'],
                idempotencyKey: 'snapshot-graph-v1',
                authorAttribution: 'synthetic-test-author',
            ),
        );
        self::assertSame($graphRevisionId, $replayedGraphRevisionId);
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $alternateAuthorContext,
                fn (): string => $commands->snapshot(
                    actor: $alternateAuthor,
                    curriculumVersionId: $content['curriculum_version_id'],
                    expectedRevisionNumber: 0,
                    expectedGraphHash: $projection['graph_hash'],
                    idempotencyKey: 'snapshot-graph-v1',
                    authorAttribution: 'synthetic-test-author',
                ),
            ),
        );

        $reviewId = $this->contexts()->run(
            $reviewerContext,
            fn (): string => $commands->review(
                actor: $reviewer,
                curriculumGraphRevisionId: $graphRevisionId,
                graphHash: $projection['graph_hash'],
                authorityAppointmentId: $appointmentId,
                decision: 'approved',
                idempotencyKey: 'approve-graph-v1',
            ),
        );
        $replayedReviewId = $this->contexts()->run(
            $reviewerContext,
            fn (): string => $commands->review(
                actor: $reviewer,
                curriculumGraphRevisionId: $graphRevisionId,
                graphHash: $projection['graph_hash'],
                authorityAppointmentId: $appointmentId,
                decision: 'approved',
                idempotencyKey: 'approve-graph-v1',
            ),
        );

        self::assertSame($reviewId, $replayedReviewId);
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $alternateReviewerContext,
                fn (): string => $commands->review(
                    actor: $alternateReviewer,
                    curriculumGraphRevisionId: $graphRevisionId,
                    graphHash: $projection['graph_hash'],
                    authorityAppointmentId: $alternateAppointmentId,
                    decision: 'approved',
                    idempotencyKey: 'approve-graph-v1',
                ),
            ),
        );
        $this->assertDatabaseHas('curriculum_graph_revisions', [
            'id' => $graphRevisionId,
            'organization_id' => $organization->getKey(),
            'curriculum_version_id' => $content['curriculum_version_id'],
            'graph_hash' => $projection['graph_hash'],
            'authored_by_membership_id' => $authorMembership->getKey(),
        ]);
        $this->assertDatabaseHas('curriculum_graph_reviews', [
            'id' => $reviewId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $projection['graph_hash'],
            'reviewer_membership_id' => $reviewerMembership->getKey(),
            'decision' => 'approved',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'curriculum.graph.review_recorded',
            'aggregate_id' => $reviewId,
        ]);
        $this->assertIdempotencyConflict(
            fn (): string => $this->contexts()->run(
                $reviewerContext,
                fn (): string => $commands->review(
                    actor: $reviewer,
                    curriculumGraphRevisionId: $graphRevisionId,
                    graphHash: $projection['graph_hash'],
                    authorityAppointmentId: $appointmentId,
                    decision: 'rejected',
                    idempotencyKey: 'approve-graph-v1',
                ),
            ),
        );

        $withdrawalId = $this->contexts()->run(
            $reviewerContext,
            fn (): string => $commands->withdraw(
                actor: $reviewer,
                curriculumGraphReviewId: $reviewId,
                authorityAppointmentId: $appointmentId,
                rationaleKey: 'reviewer_withdrew_graph_approval',
                idempotencyKey: 'withdraw-graph-v1',
            ),
        );
        $replayedWithdrawalId = $this->contexts()->run(
            $reviewerContext,
            fn (): string => $commands->withdraw(
                actor: $reviewer,
                curriculumGraphReviewId: $reviewId,
                authorityAppointmentId: $appointmentId,
                rationaleKey: 'reviewer_withdrew_graph_approval',
                idempotencyKey: 'withdraw-graph-v1',
            ),
        );
        self::assertSame($withdrawalId, $replayedWithdrawalId);
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $alternateReviewerContext,
                fn (): string => $commands->withdraw(
                    actor: $alternateReviewer,
                    curriculumGraphReviewId: $reviewId,
                    authorityAppointmentId: $appointmentId,
                    rationaleKey: 'reviewer_withdrew_graph_approval',
                    idempotencyKey: 'withdraw-graph-v1',
                ),
            ),
        );
        $this->assertDatabaseHas('curriculum_graph_reviews', [
            'id' => $withdrawalId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $projection['graph_hash'],
            'reviewer_membership_id' => $reviewerMembership->getKey(),
            'decision' => 'withdrawn',
            'rationale_key' => 'reviewer_withdrew_graph_approval',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'curriculum.graph_review.withdrawn',
            'aggregate_id' => $withdrawalId,
        ]);
        $this->assertRejected(
            fn (): string => $this->contexts()->run(
                $reviewerContext,
                fn (): string => $commands->withdraw(
                    actor: $reviewer,
                    curriculumGraphReviewId: $reviewId,
                    authorityAppointmentId: $appointmentId,
                    rationaleKey: 'second_withdrawal',
                    idempotencyKey: 'withdraw-graph-v1-again',
                ),
            ),
        );
    }

    private function grantPermission(
        Membership $membership,
        OrganizationContext $context,
        string $permissionKey,
    ): void {
        $permission = Permission::query()
            ->where('key', $permissionKey)
            ->sole();
        $role = $this->createRole(
            $context,
            'governance-'.strtolower((string) Str::ulid()),
        );
        $now = now();

        DB::table('membership_roles')->insert([
            'organization_id' => $membership->organizationId(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $membership->organizationId(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function appointAndAccept(
        User $manager,
        OrganizationContext $managerContext,
        User $reviewer,
        Membership $reviewerMembership,
        OrganizationContext $reviewerContext,
        AuthorityKind $kind,
        string $programId,
    ): string {
        $commands = $this->app->make(AuthorityAppointmentCommands::class);
        $appointmentId = $this->contexts()->run(
            $managerContext,
            fn (): string => $commands->appoint(
                actor: $manager,
                appointeeUserId: (string) $reviewer->getKey(),
                appointeeMembershipId: (string) $reviewerMembership->getKey(),
                authorityKind: $kind,
                scopeType: 'program',
                programId: $programId,
                validFrom: now()->subMinute(),
                validUntil: now()->addMonth(),
                qualificationBasis: 'Synthetic governance test basis',
                approvalEvidenceKey: 'APP-TEST-REVIEW',
                idempotencyKey: 'appoint-'.strtolower(
                    (string) Str::ulid(),
                ),
            ),
        );
        $this->contexts()->run(
            $reviewerContext,
            fn (): string => $commands->accept(
                $reviewer,
                $appointmentId,
                'accept-'.strtolower((string) Str::ulid()),
            ),
        );

        return $appointmentId;
    }

    /**
     * @return array{
     *   program_id: string,
     *   curriculum_version_id: string,
     *   source_revision_id: string,
     *   source_hash: string,
     *   variant_id: string,
     *   variant_hash: string
     * }
     */
    private function createContentFixture(
        string $organizationId,
        string $authorMembershipId,
        string $translatorMembershipId,
    ): array {
        $programId = $this->insertProgram(
            organizationId: $organizationId,
            creatorMembershipId: $authorMembershipId,
            key: 'foundation-'.strtolower((string) Str::ulid()),
        );
        $curriculumVersionId = (string) Str::ulid();
        $objectiveId = (string) Str::ulid();
        $moduleId = (string) Str::ulid();
        $lessonId = (string) Str::ulid();
        $contentDocumentId = (string) Str::ulid();
        $sourceRevisionId = (string) Str::ulid();
        $localizedVariantId = (string) Str::ulid();
        $learningItemId = (string) Str::ulid();
        $missionId = (string) Str::ulid();
        $sourceHash = hash('sha256', 'synthetic-source-'.$sourceRevisionId);
        $variantHash = hash(
            'sha256',
            'synthetic-kannada-'.$localizedVariantId,
        );
        $now = now();

        DB::table('curriculum_versions')->insert([
            'id' => $curriculumVersionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'key' => 'v1',
            'created_by_membership_id' => $authorMembershipId,
            'created_by_attribution' => 'synthetic-test-author',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_objectives')->insert([
            'id' => $objectiveId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'key' => 'objective',
            'outcome_code' => 'synthetic.governance',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_modules')->insert([
            'id' => $moduleId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'key' => 'module',
            'sequence' => 1,
            'estimated_minutes' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lessons')->insert([
            'id' => $lessonId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'curriculum_module_id' => $moduleId,
            'key' => 'lesson',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_documents')->insert([
            'id' => $contentDocumentId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'key' => 'instruction',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revisions')->insert([
            'id' => $sourceRevisionId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'content_document_id' => $contentDocumentId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'source_locale' => 'en-IN',
            'content_type' => 'instruction',
            'learner_visible' => true,
            'localization_requirement' => 'kn_required',
            'pedagogically_authoritative' => true,
            'payload' => json_encode(
                ['text' => 'Synthetic English instruction'],
                JSON_THROW_ON_ERROR,
            ),
            'content_hash' => $sourceHash,
            'authored_by_membership_id' => $authorMembershipId,
            'author_attribution' => 'synthetic-test-author',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('localized_variants')->insert([
            'id' => $localizedVariantId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'source_revision_id' => $sourceRevisionId,
            'parent_variant_id' => null,
            'locale' => 'kn-IN',
            'revision_number' => 1,
            'source_content_hash' => $sourceHash,
            'payload' => json_encode(
                ['text' => 'ಸಿಂಥೆಟಿಕ್ ಕನ್ನಡ ಸೂಚನೆ'],
                JSON_THROW_ON_ERROR,
            ),
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $translatorMembershipId,
            'translator_attribution' => 'synthetic-test-translator',
            'translated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revision_heads')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'content_document_id' => $contentDocumentId,
            'content_revision_id' => $sourceRevisionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lesson_objectives')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'lesson_id' => $lessonId,
            'objective_id' => $objectiveId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_items')->insert([
            'id' => $learningItemId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'lesson_id' => $lessonId,
            'content_document_id' => $contentDocumentId,
            'key' => 'item',
            'item_type' => 'speaking_prompt',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_missions')->insert([
            'id' => $missionId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'instruction_content_document_id' => $contentDocumentId,
            'key' => 'mission',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('mission_items')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'mission_id' => $missionId,
            'learning_item_id' => $learningItemId,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'source_revision_id' => $sourceRevisionId,
            'source_hash' => $sourceHash,
            'variant_id' => $localizedVariantId,
            'variant_hash' => $variantHash,
        ];
    }

    private function insertProgram(
        string $organizationId,
        string $creatorMembershipId,
        string $key,
    ): string {
        $programId = (string) Str::ulid();
        $now = now();

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => $key,
            'internal_name' => 'Synthetic governance test program',
            'created_by_membership_id' => $creatorMembershipId,
            'created_by_attribution' => 'synthetic-test-author',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $programId;
    }

    /**
     * @return array{id: string, hash: string}
     */
    private function createMediaAsset(
        string $organizationId,
        string $uploaderMembershipId,
    ): array {
        $assetId = (string) Str::ulid();
        $assetHash = hash('sha256', 'synthetic-media-'.$assetId);
        $now = now();

        DB::table('media_assets')->insert([
            'id' => $assetId,
            'organization_id' => $organizationId,
            'key' => 'synthetic-audio-'.strtolower($assetId),
            'asset_kind' => 'audio',
            'storage_object_key' => 'synthetic/not-a-real-media-object',
            'mime_type' => 'audio/wav',
            'byte_size' => 1,
            'asset_hash' => $assetHash,
            'uploaded_by_membership_id' => $uploaderMembershipId,
            'uploader_attribution' => 'synthetic-test-uploader',
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'id' => $assetId,
            'hash' => $assetHash,
        ];
    }

    private function createPrivilegedSession(
        User $user,
        CarbonInterface $mfaVerifiedAt,
    ): AuthenticationSession {
        return AuthenticationSession::query()->forceCreate([
            'user_id' => $user->getKey(),
            'storage_session_digest' => hash(
                'sha256',
                'synthetic-session-'.Str::ulid(),
            ),
            'storage_session_ciphertext' => 'synthetic-test-ciphertext',
            'is_privileged' => true,
            'started_at' => now()->subMinute(),
            'last_seen_at' => now(),
            'idle_expires_at' => now()->addHour(),
            'absolute_expires_at' => now()->addHours(2),
            'mfa_verified_at' => $mfaVerifiedAt,
        ]);
    }

    private function assertReviewAuthority(
        AuthorityEvidenceValidator $validator,
        string $reviewId,
    ): null {
        $validator->assertContentReviewAuthorityRemainsValid(
            $reviewId,
            now(),
        );

        return null;
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('The governance operation should have failed closed.');
        } catch (CurriculumOperationRejected) {
            return;
        }
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertIdempotencyConflict(callable $operation): void
    {
        try {
            $operation();
            self::fail(
                'A conflicting governance idempotency replay should fail.',
            );
        } catch (CurriculumIdempotencyConflict) {
            return;
        }
    }
}
