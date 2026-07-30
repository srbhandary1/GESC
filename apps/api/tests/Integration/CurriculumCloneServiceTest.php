<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Cloning\CurriculumCloneService;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\CurriculumPublisher;
use App\Curriculum\Sharing\CurriculumSharingService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

final class CurriculumCloneServiceTest extends TenancyIntegrationTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_recipient_clones_exact_publication_into_unapproved_draft_and_idempotent_replay(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T14:00:00+00:00');
        $fixture = $this->createSharedPublication(false);
        $cloner = $this->app->make(CurriculumCloneService::class);
        $unprivilegedActor = $this->createUser();
        [, $unprivilegedContext] = $this->createMembership(
            $fixture->recipientOrganization,
            $unprivilegedActor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $unprivilegedContext,
                fn () => $cloner->clone(
                    actor: $unprivilegedActor,
                    shareGrantId: $fixture->shareGrantId,
                    programKey: 'unauthorized-clone',
                    curriculumVersionKey: 'draft-v1',
                    idempotencyKey: 'unauthorized-clone',
                ),
            ),
        );

        $result = $this->contexts()->run(
            $fixture->recipientContext,
            fn () => $cloner->clone(
                actor: $fixture->recipientActor,
                shareGrantId: $fixture->shareGrantId,
                programKey: 'recipient-speaking-clone',
                curriculumVersionKey: 'draft-v1',
                idempotencyKey: 'clone-shared-release',
            ),
        );
        $replay = $this->contexts()->run(
            $fixture->recipientContext,
            fn () => $cloner->clone(
                actor: $fixture->recipientActor,
                shareGrantId: $fixture->shareGrantId,
                programKey: 'recipient-speaking-clone',
                curriculumVersionKey: 'draft-v1',
                idempotencyKey: 'clone-shared-release',
            ),
        );

        self::assertFalse($result->replayed);
        self::assertTrue($replay->replayed);
        self::assertSame($result->curriculumCloneId, $replay->curriculumCloneId);
        self::assertSame($result->programId, $replay->programId);
        self::assertSame(
            $result->curriculumVersionId,
            $replay->curriculumVersionId,
        );
        self::assertSame(
            $fixture->recipientOrganization->getKey(),
            $result->recipientOrganizationId,
        );
        self::assertSame(
            $fixture->ownerOrganization->getKey(),
            $result->sourceOwnerOrganizationId,
        );
        self::assertSame(
            $fixture->sourcePublicationId,
            $result->sourcePublicationId,
        );
        self::assertSame($fixture->shareGrantId, $result->sourceShareGrantId);
        self::assertNotSame($fixture->sourceProgramId, $result->programId);
        self::assertNotSame(
            $fixture->sourceCurriculumVersionId,
            $result->curriculumVersionId,
        );

        $this->assertDatabaseHas('curriculum_clones', [
            'id' => $result->curriculumCloneId,
            'organization_id' => $fixture->recipientOrganization->getKey(),
            'source_owner_organization_id' => $fixture
                ->ownerOrganization
                ->getKey(),
            'source_curriculum_version_id' => $fixture
                ->sourceCurriculumVersionId,
            'source_curriculum_publication_id' => $fixture
                ->sourcePublicationId,
            'source_share_grant_id' => $fixture->shareGrantId,
            'cloned_curriculum_version_id' => $result->curriculumVersionId,
            'cloned_by_membership_id' => $fixture
                ->recipientMembership
                ->getKey(),
        ]);
        $this->assertDatabaseHas('programs', [
            'id' => $result->programId,
            'organization_id' => $fixture->recipientOrganization->getKey(),
            'key' => 'recipient-speaking-clone',
            'internal_name' => 'Synthetic clonable program',
        ]);
        $this->assertDatabaseHas('curriculum_versions', [
            'id' => $result->curriculumVersionId,
            'organization_id' => $fixture->recipientOrganization->getKey(),
            'program_id' => $result->programId,
            'key' => 'draft-v1',
        ]);

        $sourceRevision = DB::table('content_revisions')
            ->where('organization_id', $fixture->ownerOrganization->getKey())
            ->where('id', $fixture->sourceRevisionId)
            ->first(['id', 'payload', 'content_hash']);
        $clonedRevision = DB::table('content_revisions')
            ->where(
                'organization_id',
                $fixture->recipientOrganization->getKey(),
            )
            ->where('curriculum_version_id', $result->curriculumVersionId)
            ->first([
                'id',
                'payload',
                'content_hash',
                'authored_by_membership_id',
            ]);

        self::assertInstanceOf(stdClass::class, $sourceRevision);
        self::assertInstanceOf(stdClass::class, $clonedRevision);
        self::assertNotSame($sourceRevision->id, $clonedRevision->id);
        self::assertSame(
            $this->decodedJson($sourceRevision, 'payload'),
            $this->decodedJson($clonedRevision, 'payload'),
        );
        self::assertSame(
            $sourceRevision->content_hash,
            $clonedRevision->content_hash,
        );
        self::assertSame(
            $fixture->recipientMembership->getKey(),
            $clonedRevision->authored_by_membership_id,
        );

        $sourceVariant = DB::table('localized_variants')
            ->where('organization_id', $fixture->ownerOrganization->getKey())
            ->where('id', $fixture->sourceVariantId)
            ->first(['id', 'payload', 'content_hash']);
        $clonedVariant = DB::table('localized_variants')
            ->where(
                'organization_id',
                $fixture->recipientOrganization->getKey(),
            )
            ->where('curriculum_version_id', $result->curriculumVersionId)
            ->first([
                'id',
                'payload',
                'content_hash',
                'translated_by_membership_id',
            ]);

        self::assertInstanceOf(stdClass::class, $sourceVariant);
        self::assertInstanceOf(stdClass::class, $clonedVariant);
        self::assertNotSame($sourceVariant->id, $clonedVariant->id);
        self::assertSame(
            $this->decodedJson($sourceVariant, 'payload'),
            $this->decodedJson($clonedVariant, 'payload'),
        );
        self::assertSame(
            $sourceVariant->content_hash,
            $clonedVariant->content_hash,
        );
        self::assertSame(
            $fixture->recipientMembership->getKey(),
            $clonedVariant->translated_by_membership_id,
        );

        self::assertSame(
            1,
            DB::table('curriculum_graph_revisions')
                ->where(
                    'organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where(
                    'curriculum_version_id',
                    $result->curriculumVersionId,
                )
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('content_reviews')
                ->where(
                    'organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where(
                    'curriculum_version_id',
                    $result->curriculumVersionId,
                )
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('curriculum_graph_reviews')
                ->where(
                    'organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where(
                    'curriculum_version_id',
                    $result->curriculumVersionId,
                )
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('curriculum_publications')
                ->where(
                    'organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where(
                    'curriculum_version_id',
                    $result->curriculumVersionId,
                )
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('curriculum_share_grants')
                ->where(
                    'recipient_organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where(
                    'curriculum_version_id',
                    $result->curriculumVersionId,
                )
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('enrollments')
                ->where(
                    'curriculum_owner_organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where(
                    'curriculum_version_id',
                    $result->curriculumVersionId,
                )
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('publication_commands')
                ->where(
                    'organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where('operation', 'curriculum.shared.clone')
                ->where('aggregate_id', $fixture->shareGrantId)
                ->count(),
        );
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $fixture->recipientOrganization->getKey(),
            'action' => 'curriculum.clone.created',
            'subject_id' => $result->curriculumCloneId,
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'organization_id' => $fixture->recipientOrganization->getKey(),
            'event_type' => 'curriculum.clone.created',
            'aggregate_id' => $result->curriculumCloneId,
        ]);

        $this->assertIdempotencyConflict(
            fn () => $this->contexts()->run(
                $fixture->recipientContext,
                fn () => $cloner->clone(
                    actor: $fixture->recipientActor,
                    shareGrantId: $fixture->shareGrantId,
                    programKey: 'different-program-key',
                    curriculumVersionKey: 'draft-v1',
                    idempotencyKey: 'clone-shared-release',
                ),
            ),
        );
    }

    public function test_media_bearing_publication_fails_closed_without_object_copy_semantics_or_residue(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T15:00:00+00:00');
        $fixture = $this->createSharedPublication(true);
        $cloner = $this->app->make(CurriculumCloneService::class);

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $fixture->recipientContext,
                fn () => $cloner->clone(
                    actor: $fixture->recipientActor,
                    shareGrantId: $fixture->shareGrantId,
                    programKey: 'blocked-media-clone',
                    curriculumVersionKey: 'draft-v1',
                    idempotencyKey: 'blocked-media-clone',
                ),
            ),
        );

        self::assertSame(
            0,
            DB::table('curriculum_clones')
                ->where(
                    'organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('programs')
                ->where(
                    'organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where('key', 'blocked-media-clone')
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_commands')
                ->where(
                    'organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where('operation', 'curriculum.shared.clone')
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('audit_events')
                ->where(
                    'organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where('action', 'curriculum.clone.created')
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('outbox_events')
                ->where(
                    'organization_id',
                    $fixture->recipientOrganization->getKey(),
                )
                ->where('event_type', 'curriculum.clone.created')
                ->count(),
        );
    }

    private function createSharedPublication(
        bool $withMedia,
    ): CurriculumCloneFixture {
        $owner = $this->createOrganization('Clone Source Owner');
        $ownerOperator = $this->createUser();
        [$ownerMembership, $ownerContext] = $this->createMembership(
            $owner,
            $ownerOperator,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $ownerMembership,
            $ownerContext,
            ['curriculum.publish', 'curriculum.share.manage'],
        );
        $authorMembership = $this->trainerMembership($owner);
        $translatorMembership = $this->trainerMembership($owner);
        $englishReviewer = $this->trainerMembership($owner);
        $kannadaReviewer = $this->trainerMembership($owner);
        $ownerOrganizationId = (string) $owner->getKey();
        $ownerMembershipId = (string) $ownerMembership->getKey();
        $programId = (string) Str::ulid();
        $versionId = (string) Str::ulid();
        $objectiveId = (string) Str::ulid();
        $moduleId = (string) Str::ulid();
        $lessonId = (string) Str::ulid();
        $documentId = (string) Str::ulid();
        $sourceRevisionId = (string) Str::ulid();
        $sourceVariantId = (string) Str::ulid();
        $itemId = (string) Str::ulid();
        $missionId = (string) Str::ulid();
        $now = now();
        $sourcePayload = [
            'instruction' => 'Introduce yourself in one clear sentence.',
            'review_note' => 'Synthetic clone test content.',
        ];
        $variantPayload = [
            'instruction' => 'ಒಂದು ಸ್ಪಷ್ಟ ವಾಕ್ಯದಲ್ಲಿ ನಿಮ್ಮನ್ನು ಪರಿಚಯಿಸಿಕೊಳ್ಳಿ.',
            'review_note' => 'ಸಿಂಥೆಟಿಕ್ ಕ್ಲೋನ್ ಪರೀಕ್ಷಾ ವಿಷಯ.',
        ];
        $sourceHash = CanonicalJson::sha256($sourcePayload);
        $variantHash = CanonicalJson::sha256($variantPayload);

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $ownerOrganizationId,
            'key' => 'clonable-source-program',
            'internal_name' => 'Synthetic clonable program',
            'created_by_membership_id' => $ownerMembershipId,
            'created_by_attribution' => 'synthetic-clone-test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        [$englishAppointmentId, $englishAppointmentEventId]
            = $this->insertAcceptedAppointment(
                organizationId: $ownerOrganizationId,
                programId: $programId,
                membership: $englishReviewer,
                appointedByMembershipId: $ownerMembershipId,
                authorityKind: 'english_academic_reviewer',
            );
        [$kannadaAppointmentId, $kannadaAppointmentEventId]
            = $this->insertAcceptedAppointment(
                organizationId: $ownerOrganizationId,
                programId: $programId,
                membership: $kannadaReviewer,
                appointedByMembershipId: $ownerMembershipId,
                authorityKind: 'kannada_reviewer',
            );
        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $ownerOrganizationId,
            'program_id' => $programId,
            'key' => 'published-v1',
            'created_by_membership_id' => $ownerMembershipId,
            'created_by_attribution' => 'synthetic-clone-test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_objectives')->insert([
            'id' => $objectiveId,
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'introduce-yourself',
            'outcome_code' => 'synthetic.introduce-yourself',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_modules')->insert([
            'id' => $moduleId,
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'introductions',
            'sequence' => 1,
            'estimated_minutes' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lessons')->insert([
            'id' => $lessonId,
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_module_id' => $moduleId,
            'key' => 'first-introduction',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lesson_objectives')->insert([
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'objective_id' => $objectiveId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_documents')->insert([
            'id' => $documentId,
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'introduction-instruction',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revisions')->insert([
            'id' => $sourceRevisionId,
            'organization_id' => $ownerOrganizationId,
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
            'authored_by_membership_id' => $authorMembership->getKey(),
            'author_attribution' => 'synthetic-clone-author',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revision_heads')->insert([
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'content_revision_id' => $sourceRevisionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('localized_variants')->insert([
            'id' => $sourceVariantId,
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'source_revision_id' => $sourceRevisionId,
            'parent_variant_id' => null,
            'locale' => 'kn-IN',
            'revision_number' => 1,
            'source_content_hash' => $sourceHash,
            'payload' => CanonicalJson::encode($variantPayload),
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $translatorMembership->getKey(),
            'translator_attribution' => 'synthetic-clone-translator',
            'translated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_items')->insert([
            'id' => $itemId,
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'content_document_id' => $documentId,
            'key' => 'introduction-prompt',
            'item_type' => 'speaking_prompt',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_missions')->insert([
            'id' => $missionId,
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'instruction_content_document_id' => $documentId,
            'key' => 'introduction-mission',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('mission_items')->insert([
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'mission_id' => $missionId,
            'learning_item_id' => $itemId,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $projection = $this->app
            ->make(CurriculumGraphProjector::class)
            ->project($ownerOrganizationId, $versionId);
        $graphRevisionId = (string) Str::ulid();
        DB::table('curriculum_graph_revisions')->insert([
            'id' => $graphRevisionId,
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'parent_revision_id' => null,
            'parent_revision_number' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($projection['graph']),
            'graph_hash' => $projection['graph_hash'],
            'authored_by_membership_id' => $authorMembership->getKey(),
            'author_attribution' => 'synthetic-clone-author',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_heads')->insert([
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $projection['graph_hash'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_reviews')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $ownerOrganizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $projection['graph_hash'],
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $englishAppointmentId,
            'authority_appointment_event_id' => $englishAppointmentEventId,
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $englishReviewer->getKey(),
            'decision' => 'approved',
            'rationale_key' => null,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_reviews')->insert([
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $ownerOrganizationId,
                'curriculum_version_id' => $versionId,
                'review_kind' => 'academic_en',
                'source_revision_id' => $sourceRevisionId,
                'localized_variant_id' => null,
                'authority_appointment_id' => $englishAppointmentId,
                'authority_appointment_event_id' => $englishAppointmentEventId,
                'reviewer_membership_id' => $englishReviewer->getKey(),
                'decision' => 'approved',
                'source_content_hash' => $sourceHash,
                'localized_content_hash' => null,
                'rationale_key' => null,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $ownerOrganizationId,
                'curriculum_version_id' => $versionId,
                'review_kind' => 'kannada',
                'source_revision_id' => $sourceRevisionId,
                'localized_variant_id' => $sourceVariantId,
                'authority_appointment_id' => $kannadaAppointmentId,
                'authority_appointment_event_id' => $kannadaAppointmentEventId,
                'reviewer_membership_id' => $kannadaReviewer->getKey(),
                'decision' => 'approved',
                'source_content_hash' => $sourceHash,
                'localized_content_hash' => $variantHash,
                'rationale_key' => null,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        if ($withMedia) {
            $rightsConfirmer = $this->trainerMembership($owner);
            [$rightsAppointmentId, $rightsAppointmentEventId]
                = $this->insertAcceptedAppointment(
                    organizationId: $ownerOrganizationId,
                    programId: $programId,
                    membership: $rightsConfirmer,
                    appointedByMembershipId: $ownerMembershipId,
                    authorityKind: 'rights_confirmer',
                );
            $this->insertDerivativeMedia(
                organizationId: $ownerOrganizationId,
                programId: $programId,
                curriculumVersionId: $versionId,
                sourceRevisionId: $sourceRevisionId,
                uploaderMembershipId: $ownerMembershipId,
                rightsConfirmerMembershipId: (string) $rightsConfirmer
                    ->getKey(),
                rightsAppointmentId: $rightsAppointmentId,
                rightsAppointmentEventId: $rightsAppointmentEventId,
            );
        }

        $publication = $this->contexts()->run(
            $ownerContext,
            fn () => $this->app
                ->make(CurriculumPublisher::class)
                ->publish(
                    actor: $ownerOperator,
                    curriculumVersionId: $versionId,
                    expectedHeadVersion: 0,
                    idempotencyKey: $withMedia
                        ? 'publish-media-clone-source'
                        : 'publish-clone-source',
                ),
        );
        $recipient = $this->createOrganization('Clone Recipient');
        $recipientActor = $this->createUser();
        [$recipientMembership, $recipientContext] = $this->createMembership(
            $recipient,
            $recipientActor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $recipientMembership,
            $recipientContext,
            ['curriculum.shared.clone'],
        );
        $grant = $this->contexts()->run(
            $ownerContext,
            fn () => $this->app
                ->make(CurriculumSharingService::class)
                ->issue(
                    actor: $ownerOperator,
                    publicationId: $publication->publicationId,
                    recipientOrganizationId: (string) $recipient->getKey(),
                    allowedUses: ['clone'],
                    effectiveAt: now(),
                    expiresAt: now()->addDay(),
                    idempotencyKey: $withMedia
                        ? 'share-media-clone-source'
                        : 'share-clone-source',
                ),
        );

        return new CurriculumCloneFixture(
            ownerOrganization: $owner,
            sourceProgramId: $programId,
            sourceCurriculumVersionId: $versionId,
            sourcePublicationId: $publication->publicationId,
            sourceRevisionId: $sourceRevisionId,
            sourceVariantId: $sourceVariantId,
            recipientOrganization: $recipient,
            recipientActor: $recipientActor,
            recipientMembership: $recipientMembership,
            recipientContext: $recipientContext,
            shareGrantId: $grant->shareGrantId,
        );
    }

    private function trainerMembership(
        Organization $organization,
    ): Membership {
        [$membership] = $this->createMembership(
            $organization,
            $this->createUser(),
            Membership::TYPE_TRAINER,
        );

        return $membership;
    }

    /**
     * @return array{string, string}
     */
    private function insertAcceptedAppointment(
        string $organizationId,
        string $programId,
        Membership $membership,
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
            'appointee_user_id' => $membership->user_id,
            'appointee_membership_id' => $membership->getKey(),
            'authority_kind' => $authorityKind,
            'scope_type' => 'program',
            'program_id' => $programId,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'qualification_basis' => 'Synthetic clone reviewer fixture',
            'approval_evidence_key' => 'APP-TEST-CLONE',
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
                'effective_at' => now()->subHour(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $acceptedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'accepted',
                'actor_membership_id' => $membership->getKey(),
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => now()->subHour(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return [$appointmentId, $acceptedEventId];
    }

    private function insertDerivativeMedia(
        string $organizationId,
        string $programId,
        string $curriculumVersionId,
        string $sourceRevisionId,
        string $uploaderMembershipId,
        string $rightsConfirmerMembershipId,
        string $rightsAppointmentId,
        string $rightsAppointmentEventId,
    ): void {
        $assetId = (string) Str::ulid();
        $rightsRevisionId = (string) Str::ulid();
        $assetHash = hash('sha256', 'synthetic-media-object');
        $evidenceHash = hash('sha256', 'synthetic-rights-evidence');
        $now = now();

        DB::table('media_assets')->insert([
            'id' => $assetId,
            'organization_id' => $organizationId,
            'key' => 'synthetic-clone-audio',
            'asset_kind' => 'audio',
            'storage_object_key' => 'synthetic/clone-audio.mp3',
            'mime_type' => 'audio/mpeg',
            'byte_size' => 128,
            'asset_hash' => $assetHash,
            'uploaded_by_membership_id' => $uploaderMembershipId,
            'uploader_attribution' => 'synthetic-clone-test',
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_rights_revisions')->insert([
            'id' => $rightsRevisionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'media_asset_id' => $assetId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'evidence_status' => 'approved',
            'evidence_hash' => $evidenceHash,
            'selected_asset_hash' => $assetHash,
            'rights_holder_reference' => 'party:SYNTHETIC-RIGHTS-HOLDER',
            'provenance_reference' => 'provenance:SYNTHETIC-CLONE-AUDIO',
            'rights_basis_key' => 'client_owned',
            'license_or_contract_reference' => 'SYNTHETIC-CLONE-TEST',
            'permitted_release_channels' => CanonicalJson::encode([
                'android',
                'web',
            ]),
            'permitted_territories' => CanonicalJson::encode(['IN']),
            'learner_delivery_allowed' => true,
            'sharing_allowed' => true,
            'derivative_use_allowed' => true,
            'effective_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
            'retention_policy_key' => 'synthetic_test_only',
            'deletion_constraint_key' => 'synthetic_test_only',
            'confirmed_by_membership_id' => $rightsConfirmerMembershipId,
            'rights_appointment_id' => $rightsAppointmentId,
            'rights_appointment_event_id' => $rightsAppointmentEventId,
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revision_media_assets')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'content_revision_id' => $sourceRevisionId,
            'media_asset_id' => $assetId,
            'usage_key' => 'model_audio',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
            'clone-'.strtolower((string) Str::ulid()),
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
                    'Missing synthetic clone permission: %s.',
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

        self::fail('Expected the curriculum clone operation to be rejected.');
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

        self::fail('Expected the changed clone replay to conflict.');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedJson(stdClass $row, string $field): array
    {
        $value = $row->{$field} ?? null;

        if (is_string($value)) {
            $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        }

        if (! is_array($value)) {
            throw new RuntimeException('Expected a JSON object fixture.');
        }

        return $value;
    }
}

final readonly class CurriculumCloneFixture
{
    public function __construct(
        public Organization $ownerOrganization,
        public string $sourceProgramId,
        public string $sourceCurriculumVersionId,
        public string $sourcePublicationId,
        public string $sourceRevisionId,
        public string $sourceVariantId,
        public Organization $recipientOrganization,
        public User $recipientActor,
        public Membership $recipientMembership,
        public OrganizationContext $recipientContext,
        public string $shareGrantId,
    ) {}
}
