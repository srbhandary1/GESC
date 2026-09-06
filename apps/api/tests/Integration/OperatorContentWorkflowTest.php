<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class OperatorContentWorkflowTest extends TenancyIntegrationTestCase
{
    private const string OTP = '246813';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new OperatorContentWorkflowOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new OperatorContentWorkflowNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_operator_can_review_publish_and_list_audit_with_tenant_isolation(): void
    {
        $user = $this->signInWeb('+919876543220');
        $organization = $this->createOrganization('Operator Content Tenant');
        $otherOrganization = $this->createOrganization(
            'Operator Foreign Tenant',
        );
        $fixture = $this->createReviewableFixture(
            $organization,
            $user,
        );
        $foreign = $this->createReviewableFixture(
            $otherOrganization,
            $this->createUser(),
        );

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $fixture['operator_membership_id'],
            ])
            ->assertOk()
            ->assertJsonPath(
                'current_membership.id',
                $fixture['operator_membership_id'],
            );

        $drafts = $this->getJson('/api/v1/operator/content/drafts')
            ->assertOk()
            ->assertJsonPath(
                'items.0.curriculum_version_id',
                $fixture['version_id'],
            )
            ->assertJsonPath('items.0.latest_english_decision', null)
            ->assertJsonPath('items.0.latest_kannada_decision', null)
            ->assertJsonPath('items.0.publication_status', 'unpublished');
        $this->assertNoStore($drafts);

        $analytics = $this->getJson('/api/v1/operator/pilot-analytics')
            ->assertOk()
            ->assertJsonPath(
                'distinction_note',
                'usage_counts_are_not_demonstrated_learning',
            )
            ->assertJsonStructure([
                'generated_at',
                'usage' => [
                    'learner_membership_count',
                    'conversation_session_count',
                    'conversation_sessions_by_status',
                    'formative_attempt_count',
                    'formative_attempts_by_status',
                ],
                'demonstrated_learning' => [
                    'mastery_state_count',
                    'mastery_by_knowledge_state',
                    'review_schedule_count',
                ],
                'distinction_note',
            ]);
        $this->assertNoStore($analytics);
        self::assertIsInt(
            $analytics->json('usage.learner_membership_count'),
        );

        $english = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/content/reviews/english', [
            'source_revision_id' => $fixture['source_revision_id'],
            'source_content_hash' => $fixture['source_hash'],
            'authority_appointment_id' => $fixture['english_appointment_id'],
            'decision' => 'approved',
        ])
            ->assertCreated()
            ->assertJsonStructure(['content_review_id']);
        $this->assertNoStore($english);

        $kannada = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/content/reviews/kannada', [
            'source_revision_id' => $fixture['source_revision_id'],
            'source_content_hash' => $fixture['source_hash'],
            'localized_variant_id' => $fixture['variant_id'],
            'localized_content_hash' => $fixture['variant_hash'],
            'authority_appointment_id' => $fixture['kannada_appointment_id'],
            'decision' => 'approved',
        ])
            ->assertCreated()
            ->assertJsonStructure(['content_review_id']);
        $this->assertNoStore($kannada);

        $published = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/content/publications', [
            'curriculum_version_id' => $fixture['version_id'],
            'expected_head_version' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath(
                'curriculum_version_id',
                $fixture['version_id'],
            )
            ->assertJsonPath('head_version', 1);
        $this->assertNoStore($published);
        $publicationId = $published->json('publication_id');
        self::assertIsString($publicationId);

        $this->getJson('/api/v1/operator/content/publications')
            ->assertOk()
            ->assertJsonPath('items.0.publication_id', $publicationId)
            ->assertJsonPath('items.0.is_head', true);

        $audit = $this->getJson('/api/v1/operator/audit-events?limit=20')
            ->assertOk()
            ->assertJsonStructure([
                'items' => [
                    [
                        'id',
                        'action',
                        'subject_type',
                        'subject_id',
                        'actor_membership_id',
                        'correlation_id',
                        'occurred_at',
                    ],
                ],
            ]);
        $this->assertNoStore($audit);
        $actions = collect($audit->json('items'))
            ->pluck('action')
            ->all();
        self::assertContains(
            'curriculum.content_review.recorded',
            $actions,
        );
        self::assertContains(
            'curriculum.publication.activated',
            $actions,
        );
        foreach ($audit->json('items') as $event) {
            self::assertIsArray($event);
            self::assertArrayNotHasKey('payload', $event);
            self::assertArrayNotHasKey('transcript', $event);
        }

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/content/reviews/english', [
            'source_revision_id' => $foreign['source_revision_id'],
            'source_content_hash' => $foreign['source_hash'],
            'authority_appointment_id' => $foreign['english_appointment_id'],
            'decision' => 'approved',
        ])->assertNotFound();

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/content/publications', [
            'curriculum_version_id' => $foreign['version_id'],
            'expected_head_version' => 0,
        ])->assertNotFound();

        $secondVersion = $this->createAdditionalPublishableVersion(
            $fixture,
            'v2',
        );
        $secondPublished = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/content/publications', [
            'curriculum_version_id' => $secondVersion['version_id'],
            'expected_head_version' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('head_version', 2);
        self::assertNotSame(
            $publicationId,
            $secondPublished->json('publication_id'),
        );

        $rollback = $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/content/rollbacks', [
            'program_id' => $fixture['program_id'],
            'target_publication_id' => $publicationId,
            'expected_head_version' => 2,
            'reason_key' => 'operator_console_rollback',
        ])
            ->assertCreated()
            ->assertJsonPath('publication_id', $publicationId)
            ->assertJsonPath('head_version', 3)
            ->assertJsonPath(
                'curriculum_version_id',
                $fixture['version_id'],
            );
        $this->assertNoStore($rollback);

        $learnerOnly = $this->signInWeb('+919876543221');
        [$learnerOperatorMembership] = $this->createMembership(
            Organization::query()->findOrFail($fixture['organization_id']),
            $learnerOnly,
            Membership::TYPE_TRAINER,
        );
        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => (string) $learnerOperatorMembership
                    ->getKey(),
            ])
            ->assertOk();
        $this->getJson('/api/v1/operator/content/drafts')->assertNotFound();
        $this->getJson('/api/v1/operator/audit-events')->assertNotFound();
        $this->getJson('/api/v1/operator/pilot-analytics')->assertNotFound();
        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/v1/operator/content/publications', [
            'curriculum_version_id' => $fixture['version_id'],
            'expected_head_version' => 3,
        ])->assertNotFound();
    }

    public function test_foundation_seed_operator_role_packs_grant_expected_permissions(): void
    {
        $direct = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->sole();
        $roleKeys = [
            Role::DIRECT_TRAINER_KEY,
            Role::DIRECT_ORGANIZATION_ADMIN_KEY,
            Role::DIRECT_CONTENT_REVIEWER_KEY,
        ];

        foreach ($roleKeys as $roleKey) {
            $this->assertDatabaseHas('roles', [
                'organization_id' => $direct->getKey(),
                'key' => $roleKey,
                'is_system' => true,
            ]);
        }

        $adminRoleId = DB::table('roles')
            ->where('organization_id', $direct->getKey())
            ->where('key', Role::DIRECT_ORGANIZATION_ADMIN_KEY)
            ->value('id');
        self::assertIsString($adminRoleId);
        $permissionKeys = DB::table('role_permissions')
            ->join(
                'permissions',
                'permissions.id',
                '=',
                'role_permissions.permission_id',
            )
            ->where('role_permissions.role_id', $adminRoleId)
            ->orderBy('permissions.key')
            ->pluck('permissions.key')
            ->all();
        self::assertSame(
            [
                'audit.read',
                'curriculum.authority.manage',
                'curriculum.publish',
                'curriculum.share.manage',
                'enrollments.curriculum.assign',
                'enrollments.manage',
                'enrollments.read',
                'entitlement_plans.manage',
                'entitlement_plans.read',
                'entitlements.adjust',
                'entitlements.grant',
                'entitlements.read',
                'entitlements.revoke',
                'institution_invites.manage',
                'learners.lifecycle.manage',
                'learners.profile.correct',
                'learners.read',
                'media.library.read',
                'media.metadata.manage',
                'media.quarantine.review',
                'media.replace',
                'media.retire',
                'media.upload',
                'membership_sessions.revoke',
                'memberships.manage',
                'memberships.read',
                'organization.settings.read',
                'privacy.erasure.request',
                'privacy.export.request',
                'roles.manage',
                'support.diagnostics.read',
                'trainer_profiles.manage',
                'trainer_profiles.read',
            ],
            $permissionKeys,
        );
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

    /**
     * @return array{
     *   organization_id: string,
     *   operator_membership_id: string,
     *   operator_context: OrganizationContext,
     *   program_id: string,
     *   version_id: string,
     *   source_revision_id: string,
     *   source_hash: string,
     *   variant_id: string,
     *   variant_hash: string,
     *   english_appointment_id: string,
     *   kannada_appointment_id: string,
     *   author_membership_id: string,
     *   translator_membership_id: string,
     *   english_reviewer_membership_id: string,
     *   kannada_reviewer_membership_id: string,
     *   english_appointment_event_id: string,
     *   kannada_appointment_event_id: string
     * }
     */
    private function createReviewableFixture(
        Organization $organization,
        User $operator,
    ): array {
        [$operatorMembership, $operatorContext] = $this->createMembership(
            $organization,
            $operator,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->attachOperatorPermissions(
            $operatorMembership,
            $operatorContext,
            [
                'curriculum.review.en',
                'curriculum.review.kn',
                'curriculum.publish',
                'audit.read',
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
        $programId = (string) Str::ulid();
        $organizationId = (string) $organization->getKey();
        $operatorMembershipId = (string) $operatorMembership->getKey();
        $now = now();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => 'operator-'.strtolower((string) Str::ulid()),
            'internal_name' => 'Operator console synthetic program',
            'created_by_membership_id' => $operatorMembershipId,
            'created_by_attribution' => 'operator-console-test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        [$englishAppointmentId, $englishAppointmentEventId]
            = $this->insertAcceptedAppointment(
                organizationId: $organizationId,
                programId: $programId,
                appointeeUserId: (string) $operator->getKey(),
                appointeeMembershipId: $operatorMembershipId,
                appointedByMembershipId: $operatorMembershipId,
                authorityKind: 'english_academic_reviewer',
            );
        [$kannadaAppointmentId, $kannadaAppointmentEventId]
            = $this->insertAcceptedAppointment(
                organizationId: $organizationId,
                programId: $programId,
                appointeeUserId: (string) $operator->getKey(),
                appointeeMembershipId: $operatorMembershipId,
                appointedByMembershipId: $operatorMembershipId,
                authorityKind: 'kannada_reviewer',
            );
        $version = $this->insertReviewableVersion(
            organizationId: $organizationId,
            programId: $programId,
            versionKey: 'v1',
            authorMembershipId: (string) $authorMembership->getKey(),
            translatorMembershipId: (string) $translatorMembership->getKey(),
            englishAppointmentId: $englishAppointmentId,
            englishAppointmentEventId: $englishAppointmentEventId,
            englishReviewerMembershipId: $operatorMembershipId,
            includeContentReviews: false,
        );

        return [
            'organization_id' => $organizationId,
            'operator_membership_id' => $operatorMembershipId,
            'operator_context' => $operatorContext,
            'program_id' => $programId,
            'version_id' => $version['version_id'],
            'source_revision_id' => $version['source_revision_id'],
            'source_hash' => $version['source_hash'],
            'variant_id' => $version['variant_id'],
            'variant_hash' => $version['variant_hash'],
            'english_appointment_id' => $englishAppointmentId,
            'kannada_appointment_id' => $kannadaAppointmentId,
            'author_membership_id' => (string) $authorMembership->getKey(),
            'translator_membership_id' => (string) $translatorMembership
                ->getKey(),
            'english_reviewer_membership_id' => $operatorMembershipId,
            'kannada_reviewer_membership_id' => $operatorMembershipId,
            'english_appointment_event_id' => $englishAppointmentEventId,
            'kannada_appointment_event_id' => $kannadaAppointmentEventId,
        ];
    }

    /**
     * @param  array{
     *   organization_id: string,
     *   program_id: string,
     *   author_membership_id: string,
     *   translator_membership_id: string,
     *   english_appointment_id: string,
     *   english_appointment_event_id: string,
     *   english_reviewer_membership_id: string,
     *   kannada_appointment_id: string,
     *   kannada_appointment_event_id: string,
     *   kannada_reviewer_membership_id: string
     * }  $fixture
     * @return array{version_id: string}
     */
    private function createAdditionalPublishableVersion(
        array $fixture,
        string $versionKey,
    ): array {
        $version = $this->insertReviewableVersion(
            organizationId: $fixture['organization_id'],
            programId: $fixture['program_id'],
            versionKey: $versionKey,
            authorMembershipId: $fixture['author_membership_id'],
            translatorMembershipId: $fixture['translator_membership_id'],
            englishAppointmentId: $fixture['english_appointment_id'],
            englishAppointmentEventId: $fixture['english_appointment_event_id'],
            englishReviewerMembershipId: $fixture[
                'english_reviewer_membership_id'
            ],
            includeContentReviews: true,
            kannadaAppointmentId: $fixture['kannada_appointment_id'],
            kannadaAppointmentEventId: $fixture[
                'kannada_appointment_event_id'
            ],
            kannadaReviewerMembershipId: $fixture[
                'kannada_reviewer_membership_id'
            ],
        );

        return ['version_id' => $version['version_id']];
    }

    /**
     * @return array{
     *   version_id: string,
     *   source_revision_id: string,
     *   source_hash: string,
     *   variant_id: string,
     *   variant_hash: string
     * }
     */
    private function insertReviewableVersion(
        string $organizationId,
        string $programId,
        string $versionKey,
        string $authorMembershipId,
        string $translatorMembershipId,
        string $englishAppointmentId,
        string $englishAppointmentEventId,
        string $englishReviewerMembershipId,
        bool $includeContentReviews,
        ?string $kannadaAppointmentId = null,
        ?string $kannadaAppointmentEventId = null,
        ?string $kannadaReviewerMembershipId = null,
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
                'Operator console synthetic English for %s.',
                $versionKey,
            ),
        ];
        $variantPayload = [
            'instruction' => sprintf(
                'Operator console synthetic Kannada for %s.',
                $versionKey,
            ),
        ];
        $sourceHash = CanonicalJson::sha256($sourcePayload);
        $variantHash = CanonicalJson::sha256($variantPayload);
        $now = now();

        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'key' => $versionKey,
            'created_by_membership_id' => $englishReviewerMembershipId,
            'created_by_attribution' => 'operator-console-test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_objectives')->insert([
            'id' => $objectiveId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'objective-'.$versionKey,
            'outcome_code' => 'synthetic.operator.'.$versionKey,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_modules')->insert([
            'id' => $moduleId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'module-'.$versionKey,
            'sequence' => 1,
            'estimated_minutes' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lessons')->insert([
            'id' => $lessonId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_module_id' => $moduleId,
            'key' => 'lesson-'.$versionKey,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lesson_objectives')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'objective_id' => $objectiveId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_documents')->insert([
            'id' => $documentId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'instruction-'.$versionKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revisions')->insert([
            'id' => $sourceRevisionId,
            'organization_id' => $organizationId,
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
            'authored_by_membership_id' => $authorMembershipId,
            'author_attribution' => 'operator-console-author',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revision_heads')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'content_revision_id' => $sourceRevisionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('localized_variants')->insert([
            'id' => $variantId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'source_revision_id' => $sourceRevisionId,
            'parent_variant_id' => null,
            'locale' => 'kn-IN',
            'revision_number' => 1,
            'source_content_hash' => $sourceHash,
            'payload' => CanonicalJson::encode($variantPayload),
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $translatorMembershipId,
            'translator_attribution' => 'operator-console-translator',
            'translated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_items')->insert([
            'id' => $itemId,
            'organization_id' => $organizationId,
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
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'instruction_content_document_id' => $documentId,
            'key' => 'mission-'.$versionKey,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('mission_items')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'mission_id' => $missionId,
            'learning_item_id' => $itemId,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $graph = $this->app->make(CurriculumGraphProjector::class)->project(
            $organizationId,
            $versionId,
        );
        $graphRevisionId = (string) Str::ulid();
        DB::table('curriculum_graph_revisions')->insert([
            'id' => $graphRevisionId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'parent_revision_id' => null,
            'parent_revision_number' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($graph['graph']),
            'graph_hash' => $graph['graph_hash'],
            'authored_by_membership_id' => $authorMembershipId,
            'author_attribution' => 'operator-console-author',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_heads')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graph['graph_hash'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_reviews')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graph['graph_hash'],
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $englishAppointmentId,
            'authority_appointment_event_id' => $englishAppointmentEventId,
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $englishReviewerMembershipId,
            'decision' => 'approved',
            'rationale_key' => null,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($includeContentReviews) {
            if (
                $kannadaAppointmentId === null
                || $kannadaAppointmentEventId === null
                || $kannadaReviewerMembershipId === null
            ) {
                throw new RuntimeException(
                    'Kannada appointment fields are required when seeding content reviews.',
                );
            }

            DB::table('content_reviews')->insert([
                [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $versionId,
                    'review_kind' => 'academic_en',
                    'source_revision_id' => $sourceRevisionId,
                    'localized_variant_id' => null,
                    'authority_appointment_id' => $englishAppointmentId,
                    'authority_appointment_event_id' => $englishAppointmentEventId,
                    'reviewer_membership_id' => $englishReviewerMembershipId,
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
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $versionId,
                    'review_kind' => 'kannada',
                    'source_revision_id' => $sourceRevisionId,
                    'localized_variant_id' => $variantId,
                    'authority_appointment_id' => $kannadaAppointmentId,
                    'authority_appointment_event_id' => $kannadaAppointmentEventId,
                    'reviewer_membership_id' => $kannadaReviewerMembershipId,
                    'decision' => 'approved',
                    'source_content_hash' => $sourceHash,
                    'localized_content_hash' => $variantHash,
                    'rationale_key' => null,
                    'reviewed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        return [
            'version_id' => $versionId,
            'source_revision_id' => $sourceRevisionId,
            'source_hash' => $sourceHash,
            'variant_id' => $variantId,
            'variant_hash' => $variantHash,
        ];
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
            'qualification_basis' => 'Synthetic operator reviewer fixture',
            'approval_evidence_key' => 'APP-0028-OPERATOR',
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
    private function attachOperatorPermissions(
        Membership $membership,
        OrganizationContext $context,
        array $permissionKeys,
    ): void {
        $role = $this->createRole(
            $context,
            'operator-'.strtolower((string) Str::ulid()),
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

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertNoStore(TestResponse $response): void
    {
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }
}

final class OperatorContentWorkflowOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class OperatorContentWorkflowNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(
        string $normalizedMobileNumber,
        string $code,
    ): void {
        //
    }
}
