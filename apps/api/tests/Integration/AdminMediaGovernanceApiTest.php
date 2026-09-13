<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\ManagedMediaGovernanceEvidence;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

final class AdminMediaGovernanceApiTest extends TenancyIntegrationTestCase
{
    private const string OTP = '830418';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new AdminMediaGovernanceOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new AdminMediaGovernanceNullOtpDeliveryAdapter,
        );
        Storage::fake('media_quarantine');
        Storage::fake('media_accepted');
        config()->set('media.quarantine_disk', 'media_quarantine');
        config()->set('media.accepted_disks.model_audio', 'media_accepted');
        config()->set('media.accepted_disks.lesson_audio', 'media_accepted');
        config()->set('media.accepted_disks.lesson_video', 'media_accepted');
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_lesson_audio_metadata_is_audio_only_and_content_attachment_remains_closed(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T04:30:00+00:00');
        $actor = $this->signInWeb('+919310000019');
        $organization = $this->createOrganization('Lesson Audio Governance');
        [$membership, $context] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['curriculum.author', 'media.library.read', 'media.metadata.manage'],
            'lesson-audio-metadata-author',
        );
        $this->selectMembership($membership);
        $this->freshenCurrentWebSession($actor);
        $asset = $this->createManagedAsset(
            $organization,
            $membership,
            'governed long form lesson audio',
            'lesson_audio',
        );

        $metadata = $this->postCommand(
            "/api/v1/operator/media/assets/{$asset['id']}/metadata-revisions",
            [
                'expected_head_revision_id' => null,
                'expected_metadata_version' => 0,
                'operator_label' => 'Level 1 Introduction',
                'transcript' => 'I can speak in English.',
                'text_alternative' => null,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('metadata_version', 1);
        self::assertIsString($metadata->json('metadata_revision_id'));

        $this->postCommand(
            "/api/v1/operator/media/assets/{$asset['id']}/metadata-revisions",
            [
                'expected_head_revision_id' => $metadata->json('metadata_revision_id'),
                'expected_metadata_version' => 1,
                'operator_label' => 'Level 1 Introduction successor',
                'transcript' => 'I can speak in English clearly.',
                'text_alternative' => 'Audio does not accept a visual description.',
            ],
            (string) Str::ulid(),
        )->assertNotFound();

        $this->postCommand(
            "/api/v1/operator/media/assets/{$asset['id']}/attachments",
            [
                'program_id' => (string) Str::ulid(),
                'curriculum_version_id' => (string) Str::ulid(),
                'source_revision_id' => (string) Str::ulid(),
                'usage_key' => 'lesson_audio',
                'expected_workspace_version' => 1,
                'expected_lifecycle_version' => 1,
                'metadata_revision_id' => (string) Str::ulid(),
                'metadata_content_hash' => str_repeat('a', 64),
                'english_review_id' => (string) Str::ulid(),
                'metadata_variant_id' => (string) Str::ulid(),
                'variant_content_hash' => str_repeat('b', 64),
                'kannada_review_id' => (string) Str::ulid(),
                'media_rights_revision_id' => (string) Str::ulid(),
            ],
            (string) Str::ulid(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('message_key', 'problem.validation_failed');
    }

    public function test_unresolved_inaudible_marker_cannot_receive_academic_approval(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T04:45:00+00:00');
        $author = $this->signInWeb('+919310000029');
        $organization = $this->createOrganization('Inaudible Transcript Governance');
        [$authorMembership, $authorContext] = $this->createMembership(
            $organization,
            $author,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $authorMembership,
            $authorContext,
            ['curriculum.author', 'media.metadata.manage'],
            'inaudible-transcript-author',
        );
        $this->selectMembership($authorMembership);
        $this->freshenCurrentWebSession($author);
        $workspace = $this->createDailyMissionWorkspace();
        $asset = $this->createManagedAsset(
            $organization,
            $authorMembership,
            'inaudible governed long form audio',
            'lesson_audio',
        );
        $metadata = $this->postCommand(
            "/api/v1/operator/media/assets/{$asset['id']}/metadata-revisions",
            [
                'expected_head_revision_id' => null,
                'expected_metadata_version' => 0,
                'operator_label' => 'Level 1 unresolved passage',
                'transcript' => 'I can [inaudible 00:02-00:04] speak in English.',
                'text_alternative' => null,
            ],
            (string) Str::ulid(),
        )->assertCreated();
        $metadataRevisionId = $metadata->json('metadata_revision_id');
        $metadataHash = $metadata->json('content_hash');
        self::assertIsString($metadataRevisionId);
        self::assertIsString($metadataHash);

        $reviewer = $this->signInWeb('+919310000030');
        [$reviewerMembership, $reviewerContext] = $this->createMembership(
            $organization,
            $reviewer,
            Membership::TYPE_TRAINER,
        );
        $this->grantTestPermissions(
            $reviewerMembership,
            $reviewerContext,
            ['curriculum.review.en'],
            'inaudible-transcript-reviewer',
        );
        [$appointmentId] = $this->insertAcceptedAppointment(
            $organization,
            $workspace['program_id'],
            $reviewer,
            $reviewerMembership,
            $authorMembership,
            'english_academic_reviewer',
        );
        $this->selectMembership($reviewerMembership);
        $this->freshenCurrentWebSession($reviewer);

        $this->postCommand(
            '/api/v1/operator/media/metadata-reviews',
            [
                'program_id' => $workspace['program_id'],
                'metadata_revision_id' => $metadataRevisionId,
                'source_content_hash' => $metadataHash,
                'review_kind' => 'academic_en',
                'metadata_variant_id' => null,
                'variant_content_hash' => null,
                'decision' => 'approved',
                'rationale_key' => null,
                'authority_appointment_id' => $appointmentId,
            ],
            (string) Str::ulid(),
        )->assertNotFound();

        $this->assertDatabaseMissing('media_asset_metadata_reviews', [
            'organization_id' => $organization->getKey(),
            'metadata_revision_id' => $metadataRevisionId,
            'decision' => 'approved',
        ]);
    }

    public function test_accessibility_rights_attachment_replacement_and_retirement_are_exact_and_separated(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T05:00:00+00:00');
        $authorPhone = '+919310000010';
        $author = $this->signInWeb($authorPhone);
        $organization = $this->createOrganization('Governed Media Tenant');
        [$authorMembership, $authorContext] = $this->createMembership(
            $organization,
            $author,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $authorMembership,
            $authorContext,
            [
                'curriculum.author',
                'curriculum.translate.kn',
                'media.library.read',
                'media.metadata.manage',
                'media.replace',
                'media.retire',
            ],
            'governed-media-author',
        );
        $this->selectMembership($authorMembership);
        $this->freshenCurrentWebSession($author);

        $workspace = $this->createDailyMissionWorkspace();
        $asset = $this->createManagedAsset(
            $organization,
            $authorMembership,
            'first governed model audio',
        );
        $english = $this->postCommand(
            "/api/v1/operator/media/assets/{$asset['id']}/metadata-revisions",
            [
                'expected_head_revision_id' => null,
                'expected_metadata_version' => 0,
                'operator_label' => 'Greeting model audio',
                'transcript' => 'Hello, it is good to meet you.',
                'text_alternative' => null,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('metadata_version', 1);
        $metadataRevisionId = $english->json('metadata_revision_id');
        $metadataHash = $english->json('content_hash');
        self::assertIsString($metadataRevisionId);
        self::assertIsString($metadataHash);

        $this->postCommand(
            "/api/v1/operator/media/metadata-revisions/{$metadataRevisionId}/kannada-variants",
            [
                'source_content_hash' => $metadataHash,
                'expected_head_variant_id' => null,
                'expected_variant_version' => 0,
                'transcript' => 'ನಮಸ್ಕಾರ, ನಿಮ್ಮನ್ನು ಭೇಟಿಯಾಗಿ ಸಂತೋಷವಾಗಿದೆ.',
                'text_alternative' => null,
            ],
            (string) Str::ulid(),
        )->assertNotFound();

        $translator = $this->signInWeb('+919310000011');
        [$translatorMembership, $translatorContext] = $this->createMembership(
            $organization,
            $translator,
            Membership::TYPE_TRAINER,
        );
        $this->grantTestPermissions(
            $translatorMembership,
            $translatorContext,
            ['curriculum.translate.kn', 'media.metadata.manage'],
            'governed-media-translator',
        );
        $this->selectMembership($translatorMembership);
        $this->freshenCurrentWebSession($translator);
        $kannada = $this->postCommand(
            "/api/v1/operator/media/metadata-revisions/{$metadataRevisionId}/kannada-variants",
            [
                'source_content_hash' => $metadataHash,
                'expected_head_variant_id' => null,
                'expected_variant_version' => 0,
                'transcript' => 'ನಮಸ್ಕಾರ, ನಿಮ್ಮನ್ನು ಭೇಟಿಯಾಗಿ ಸಂತೋಷವಾಗಿದೆ.',
                'text_alternative' => null,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('variant_version', 1);
        $variantId = $kannada->json('metadata_variant_id');
        $variantHash = $kannada->json('content_hash');
        self::assertIsString($variantId);
        self::assertIsString($variantHash);

        $englishReviewer = $this->signInWeb('+919310000012');
        [$englishReviewerMembership, $englishReviewerContext]
            = $this->createMembership(
                $organization,
                $englishReviewer,
                Membership::TYPE_TRAINER,
            );
        $this->grantTestPermissions(
            $englishReviewerMembership,
            $englishReviewerContext,
            ['curriculum.review.en'],
            'governed-media-english-reviewer',
        );
        [$englishAppointment] = $this->insertAcceptedAppointment(
            $organization,
            $workspace['program_id'],
            $englishReviewer,
            $englishReviewerMembership,
            $authorMembership,
            'english_academic_reviewer',
        );
        $this->selectMembership($englishReviewerMembership);
        $this->freshenCurrentWebSession($englishReviewer);
        $englishReview = $this->postCommand(
            '/api/v1/operator/media/metadata-reviews',
            [
                'program_id' => $workspace['program_id'],
                'metadata_revision_id' => $metadataRevisionId,
                'source_content_hash' => $metadataHash,
                'review_kind' => 'academic_en',
                'metadata_variant_id' => null,
                'variant_content_hash' => null,
                'decision' => 'approved',
                'rationale_key' => null,
                'authority_appointment_id' => $englishAppointment,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('decision', 'approved');
        $englishReviewId = $englishReview->json(
            'media_asset_metadata_review_id',
        );
        self::assertIsString($englishReviewId);

        $kannadaReviewer = $this->signInWeb('+919310000013');
        [$kannadaReviewerMembership, $kannadaReviewerContext]
            = $this->createMembership(
                $organization,
                $kannadaReviewer,
                Membership::TYPE_TRAINER,
            );
        $this->grantTestPermissions(
            $kannadaReviewerMembership,
            $kannadaReviewerContext,
            ['curriculum.review.kn'],
            'governed-media-kannada-reviewer',
        );
        [$kannadaAppointment] = $this->insertAcceptedAppointment(
            $organization,
            $workspace['program_id'],
            $kannadaReviewer,
            $kannadaReviewerMembership,
            $authorMembership,
            'kannada_reviewer',
        );
        $this->selectMembership($kannadaReviewerMembership);
        $this->freshenCurrentWebSession($kannadaReviewer);
        $kannadaReview = $this->postCommand(
            '/api/v1/operator/media/metadata-reviews',
            [
                'program_id' => $workspace['program_id'],
                'metadata_revision_id' => $metadataRevisionId,
                'source_content_hash' => $metadataHash,
                'review_kind' => 'kannada',
                'metadata_variant_id' => $variantId,
                'variant_content_hash' => $variantHash,
                'decision' => 'approved',
                'rationale_key' => null,
                'authority_appointment_id' => $kannadaAppointment,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('decision', 'approved');
        $kannadaReviewId = $kannadaReview->json(
            'media_asset_metadata_review_id',
        );
        self::assertIsString($kannadaReviewId);

        $confirmer = $this->signInWeb('+919310000014');
        [$confirmerMembership, $confirmerContext] = $this->createMembership(
            $organization,
            $confirmer,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $confirmerMembership,
            $confirmerContext,
            ['media.rights.approve'],
            'governed-media-rights-confirmer',
        );
        [$rightsAppointment] = $this->insertAcceptedAppointment(
            $organization,
            $workspace['program_id'],
            $confirmer,
            $confirmerMembership,
            $authorMembership,
            'rights_confirmer',
        );
        $this->selectMembership($confirmerMembership);
        $this->freshenCurrentWebSession($confirmer);
        $rightsEvidenceHash = hash(
            'sha256',
            'APP-0044 governed evidence',
        );
        $rights = $this->postCommand(
            '/api/v1/operator/media/rights-decisions',
            [
                'action' => 'confirm',
                'program_id' => $workspace['program_id'],
                'media_asset_id' => $asset['id'],
                'selected_asset_hash' => $asset['hash'],
                'parent_revision_id' => null,
                'evidence_hash' => $rightsEvidenceHash,
                'rights_holder_reference' => 'project-owner',
                'provenance_reference' => 'APP-0044',
                'rights_basis_key' => 'owner_attestation',
                'license_or_contract_reference' => 'APP-0044',
                'permitted_release_channels' => ['android', 'web'],
                'permitted_territories' => ['IN'],
                'learner_delivery_allowed' => true,
                'sharing_allowed' => false,
                'derivative_use_allowed' => true,
                'effective_at' => '2026-08-10T04:59:00+00:00',
                'expires_at' => '2027-08-10T05:00:00+00:00',
                'retention_policy_key' => 'accepted_media',
                'deletion_constraint_key' => 'preserve_while_referenced',
                'authority_appointment_id' => $rightsAppointment,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('action', 'confirm');
        $rightsRevisionId = $rights->json('media_rights_revision_id');
        self::assertIsString($rightsRevisionId);

        $author = $this->signInWeb($authorPhone);
        self::assertSame($author->getKey(), $authorMembership->user_id);
        $this->selectMembership($authorMembership);
        $this->freshenCurrentWebSession($author);
        $attached = $this->postCommand(
            "/api/v1/operator/media/assets/{$asset['id']}/attachments",
            [
                'program_id' => $workspace['program_id'],
                'curriculum_version_id' => $workspace['version_id'],
                'source_revision_id' => $workspace['source_revision_id'],
                'usage_key' => 'model_audio',
                'expected_workspace_version' => 2,
                'expected_lifecycle_version' => 1,
                'metadata_revision_id' => $metadataRevisionId,
                'metadata_content_hash' => $metadataHash,
                'english_review_id' => $englishReviewId,
                'metadata_variant_id' => $variantId,
                'variant_content_hash' => $variantHash,
                'kannada_review_id' => $kannadaReviewId,
                'media_rights_revision_id' => $rightsRevisionId,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('workspace_version', 3)
            ->assertJsonPath('usage_key', 'model_audio');
        self::assertIsString($attached->json('media_attachment_event_id'));

        $managedMedia = $this->app->make(
            ManagedMediaGovernanceEvidence::class,
        );
        $evaluatedAt = CarbonImmutable::now();
        $governance = $managedMedia->selectCurrent(
            organizationId: (string) $organization->getKey(),
            programId: $workspace['program_id'],
            curriculumVersionId: $workspace['version_id'],
            sourceRevisionId: $workspace['source_revision_id'],
            mediaAssetId: $asset['id'],
            assetHash: $asset['hash'],
            usageKey: 'model_audio',
            rightsRevisionId: $rightsRevisionId,
            rightsEvidenceHash: $rightsEvidenceHash,
            evaluatedAt: $evaluatedAt,
        );
        self::assertSame(
            $asset['hash'],
            $governance['selected_scan']['object_hash'] ?? null,
        );
        self::assertSame(
            $metadataRevisionId,
            $governance['metadata']['revision_id'] ?? null,
        );
        self::assertSame(
            $englishReviewId,
            $governance['metadata']['english_review']['id'] ?? null,
        );
        self::assertSame(
            $variantId,
            $governance['metadata']['kannada_variant']['id'] ?? null,
        );
        self::assertSame(
            $kannadaReviewId,
            $governance['metadata']['kannada_variant']['review']['id']
                ?? null,
        );
        self::assertSame(
            $rightsRevisionId,
            $governance['rights']['revision_id'] ?? null,
        );

        $revalidatedAt = $evaluatedAt->addSecond();
        DB::table('authority_appointment_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'authority_appointment_id' => $englishAppointment,
            'event_type' => 'revoked_for_cause',
            'actor_membership_id' => $authorMembership->getKey(),
            'reason_key' => 'review_authority_compromised',
            'invalidates_prior_approvals' => true,
            'effective_at' => $revalidatedAt,
            'created_at' => $revalidatedAt,
            'updated_at' => $revalidatedAt,
        ]);
        $this->assertCurriculumOperationRejected(
            fn (): array => $managedMedia->selectCurrent(
                organizationId: (string) $organization->getKey(),
                programId: $workspace['program_id'],
                curriculumVersionId: $workspace['version_id'],
                sourceRevisionId: $workspace['source_revision_id'],
                mediaAssetId: $asset['id'],
                assetHash: $asset['hash'],
                usageKey: 'model_audio',
                rightsRevisionId: $rightsRevisionId,
                rightsEvidenceHash: $rightsEvidenceHash,
                evaluatedAt: $revalidatedAt,
            ),
        );
        $managedMedia->assertSnapshot(
            organizationId: (string) $organization->getKey(),
            programId: $workspace['program_id'],
            curriculumVersionId: $workspace['version_id'],
            sourceRevisionId: $workspace['source_revision_id'],
            mediaAssetId: $asset['id'],
            assetHash: $asset['hash'],
            usageKey: 'model_audio',
            rightsRevisionId: $rightsRevisionId,
            rightsEvidenceHash: $rightsEvidenceHash,
            expected: $governance,
            evaluatedAt: $revalidatedAt,
            requireCurrentSelection: false,
            validateCurrentAuthority: false,
        );

        $successor = $this->createManagedAsset(
            $organization,
            $authorMembership,
            'replacement governed model audio',
        );
        $replacement = $this->postCommand(
            "/api/v1/operator/media/assets/{$asset['id']}/replacements",
            [
                'successor_media_asset_id' => $successor['id'],
                'expected_predecessor_lifecycle_version' => 1,
                'expected_successor_lifecycle_version' => 1,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('successor_lifecycle_version', 2)
            ->assertJsonPath('authority_inherited', false);
        self::assertSame($asset['id'], $replacement->json(
            'predecessor_media_asset_id',
        ));
        $this->assertDatabaseHas('media_asset_lifecycles', [
            'organization_id' => $organization->getKey(),
            'media_asset_id' => $successor['id'],
            'predecessor_media_asset_id' => $asset['id'],
            'version' => 2,
            'state' => 'active',
        ]);
        self::assertSame(0, DB::table('media_rights_revisions')
            ->where('media_asset_id', $successor['id'])
            ->count());

        $this->postCommand(
            "/api/v1/operator/media/assets/{$asset['id']}/retirements",
            [
                'expected_lifecycle_version' => 1,
                'reason_key' => 'superseded',
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('state', 'retired')
            ->assertJsonPath('lifecycle_version', 2)
            ->assertJsonPath('existing_publications_changed', false);
        $this->assertCurriculumOperationRejected(
            function () use (
                $managedMedia,
                $organization,
                $workspace,
                $asset,
                $rightsRevisionId,
                $rightsEvidenceHash,
                $governance,
                $revalidatedAt,
            ): void {
                $managedMedia->assertSnapshot(
                    organizationId: (string) $organization->getKey(),
                    programId: $workspace['program_id'],
                    curriculumVersionId: $workspace['version_id'],
                    sourceRevisionId: $workspace['source_revision_id'],
                    mediaAssetId: $asset['id'],
                    assetHash: $asset['hash'],
                    usageKey: 'model_audio',
                    rightsRevisionId: $rightsRevisionId,
                    rightsEvidenceHash: $rightsEvidenceHash,
                    expected: $governance,
                    evaluatedAt: $revalidatedAt,
                    requireCurrentSelection: true,
                    validateCurrentAuthority: false,
                );
            },
        );
        $detail = $this->getJson(
            "/api/v1/operator/media/assets/{$asset['id']}",
        )
            ->assertOk()
            ->assertJsonPath('data.lifecycle.state', 'retired')
            ->assertJsonPath('data.metadata.id', $metadataRevisionId)
            ->assertJsonPath('data.kannada_variant.id', $variantId)
            ->assertJsonPath(
                'data.metadata_reviews.0.program_id',
                $workspace['program_id'],
            )
            ->assertJsonPath(
                'data.rights_revisions.0.id',
                $rightsRevisionId,
            )
            ->assertJsonPath(
                'data.usages.0.content_revision_id',
                $workspace['source_revision_id'],
            );
        self::assertStringContainsString(
            'private',
            (string) $detail->headers->get('Cache-Control'),
        );

        $evidence = json_encode([
            DB::table('audit_events')->get()->all(),
            DB::table('outbox_events')->get()->all(),
        ], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(
            'Hello, it is good to meet you.',
            $evidence,
        );
        self::assertStringNotContainsString(
            'ನಮಸ್ಕಾರ, ನಿಮ್ಮನ್ನು ಭೇಟಿಯಾಗಿ ಸಂತೋಷವಾಗಿದೆ.',
            $evidence,
        );
    }

    /**
     * @return array{program_id: string, version_id: string, source_revision_id: string}
     */
    private function createDailyMissionWorkspace(): array
    {
        $created = $this->postCommand(
            '/api/v1/operator/content/programs',
            [
                'program_key' => 'media-governance-program',
                'internal_name' => 'Media governance program',
                'version_key' => 'draft-1',
                'expected_version' => 0,
            ],
            (string) Str::ulid(),
        )->assertCreated();
        $programId = $created->json('program_id');
        $versionId = $created->json('curriculum_version_id');
        self::assertIsString($programId);
        self::assertIsString($versionId);
        $document = $this->postCommand(
            "/api/v1/operator/content/workspaces/{$versionId}/documents",
            [
                'document_key' => 'daily-greeting',
                'content_type' => 'instruction',
                'learner_visible' => true,
                'payload' => [
                    'schema' => 'global-english.daily-speaking-mission.v1',
                    'goal_key' => 'everyday-conversation',
                    'estimated_minutes' => 5,
                    'title' => 'Practise a greeting',
                    'model_text' => 'Hello, it is good to meet you.',
                    'transcript' => 'Hello, it is good to meet you.',
                    'can_do' => 'I can greet someone politely.',
                    'model_audio_duration_ms' => 1500,
                    'check' => [
                        'id' => 'greeting-check',
                        'prompt' => 'Which greeting is polite?',
                        'options' => [
                            ['id' => 'hello', 'label' => 'Hello'],
                            ['id' => 'silence', 'label' => 'Say nothing'],
                        ],
                        'correct_option_id' => 'hello',
                    ],
                ],
                'expected_workspace_version' => 1,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('workspace_version', 2);
        $sourceRevisionId = $document->json('source_revision_id');
        self::assertIsString($sourceRevisionId);

        return [
            'program_id' => $programId,
            'version_id' => $versionId,
            'source_revision_id' => $sourceRevisionId,
        ];
    }

    /** @return array{id: string, hash: string} */
    private function createManagedAsset(
        Organization $organization,
        Membership $manager,
        string $bytes,
        string $assetKind = 'model_audio',
    ): array {
        $assetId = (string) Str::ulid();
        $scanId = (string) Str::ulid();
        $hash = hash('sha256', $bytes);
        $objectKey = 'accepted/'.$assetKind.'/'.strtolower($assetId).'/'.$hash;
        $now = now();
        Storage::disk('media_accepted')->put($objectKey, $bytes);
        DB::table('media_assets')->insert([
            'id' => $assetId,
            'organization_id' => $organization->getKey(),
            'key' => 'managed-'.strtolower($assetId),
            'asset_kind' => $assetKind,
            'storage_object_key' => $objectKey,
            'mime_type' => 'audio/wav',
            'byte_size' => strlen($bytes),
            'asset_hash' => $hash,
            'uploaded_by_membership_id' => $manager->getKey(),
            'uploader_attribution' => 'synthetic-test-uploader',
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_security_scans')->insert([
            'id' => $scanId,
            'organization_id' => $organization->getKey(),
            'media_upload_id' => null,
            'media_asset_id' => $assetId,
            'asset_kind' => $assetKind,
            'object_hash' => $hash,
            'byte_size' => strlen($bytes),
            'inspection_outcome' => 'passed',
            'malware_outcome' => 'clean',
            'detected_mime_type' => 'audio/wav',
            'duration_milliseconds' => 1500,
            'inspector_name' => 'synthetic-test-inspector',
            'inspector_policy_version' => 'test-v1',
            'scanner_name' => 'synthetic-test-scanner',
            'scanner_engine_version' => 'test-v1',
            'scanner_policy_version' => 'test-v1',
            'technical_metadata_hash' => CanonicalJson::sha256([
                'asset_kind' => $assetKind,
                'byte_size' => strlen($bytes),
                'detected_mime_type' => 'audio/wav',
                'duration_milliseconds' => 1500,
                'inspection_outcome' => 'passed',
                'inspector_name' => 'synthetic-test-inspector',
                'inspector_policy_version' => 'test-v1',
                'malware_outcome' => 'clean',
                'object_hash' => $hash,
                'scanner_engine_version' => 'test-v1',
                'scanner_name' => 'synthetic-test-scanner',
                'scanner_policy_version' => 'test-v1',
            ]),
            'inspected_at' => $now,
            'scanned_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_asset_lifecycles')->insert([
            'organization_id' => $organization->getKey(),
            'media_asset_id' => $assetId,
            'asset_kind' => $assetKind,
            'state' => 'active',
            'version' => 1,
            'selected_scan_id' => $scanId,
            'predecessor_media_asset_id' => null,
            'managed_by_membership_id' => $manager->getKey(),
            'retired_by_membership_id' => null,
            'retirement_reason_key' => null,
            'managed_at' => $now,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $assetId, 'hash' => $hash];
    }

    /** @return array{string, string} */
    private function insertAcceptedAppointment(
        Organization $organization,
        string $programId,
        User $appointee,
        Membership $appointeeMembership,
        Membership $appointingMembership,
        string $authorityKind,
    ): array {
        $appointmentId = (string) Str::ulid();
        $appointedEventId = (string) Str::ulid();
        $acceptedEventId = (string) Str::ulid();
        $now = now();
        DB::table('authority_appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $organization->getKey(),
            'appointee_user_id' => $appointee->getKey(),
            'appointee_membership_id' => $appointeeMembership->getKey(),
            'authority_kind' => $authorityKind,
            'scope_type' => 'program',
            'program_id' => $programId,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'qualification_basis' => 'Synthetic media governance qualification',
            'approval_evidence_key' => 'APP-TEST-ADMIN-MEDIA',
            'appointed_by_membership_id' => $appointingMembership->getKey(),
            'appointed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointment_events')->insert([
            [
                'id' => $appointedEventId,
                'organization_id' => $organization->getKey(),
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'appointed',
                'actor_membership_id' => $appointingMembership->getKey(),
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => now()->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $acceptedEventId,
                'organization_id' => $organization->getKey(),
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'accepted',
                'actor_membership_id' => $appointeeMembership->getKey(),
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => now()->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return [$appointmentId, $acceptedEventId];
    }

    private function signInWeb(string $mobileNumber): User
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();

        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => $mobileNumber,
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);
        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => $mobileNumber,
                'code' => self::OTP,
            ])
            ->assertOk();
        $cookie = $verified->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
        $userId = $verified->json('user.id');
        self::assertIsString($userId);

        return User::query()->whereKey($userId)->sole();
    }

    private function selectMembership(Membership $membership): void
    {
        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $membership->getKey(),
            ])
            ->assertOk();
    }

    private function freshenCurrentWebSession(User $user): void
    {
        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->firstOrFail();
        $this->markTestAuthenticationSessionPrivileged(
            (string) $session->getKey(),
            $user,
            now(),
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function postCommand(
        string $uri,
        array $body,
        string $idempotencyKey,
    ): TestResponse {
        return $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->withHeader('Origin', 'https://localhost')
            ->postJson($uri, $body);
    }

    /** @param callable(): mixed $operation */
    private function assertCurriculumOperationRejected(
        callable $operation,
    ): void {
        try {
            $operation();
        } catch (CurriculumOperationRejected) {
            return;
        }

        self::fail('The managed media operation should have failed closed.');
    }
}

final class AdminMediaGovernanceOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class AdminMediaGovernanceNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(string $mobileNumber, string $code): void {}
}
