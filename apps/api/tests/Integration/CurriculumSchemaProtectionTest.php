<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use Carbon\CarbonInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

final class CurriculumSchemaProtectionTest extends TenancyIntegrationTestCase
{
    public function test_kannada_variant_lineage_rejects_a_same_hash_cross_source_parent(): void
    {
        $fixture = $this->createCurriculumFixture('variant-source');
        $variant = $this->insertLocalizedVariant(
            $fixture,
            $fixture['source_a_id'],
            $fixture['source_hash'],
            1,
            null,
            'source-a-v1',
        );

        $this->assertDatabaseRejection(
            function () use ($fixture, $variant): void {
                $this->insertLocalizedVariant(
                    $fixture,
                    $fixture['source_b_id'],
                    $fixture['source_hash'],
                    2,
                    $variant['id'],
                    'forged-source-b-v2',
                );
            },
            '23514',
            'Localized variant parent must be the immediately preceding revision',
        );
    }

    public function test_kannada_review_rejects_a_same_hash_cross_source_variant(): void
    {
        $fixture = $this->createCurriculumFixture('review-source');
        $variant = $this->insertLocalizedVariant(
            $fixture,
            $fixture['source_a_id'],
            $fixture['source_hash'],
            1,
            null,
            'source-a-v1',
        );
        $appointment = $this->createAppointment(
            $fixture,
            'kannada_reviewer',
            'accepted',
        );

        $this->assertDatabaseRejection(
            fn (): bool => DB::table('content_reviews')->insert(
                $this->contentReviewPayload(
                    $fixture,
                    'kannada',
                    $fixture['source_b_id'],
                    $fixture['source_hash'],
                    $appointment,
                    $variant,
                ),
            ),
            '23503',
            'content_reviews_variant_tenant_fk',
        );
    }

    public function test_publication_component_rejects_a_same_hash_cross_source_kannada_evidence(): void
    {
        $fixture = $this->createCurriculumFixture('publication-source');
        $variant = $this->insertLocalizedVariant(
            $fixture,
            $fixture['source_a_id'],
            $fixture['source_hash'],
            1,
            null,
            'source-a-v1',
        );
        $academicAppointment = $this->createAppointment(
            $fixture,
            'english_academic_reviewer',
            'accepted',
        );
        $kannadaAppointment = $this->createAppointment(
            $fixture,
            'kannada_reviewer',
            'accepted',
        );
        $academicReviewId = $this->insertContentReview(
            $fixture,
            'academic_en',
            $fixture['source_b_id'],
            $fixture['source_hash'],
            $academicAppointment,
            null,
        );
        $kannadaReviewId = $this->insertContentReview(
            $fixture,
            'kannada',
            $fixture['source_a_id'],
            $fixture['source_hash'],
            $kannadaAppointment,
            $variant,
        );
        $publicationId = $this->insertPublication($fixture);

        $this->assertDatabaseRejection(
            fn (): bool => DB::table('publication_content_components')->insert([
                'organization_id' => $fixture['organization_id'],
                'program_id' => $fixture['program_id'],
                'curriculum_version_id' => $fixture['version_id'],
                'curriculum_publication_id' => $publicationId,
                'content_document_id' => $fixture['document_b_id'],
                'source_revision_id' => $fixture['source_b_id'],
                'source_content_hash' => $fixture['source_hash'],
                'academic_review_id' => $academicReviewId,
                'academic_review_kind' => 'academic_en',
                'academic_review_decision' => 'approved',
                'academic_appointment_id' => $academicAppointment['id'],
                'academic_appointment_event_id' => $academicAppointment['event_id'],
                'localized_variant_id' => $variant['id'],
                'localized_content_hash' => $variant['hash'],
                'kannada_review_id' => $kannadaReviewId,
                'kannada_review_kind' => 'kannada',
                'kannada_review_decision' => 'approved',
                'kannada_appointment_id' => $kannadaAppointment['id'],
                'kannada_appointment_event_id' => $kannadaAppointment['event_id'],
                'created_at' => now(),
            ]),
            '23503',
            'publication_content_components_variant_fk',
        );
    }

    public function test_publication_media_component_rejects_a_different_source_revision_than_the_published_content(): void
    {
        $fixture = $this->createCurriculumFixture(
            'publication-media-source',
        );
        $successor = $this->insertContentRevision(
            $fixture,
            $fixture['document_a_id'],
            $fixture['source_a_id'],
            2,
            'source-a-v2-with-media',
        );
        $asset = $this->insertMediaAsset($fixture, 'cross-revision');
        $now = now();

        DB::table('content_revision_media_assets')->insert([
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $fixture['version_id'],
            'content_revision_id' => $successor['id'],
            'media_asset_id' => $asset['id'],
            'usage_key' => 'model_audio',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $academicAppointment = $this->createAppointment(
            $fixture,
            'english_academic_reviewer',
            'accepted',
        );
        $academicReviewId = $this->insertContentReview(
            $fixture,
            'academic_en',
            $fixture['source_a_id'],
            $fixture['source_hash'],
            $academicAppointment,
            null,
        );
        $rightsAppointment = $this->createAppointment(
            $fixture,
            'rights_confirmer',
            'accepted',
        );
        $rightsRevisionId = (string) Str::ulid();
        $rights = $this->mediaRightsPayload(
            $fixture,
            $asset,
            $rightsRevisionId,
            1,
            null,
            'approved',
            $rightsAppointment,
        );
        DB::table('media_rights_revisions')->insert($rights);

        $publicationId = $this->insertPublication($fixture);
        DB::table('publication_content_components')->insert([
            'organization_id' => $fixture['organization_id'],
            'program_id' => $fixture['program_id'],
            'curriculum_version_id' => $fixture['version_id'],
            'curriculum_publication_id' => $publicationId,
            'content_document_id' => $fixture['document_a_id'],
            'source_revision_id' => $fixture['source_a_id'],
            'source_content_hash' => $fixture['source_hash'],
            'academic_review_id' => $academicReviewId,
            'academic_review_kind' => 'academic_en',
            'academic_review_decision' => 'approved',
            'academic_appointment_id' => $academicAppointment['id'],
            'academic_appointment_event_id' => $academicAppointment['event_id'],
            'localized_variant_id' => null,
            'localized_content_hash' => null,
            'kannada_review_id' => null,
            'kannada_review_kind' => null,
            'kannada_review_decision' => null,
            'kannada_appointment_id' => null,
            'kannada_appointment_event_id' => null,
            'created_at' => $now,
        ]);

        $this->assertDatabaseRejection(
            fn (): bool => DB::table('publication_media_components')->insert([
                'organization_id' => $fixture['organization_id'],
                'program_id' => $fixture['program_id'],
                'curriculum_version_id' => $fixture['version_id'],
                'curriculum_publication_id' => $publicationId,
                'content_document_id' => $fixture['document_a_id'],
                'source_revision_id' => $successor['id'],
                'media_asset_id' => $asset['id'],
                'asset_hash' => $asset['hash'],
                'media_rights_revision_id' => $rightsRevisionId,
                'rights_evidence_status' => 'approved',
                'rights_evidence_hash' => $rights['evidence_hash'],
                'usage_key' => 'model_audio',
                'sequence' => 1,
                'created_at' => $now,
            ]),
            '23503',
            'publication_media_components_content_fk',
        );
    }

    public function test_content_revision_lineage_rejects_a_nonconsecutive_parent(): void
    {
        $fixture = $this->createCurriculumFixture('content-lineage');
        $secondRevision = $this->insertContentRevision(
            $fixture,
            $fixture['document_a_id'],
            $fixture['source_a_id'],
            2,
            'source-a-v2',
        );
        self::assertNotSame($fixture['source_a_id'], $secondRevision['id']);

        $this->assertDatabaseRejection(
            fn (): array => $this->insertContentRevision(
                $fixture,
                $fixture['document_a_id'],
                $fixture['source_a_id'],
                3,
                'forged-source-a-v3',
            ),
            '23514',
            'Content revision parent must be the immediately preceding revision',
        );
    }

    public function test_localized_variant_lineage_rejects_a_nonconsecutive_parent(): void
    {
        $fixture = $this->createCurriculumFixture('variant-lineage');
        $firstVariant = $this->insertLocalizedVariant(
            $fixture,
            $fixture['source_a_id'],
            $fixture['source_hash'],
            1,
            null,
            'variant-v1',
        );
        $secondVariant = $this->insertLocalizedVariant(
            $fixture,
            $fixture['source_a_id'],
            $fixture['source_hash'],
            2,
            $firstVariant['id'],
            'variant-v2',
        );
        self::assertNotSame($firstVariant['id'], $secondVariant['id']);

        $this->assertDatabaseRejection(
            fn (): array => $this->insertLocalizedVariant(
                $fixture,
                $fixture['source_a_id'],
                $fixture['source_hash'],
                3,
                $firstVariant['id'],
                'forged-variant-v3',
            ),
            '23514',
            'Localized variant parent must be the immediately preceding revision',
        );
    }

    public function test_media_rights_lineage_rejects_a_nonconsecutive_parent(): void
    {
        $fixture = $this->createCurriculumFixture('rights-lineage');
        $asset = $this->insertMediaAsset($fixture, 'lineage');
        $firstRightsId = (string) Str::ulid();
        DB::table('media_rights_revisions')->insert(
            $this->mediaRightsPayload(
                $fixture,
                $asset,
                $firstRightsId,
                1,
                null,
                'pending',
                null,
            ),
        );
        $secondRightsId = (string) Str::ulid();
        DB::table('media_rights_revisions')->insert(
            $this->mediaRightsPayload(
                $fixture,
                $asset,
                $secondRightsId,
                2,
                $firstRightsId,
                'pending',
                null,
            ),
        );
        self::assertNotSame($firstRightsId, $secondRightsId);

        $this->assertDatabaseRejection(
            fn (): bool => DB::table('media_rights_revisions')->insert(
                $this->mediaRightsPayload(
                    $fixture,
                    $asset,
                    (string) Str::ulid(),
                    3,
                    $firstRightsId,
                    'pending',
                    null,
                ),
            ),
            '23514',
            'Media rights parent must be the immediately preceding revision',
        );
    }

    #[DataProvider('invalidGovernanceAuthorityProvider')]
    public function test_governance_evidence_rejects_wrong_or_nonaccepted_appointments(
        string $target,
        string $defect,
    ): void {
        $fixture = $this->createCurriculumFixture(
            'authority-'.$target.'-'.$defect,
        );
        $requiredKind = $target === 'content_review'
            ? 'english_academic_reviewer'
            : 'rights_confirmer';
        $appointmentKind = $defect === 'wrong_kind'
            ? ($target === 'content_review'
                ? 'rights_confirmer'
                : 'english_academic_reviewer')
            : $requiredKind;
        $eventType = $defect === 'nonaccepted_event'
            ? 'appointed'
            : 'accepted';
        $appointment = $this->createAppointment(
            $fixture,
            $appointmentKind,
            $eventType,
        );

        if ($target === 'content_review') {
            $operation = fn (): bool => DB::table('content_reviews')->insert(
                $this->contentReviewPayload(
                    $fixture,
                    'academic_en',
                    $fixture['source_a_id'],
                    $fixture['source_hash'],
                    $appointment,
                    null,
                ),
            );
            $message = 'Content review authority evidence is not an accepted appointment of the required kind';
        } else {
            $asset = $this->insertMediaAsset($fixture, $defect);
            $operation = fn (): bool => DB::table(
                'media_rights_revisions',
            )->insert(
                $this->mediaRightsPayload(
                    $fixture,
                    $asset,
                    (string) Str::ulid(),
                    1,
                    null,
                    'approved',
                    $appointment,
                ),
            );
            $message = 'Media rights authority evidence is not an accepted rights-confirmer appointment';
        }

        $this->assertDatabaseRejection(
            $operation,
            '23514',
            $message,
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidGovernanceAuthorityProvider(): array
    {
        return [
            'content review with wrong appointment kind' => [
                'content_review',
                'wrong_kind',
            ],
            'content review with nonaccepted event' => [
                'content_review',
                'nonaccepted_event',
            ],
            'media rights with wrong appointment kind' => [
                'media_rights',
                'wrong_kind',
            ],
            'media rights with nonaccepted event' => [
                'media_rights',
                'nonaccepted_event',
            ],
        ];
    }

    public function test_prepublication_query_builder_update_cannot_reassign_organization_ownership(): void
    {
        $fixture = $this->createCurriculumFixture('ownership');
        $otherOrganization = $this->createOrganization(
            'Curriculum Ownership Forgery Target',
        );

        $this->assertDatabaseRejection(
            fn (): int => DB::table('curriculum_versions')
                ->where('id', $fixture['version_id'])
                ->update([
                    'organization_id' => $otherOrganization->getKey(),
                    'updated_at' => now(),
                ]),
            '55000',
            'organization ownership is immutable',
        );
    }

    public function test_owner_emergency_event_rejects_mfa_evidence_from_after_the_event(): void
    {
        $fixture = $this->createCurriculumFixture(
            'owner-emergency-future-mfa',
        );
        $publicationId = $this->insertPublication($fixture);
        $effectiveAt = now();
        $session = $this->createPrivilegedSession(
            $fixture['user_id'],
            $effectiveAt->copy()->addMinute(),
        );

        $this->assertDatabaseRejection(
            fn (): bool => DB::table('publication_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $fixture['organization_id'],
                'program_id' => $fixture['program_id'],
                'curriculum_version_id' => $fixture['version_id'],
                'curriculum_publication_id' => $publicationId,
                'event_type' => 'suspended',
                'previous_curriculum_version_id' => null,
                'previous_publication_id' => null,
                'actor_type' => 'owner_membership',
                'actor_membership_id' => $fixture['membership_id'],
                'platform_actor_user_id' => null,
                'authentication_session_id' => $session->getKey(),
                'platform_audit_event_id' => null,
                'reason_key' => 'future_mfa_must_fail',
                'remediation_reference' => 'runbook.future-mfa.v1',
                'correlation_id' => (string) Str::uuid(),
                'effective_at' => $effectiveAt,
                'created_at' => $effectiveAt,
            ]),
            '23514',
            'Owner emergency evidence is not bound to the accountable actor session',
        );
    }

    public function test_platform_emergency_event_rejects_an_unrelated_platform_audit(): void
    {
        $fixture = $this->createCurriculumFixture(
            'platform-emergency-audit',
        );
        $publicationId = $this->insertPublication($fixture);
        $platformActor = $this->createUser();
        $effectiveAt = now();
        $session = $this->createPrivilegedSession(
            (string) $platformActor->getKey(),
            $effectiveAt,
        );
        $unrelatedOrganization = $this->createOrganization(
            'Unrelated Emergency Audit Target',
        );
        $auditEventId = (string) Str::ulid();
        $correlationId = (string) Str::uuid();

        DB::table('audit_events')->insert([
            'id' => $auditEventId,
            'scope_kind' => 'platform',
            'organization_id' => null,
            'actor_user_id' => $platformActor->getKey(),
            'actor_membership_id' => null,
            'target_organization_id' => $unrelatedOrganization->getKey(),
            'capability_key' => 'content.emergency-suspend',
            'reason_key' => 'legal_hold',
            'action' => 'curriculum.publication.suspended',
            'subject_type' => 'program',
            'subject_id' => $fixture['program_id'],
            'correlation_id' => $correlationId,
            'occurred_at' => $effectiveAt,
        ]);

        $this->assertDatabaseRejection(
            fn (): bool => DB::table('publication_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $fixture['organization_id'],
                'program_id' => $fixture['program_id'],
                'curriculum_version_id' => $fixture['version_id'],
                'curriculum_publication_id' => $publicationId,
                'event_type' => 'suspended',
                'previous_curriculum_version_id' => null,
                'previous_publication_id' => null,
                'actor_type' => 'platform_user',
                'actor_membership_id' => null,
                'platform_actor_user_id' => $platformActor->getKey(),
                'authentication_session_id' => $session->getKey(),
                'platform_audit_event_id' => $auditEventId,
                'reason_key' => 'legal_hold',
                'remediation_reference' => 'runbook.legal-hold.v1',
                'correlation_id' => $correlationId,
                'effective_at' => $effectiveAt,
                'created_at' => $effectiveAt,
            ]),
            '23514',
            'Platform emergency evidence is not bound to the exact authorization audit',
        );
    }

    public function test_rolling_publication_migration_down_removes_program_append_only_trigger(): void
    {
        self::assertSame(1, $this->programAppendOnlyTriggerCount());

        $learningMigration = require database_path(
            'migrations/2026_07_30_000024_create_deterministic_learning_tables.php',
        );
        if (
            ! $learningMigration instanceof Migration
            || ! method_exists($learningMigration, 'down')
        ) {
            self::fail('The deterministic learning migration must provide down().');
        }
        $learningMigration->down();

        $pinMigration = require database_path(
            'migrations/2026_07_30_000023_pin_enrollments_to_curriculum_publications.php',
        );
        if (
            ! $pinMigration instanceof Migration
            || ! method_exists($pinMigration, 'down')
        ) {
            self::fail('The enrollment pin migration must provide down().');
        }
        $pinMigration->down();

        $publicationMigration = require database_path(
            'migrations/2026_07_30_000022_create_curriculum_publication_tables.php',
        );
        if (
            ! $publicationMigration instanceof Migration
            || ! method_exists($publicationMigration, 'down')
        ) {
            self::fail('The publication migration must provide down().');
        }
        $publicationMigration->down();

        self::assertSame(0, $this->programAppendOnlyTriggerCount());
    }

    /**
     * @return array{
     *     organization_id: string,
     *     membership_id: string,
     *     user_id: string,
     *     program_id: string,
     *     version_id: string,
     *     document_a_id: string,
     *     document_b_id: string,
     *     source_a_id: string,
     *     source_b_id: string,
     *     source_hash: string
     * }
     */
    private function createCurriculumFixture(string $key): array
    {
        $organization = $this->createOrganization(
            'Curriculum Schema '.Str::headline($key),
        );
        $user = $this->createUser();
        [$membership] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $now = now();
        $programId = (string) Str::ulid();
        $versionId = (string) Str::ulid();
        $documentAId = (string) Str::ulid();
        $documentBId = (string) Str::ulid();
        $sourceAId = (string) Str::ulid();
        $sourceBId = (string) Str::ulid();
        $payload = [
            'instruction' => 'This deliberate same-hash fixture tests exact source identity.',
        ];
        $sourceHash = CanonicalJson::sha256($payload);

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organization->getKey(),
            'key' => $key,
            'internal_name' => 'Schema protection fixture',
            'created_by_membership_id' => $membership->getKey(),
            'created_by_attribution' => 'synthetic_schema_protection_test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $organization->getKey(),
            'program_id' => $programId,
            'key' => 'draft-1',
            'created_by_membership_id' => $membership->getKey(),
            'created_by_attribution' => 'synthetic_schema_protection_test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            [$documentAId, 'source-a', $sourceAId],
            [$documentBId, 'source-b', $sourceBId],
        ] as [$documentId, $documentKey, $sourceRevisionId]) {
            DB::table('content_documents')->insert([
                'id' => $documentId,
                'organization_id' => $organization->getKey(),
                'curriculum_version_id' => $versionId,
                'key' => $documentKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('content_revisions')->insert([
                'id' => $sourceRevisionId,
                'organization_id' => $organization->getKey(),
                'curriculum_version_id' => $versionId,
                'content_document_id' => $documentId,
                'parent_revision_id' => null,
                'revision_number' => 1,
                'source_locale' => 'en-IN',
                'content_type' => 'instruction',
                'learner_visible' => true,
                'localization_requirement' => 'kn_required',
                'pedagogically_authoritative' => true,
                'payload' => CanonicalJson::encode($payload),
                'content_hash' => $sourceHash,
                'authored_by_membership_id' => $membership->getKey(),
                'author_attribution' => 'synthetic_schema_protection_test',
                'authored_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [
            'organization_id' => (string) $organization->getKey(),
            'membership_id' => (string) $membership->getKey(),
            'user_id' => (string) $user->getKey(),
            'program_id' => $programId,
            'version_id' => $versionId,
            'document_a_id' => $documentAId,
            'document_b_id' => $documentBId,
            'source_a_id' => $sourceAId,
            'source_b_id' => $sourceBId,
            'source_hash' => $sourceHash,
        ];
    }

    /**
     * @param  array{
     *     organization_id: string,
     *     membership_id: string,
     *     version_id: string
     * }  $fixture
     * @return array{id: string, hash: string}
     */
    private function insertContentRevision(
        array $fixture,
        string $documentId,
        string $parentRevisionId,
        int $revisionNumber,
        string $payloadKey,
    ): array {
        $id = (string) Str::ulid();
        $payload = ['instruction' => $payloadKey];
        $hash = CanonicalJson::sha256($payload);
        $now = now();

        DB::table('content_revisions')->insert([
            'id' => $id,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $fixture['version_id'],
            'content_document_id' => $documentId,
            'parent_revision_id' => $parentRevisionId,
            'revision_number' => $revisionNumber,
            'source_locale' => 'en-IN',
            'content_type' => 'instruction',
            'learner_visible' => true,
            'localization_requirement' => 'kn_required',
            'pedagogically_authoritative' => true,
            'payload' => CanonicalJson::encode($payload),
            'content_hash' => $hash,
            'authored_by_membership_id' => $fixture['membership_id'],
            'author_attribution' => 'synthetic_schema_protection_test',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $id, 'hash' => $hash];
    }

    /**
     * @param  array{
     *     organization_id: string,
     *     membership_id: string,
     *     version_id: string
     * }  $fixture
     * @return array{id: string, hash: string}
     */
    private function insertLocalizedVariant(
        array $fixture,
        string $sourceRevisionId,
        string $sourceHash,
        int $revisionNumber,
        ?string $parentVariantId,
        string $payloadKey,
    ): array {
        $id = (string) Str::ulid();
        $payload = ['guidance' => $payloadKey];
        $hash = CanonicalJson::sha256($payload);
        $now = now();

        DB::table('localized_variants')->insert([
            'id' => $id,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $fixture['version_id'],
            'source_revision_id' => $sourceRevisionId,
            'parent_variant_id' => $parentVariantId,
            'locale' => 'kn-IN',
            'revision_number' => $revisionNumber,
            'source_content_hash' => $sourceHash,
            'payload' => CanonicalJson::encode($payload),
            'content_hash' => $hash,
            'translated_by_membership_id' => $fixture['membership_id'],
            'translator_attribution' => 'synthetic_schema_protection_test',
            'translated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $id, 'hash' => $hash];
    }

    /**
     * @param  array{
     *     organization_id: string,
     *     membership_id: string,
     *     user_id: string,
     *     program_id: string
     * }  $fixture
     * @return array{id: string, event_id: string}
     */
    private function createAppointment(
        array $fixture,
        string $authorityKind,
        string $eventType,
    ): array {
        $appointmentId = (string) Str::ulid();
        $eventId = (string) Str::ulid();
        $now = now();

        DB::table('authority_appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $fixture['organization_id'],
            'appointee_user_id' => $fixture['user_id'],
            'appointee_membership_id' => $fixture['membership_id'],
            'authority_kind' => $authorityKind,
            'scope_type' => 'program',
            'program_id' => $fixture['program_id'],
            'valid_from' => $now->copy()->subMinute(),
            'valid_until' => null,
            'qualification_basis' => 'Synthetic schema protection fixture',
            'approval_evidence_key' => 'schema-protection-'.$appointmentId,
            'appointed_by_membership_id' => $fixture['membership_id'],
            'appointed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointment_events')->insert([
            'id' => $eventId,
            'organization_id' => $fixture['organization_id'],
            'authority_appointment_id' => $appointmentId,
            'event_type' => $eventType,
            'actor_membership_id' => $fixture['membership_id'],
            'reason_key' => null,
            'invalidates_prior_approvals' => false,
            'effective_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $appointmentId, 'event_id' => $eventId];
    }

    /**
     * @param  array{
     *     organization_id: string,
     *     membership_id: string,
     *     version_id: string
     * }  $fixture
     * @param  array{id: string, event_id: string}  $appointment
     * @param  array{id: string, hash: string}|null  $variant
     * @return array<string, mixed>
     */
    private function contentReviewPayload(
        array $fixture,
        string $reviewKind,
        string $sourceRevisionId,
        string $sourceHash,
        array $appointment,
        ?array $variant,
    ): array {
        $now = now();

        return [
            'id' => (string) Str::ulid(),
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $fixture['version_id'],
            'review_kind' => $reviewKind,
            'source_revision_id' => $sourceRevisionId,
            'localized_variant_id' => $variant['id'] ?? null,
            'authority_appointment_id' => $appointment['id'],
            'authority_appointment_event_id' => $appointment['event_id'],
            'reviewer_membership_id' => $fixture['membership_id'],
            'decision' => 'approved',
            'source_content_hash' => $sourceHash,
            'localized_content_hash' => $variant['hash'] ?? null,
            'rationale_key' => 'synthetic_schema_protection_test',
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array{
     *     organization_id: string,
     *     membership_id: string,
     *     version_id: string
     * }  $fixture
     * @param  array{id: string, event_id: string}  $appointment
     * @param  array{id: string, hash: string}|null  $variant
     */
    private function insertContentReview(
        array $fixture,
        string $reviewKind,
        string $sourceRevisionId,
        string $sourceHash,
        array $appointment,
        ?array $variant,
    ): string {
        $payload = $this->contentReviewPayload(
            $fixture,
            $reviewKind,
            $sourceRevisionId,
            $sourceHash,
            $appointment,
            $variant,
        );
        DB::table('content_reviews')->insert($payload);

        return (string) $payload['id'];
    }

    /**
     * @param  array{
     *     organization_id: string,
     *     membership_id: string,
     *     program_id: string,
     *     version_id: string
     * }  $fixture
     */
    private function insertPublication(array $fixture): string
    {
        $id = (string) Str::ulid();
        $manifest = CanonicalJson::encode([
            'fixture' => 'curriculum-schema-protection',
            'publication_id' => $id,
        ]);

        DB::table('curriculum_publications')->insert([
            'id' => $id,
            'organization_id' => $fixture['organization_id'],
            'program_id' => $fixture['program_id'],
            'curriculum_version_id' => $fixture['version_id'],
            'publication_number' => 1,
            'manifest_schema_version' => 1,
            'manifest' => $manifest,
            'manifest_hash' => hash('sha256', $manifest),
            'created_by_membership_id' => $fixture['membership_id'],
            'created_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array{organization_id: string, membership_id: string}  $fixture
     * @return array{id: string, hash: string}
     */
    private function insertMediaAsset(array $fixture, string $key): array
    {
        $id = (string) Str::ulid();
        $hash = hash('sha256', 'schema-protection-media-'.$key);
        $now = now();

        DB::table('media_assets')->insert([
            'id' => $id,
            'organization_id' => $fixture['organization_id'],
            'key' => 'schema-protection-'.$key,
            'asset_kind' => 'audio',
            'storage_object_key' => 'synthetic/'.$id.'.mp3',
            'mime_type' => 'audio/mpeg',
            'byte_size' => 1024,
            'asset_hash' => $hash,
            'uploaded_by_membership_id' => $fixture['membership_id'],
            'uploader_attribution' => 'synthetic_schema_protection_test',
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $id, 'hash' => $hash];
    }

    /**
     * @param  array{
     *     organization_id: string,
     *     membership_id: string,
     *     program_id: string
     * }  $fixture
     * @param  array{id: string, hash: string}  $asset
     * @param  array{id: string, event_id: string}|null  $appointment
     * @return array<string, mixed>
     */
    private function mediaRightsPayload(
        array $fixture,
        array $asset,
        string $id,
        int $revisionNumber,
        ?string $parentRevisionId,
        string $status,
        ?array $appointment,
    ): array {
        $now = now();

        return [
            'id' => $id,
            'organization_id' => $fixture['organization_id'],
            'program_id' => $fixture['program_id'],
            'media_asset_id' => $asset['id'],
            'parent_revision_id' => $parentRevisionId,
            'revision_number' => $revisionNumber,
            'evidence_status' => $status,
            'evidence_hash' => hash(
                'sha256',
                'schema-rights-'.$id.'-'.$status,
            ),
            'selected_asset_hash' => $asset['hash'],
            'rights_holder_reference' => 'synthetic:rights-holder',
            'provenance_reference' => 'synthetic:provenance',
            'rights_basis_key' => 'synthetic_test_release',
            'license_or_contract_reference' => 'synthetic:release',
            'permitted_release_channels' => CanonicalJson::encode([
                'android',
                'web',
            ]),
            'permitted_territories' => CanonicalJson::encode(['IN']),
            'learner_delivery_allowed' => true,
            'sharing_allowed' => false,
            'derivative_use_allowed' => false,
            'effective_at' => $now,
            'expires_at' => null,
            'retention_policy_key' => 'retain_while_referenced',
            'deletion_constraint_key' => 'publication_pin_protected',
            'confirmed_by_membership_id' => $appointment === null
                ? null
                : $fixture['membership_id'],
            'rights_appointment_id' => $appointment['id'] ?? null,
            'rights_appointment_event_id' => $appointment['event_id'] ?? null,
            'confirmed_at' => $appointment === null ? null : $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function createPrivilegedSession(
        string $userId,
        CarbonInterface $mfaVerifiedAt,
    ): AuthenticationSession {
        return AuthenticationSession::query()->forceCreate([
            'user_id' => $userId,
            'storage_session_digest' => hash(
                'sha256',
                'schema-protection-session-'.Str::ulid(),
            ),
            'storage_session_ciphertext' => 'synthetic-test-session',
            'is_privileged' => true,
            'started_at' => now()->subMinute(),
            'last_seen_at' => now(),
            'idle_expires_at' => now()->addMinutes(30),
            'absolute_expires_at' => now()->addHours(6),
            'mfa_verified_at' => $mfaVerifiedAt,
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertDatabaseRejection(
        callable $operation,
        string $sqlState,
        string $messageFragment,
    ): void {
        try {
            $operation();
            self::fail('The forged database operation must be rejected.');
        } catch (QueryException $exception) {
            self::assertSame($sqlState, (string) $exception->getCode());
            self::assertStringContainsString(
                $messageFragment,
                $exception->getMessage(),
            );
        }
    }

    private function programAppendOnlyTriggerCount(): int
    {
        return (int) DB::scalar(<<<'SQL'
SELECT COUNT(*)
FROM pg_trigger trigger
JOIN pg_class relation ON relation.oid = trigger.tgrelid
WHERE relation.relname = 'programs'
  AND trigger.tgname = 'programs_append_only'
  AND trigger.tgisinternal = FALSE
SQL);
    }
}
