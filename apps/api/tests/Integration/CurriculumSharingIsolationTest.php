<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\CurriculumRetirementService;
use App\Curriculum\Sharing\CurriculumSharingService;
use App\Curriculum\Sharing\SharedCurriculumAccessResolver;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CurriculumSharingIsolationTest extends TenancyIntegrationTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_direct_shared_preview_requires_the_recipient_permission_and_the_preview_use(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T15:00:00+00:00');
        $owner = $this->createOrganization('Preview Owner');
        [$ownerActor, $ownerMembership, $ownerContext]
            = $this->createOperator(
                $owner,
                [
                    'curriculum.publish',
                    'curriculum.share.manage',
                ],
            );
        $release = $this->createPublishedRelease(
            $owner,
            $ownerMembership,
        );
        $recipient = $this->createOrganization('Preview Recipient');
        [$recipientActor, , $recipientContext] = $this->createOperator(
            $recipient,
            ['curriculum.shared.read'],
        );
        [$unprivilegedActor, , $unprivilegedContext]
            = $this->createOperator($recipient, []);
        $enrollmentOnlyRecipient = $this->createOrganization(
            'Enrollment Only Recipient',
        );
        [
            $enrollmentOnlyActor,
            ,
            $enrollmentOnlyContext,
        ] = $this->createOperator(
            $enrollmentOnlyRecipient,
            ['curriculum.shared.read'],
        );
        $sharing = $this->app->make(CurriculumSharingService::class);
        $previewGrant = $this->contexts()->run(
            $ownerContext,
            fn () => $sharing->issue(
                actor: $ownerActor,
                publicationId: $release['publication_id'],
                recipientOrganizationId: (string) $recipient->getKey(),
                allowedUses: ['preview'],
                effectiveAt: now()->subMinute(),
                expiresAt: now()->addDay(),
                idempotencyKey: 'issue-preview-grant',
            ),
        );
        $enrollmentGrant = $this->contexts()->run(
            $ownerContext,
            fn () => $sharing->issue(
                actor: $ownerActor,
                publicationId: $release['publication_id'],
                recipientOrganizationId: (string) $enrollmentOnlyRecipient
                    ->getKey(),
                allowedUses: ['enrollment'],
                effectiveAt: now()->subMinute(),
                expiresAt: now()->addDay(),
                idempotencyKey: 'issue-enrollment-only-grant',
            ),
        );
        $this->contexts()->run(
            $ownerContext,
            fn () => $this->app
                ->make(CurriculumRetirementService::class)
                ->retire(
                    actor: $ownerActor,
                    programId: $release['program_id'],
                    expectedHeadVersion: 1,
                    idempotencyKey: 'retire-after-share-issuance',
                    reasonKey: 'release_no_longer_current',
                ),
        );
        $resolver = $this->app->make(SharedCurriculumAccessResolver::class);
        $baselineTransactionLevel = DB::transactionLevel();
        $authorizationTransactionLevel = null;
        $authorizationSql = null;
        $grantTransactionLevel = null;
        $grantSql = null;
        $headTransactionLevel = null;
        $headSql = null;
        DB::listen(function (QueryExecuted $query) use (
            &$authorizationTransactionLevel,
            &$authorizationSql,
            &$grantTransactionLevel,
            &$grantSql,
            &$headTransactionLevel,
            &$headSql,
        ): void {
            $sql = strtolower($query->sql);

            if (
                str_contains($sql, 'membership_roles')
                && str_contains($sql, 'role_permissions')
                && str_contains($sql, 'permissions')
            ) {
                $authorizationTransactionLevel = DB::transactionLevel();
                $authorizationSql = $sql;
            }

            if (
                str_contains($sql, 'curriculum_share_grants')
                && str_contains($sql, 'curriculum_share_grant_heads')
            ) {
                $grantTransactionLevel = DB::transactionLevel();
                $grantSql = $sql;
            }

            if (str_contains($sql, 'from "publication_heads"')) {
                $headTransactionLevel = DB::transactionLevel();
                $headSql = $sql;
            }
        });
        $access = $this->contexts()->run(
            $recipientContext,
            fn () => $resolver->resolve(
                actor: $recipientActor,
                shareGrantId: $previewGrant->shareGrantId,
                use: 'preview',
            ),
        );

        self::assertSame($previewGrant->shareGrantId, $access->shareGrantId);
        self::assertSame($recipient->getKey(), $access->recipientOrganizationId);
        self::assertSame('preview', $access->use);
        self::assertSame($owner->getKey(), $access->publication->ownerOrganizationId);
        self::assertSame(
            $release['publication_id'],
            $access->publication->publicationId,
        );
        self::assertSame(now()->toIso8601String(), $access->evaluatedAt->toIso8601String());
        self::assertSame(
            $baselineTransactionLevel + 1,
            $authorizationTransactionLevel,
        );
        self::assertSame(
            $baselineTransactionLevel + 1,
            $grantTransactionLevel,
        );
        self::assertSame(
            $baselineTransactionLevel + 1,
            $headTransactionLevel,
        );

        if (DB::connection()->getDriverName() === 'pgsql') {
            self::assertIsString($authorizationSql);
            self::assertIsString($grantSql);
            self::assertIsString($headSql);
            self::assertStringContainsString('for update', $authorizationSql);
            self::assertStringContainsString('for update', $grantSql);
            self::assertStringContainsString('for update', $headSql);
        }

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $unprivilegedContext,
                fn () => $resolver->resolve(
                    actor: $unprivilegedActor,
                    shareGrantId: $previewGrant->shareGrantId,
                    use: 'preview',
                ),
            ),
        );
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $enrollmentOnlyContext,
                fn () => $resolver->resolve(
                    actor: $enrollmentOnlyActor,
                    shareGrantId: $enrollmentGrant->shareGrantId,
                    use: 'preview',
                ),
            ),
        );

        DB::table('publication_heads')
            ->where('organization_id', $owner->getKey())
            ->where('program_id', $release['program_id'])
            ->update([
                'is_suspended' => true,
                'lock_version' => 3,
                'updated_at' => now(),
            ]);
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $recipientContext,
                fn () => $resolver->resolve(
                    actor: $recipientActor,
                    shareGrantId: $previewGrant->shareGrantId,
                    use: 'preview',
                ),
            ),
        );
    }

    public function test_recipient_cannot_withdraw_expand_or_reshare_an_owner_grant_even_with_share_management_permission(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T15:30:00+00:00');
        $owner = $this->createOrganization('Share Authority Owner');
        [$ownerActor, $ownerMembership, $ownerContext]
            = $this->createOperator(
                $owner,
                ['curriculum.share.manage'],
            );
        $release = $this->createPublishedRelease(
            $owner,
            $ownerMembership,
        );
        $recipient = $this->createOrganization('Share Authority Recipient');
        [$recipientActor, , $recipientContext] = $this->createOperator(
            $recipient,
            [
                'curriculum.share.manage',
                'curriculum.shared.read',
            ],
        );
        $thirdOrganization = $this->createOrganization(
            'Share Authority Third Tenant',
        );
        $sharing = $this->app->make(CurriculumSharingService::class);
        $grant = $this->contexts()->run(
            $ownerContext,
            fn () => $sharing->issue(
                actor: $ownerActor,
                publicationId: $release['publication_id'],
                recipientOrganizationId: (string) $recipient->getKey(),
                allowedUses: ['preview'],
                effectiveAt: now()->subMinute(),
                expiresAt: now()->addDay(),
                idempotencyKey: 'owner-preview-grant',
            ),
        );

        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $recipientContext,
                fn () => $sharing->withdraw(
                    actor: $recipientActor,
                    shareGrantId: $grant->shareGrantId,
                    expectedGrantVersion: 1,
                    idempotencyKey: 'recipient-withdraw-owner-grant',
                    reasonKey: 'recipient_not_owner',
                ),
            ),
        );
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $recipientContext,
                fn () => $sharing->issue(
                    actor: $recipientActor,
                    publicationId: $release['publication_id'],
                    recipientOrganizationId: (string) $thirdOrganization
                        ->getKey(),
                    allowedUses: ['preview'],
                    effectiveAt: now()->subMinute(),
                    expiresAt: now()->addDay(),
                    idempotencyKey: 'recipient-reshare-owner-publication',
                ),
            ),
        );
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $recipientContext,
                fn () => $sharing->issue(
                    actor: $recipientActor,
                    publicationId: $release['publication_id'],
                    recipientOrganizationId: (string) $thirdOrganization
                        ->getKey(),
                    allowedUses: ['enrollment', 'preview'],
                    effectiveAt: now()->subMinute(),
                    expiresAt: now()->addDay(),
                    idempotencyKey: 'recipient-expand-owner-grant',
                ),
            ),
        );

        $this->assertDatabaseHas('curriculum_share_grant_heads', [
            'organization_id' => $owner->getKey(),
            'curriculum_share_grant_id' => $grant->shareGrantId,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        self::assertSame(
            1,
            DB::table('curriculum_share_grants')
                ->where('curriculum_publication_id', $release['publication_id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('publication_commands')
                ->where('organization_id', $recipient->getKey())
                ->whereIn('operation', [
                    'curriculum.share.issue',
                    'curriculum.share.withdraw',
                ])
                ->count(),
        );
    }

    public function test_same_owner_enrollment_pin_rejects_a_publication_outside_the_declared_owner_tuple(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T16:00:00+00:00');
        $owner = $this->createOrganization('Same Owner Pin Tenant');
        [, $ownerMembership, $ownerContext] = $this->createOperator($owner, []);
        $release = $this->createPublishedRelease(
            $owner,
            $ownerMembership,
        );
        $enrollmentId = $this->createPendingEnrollment(
            $owner,
            $ownerContext,
        );

        $this->assertPinForeignKeyRejected(
            fn (): int => DB::table('enrollments')
                ->where('organization_id', $owner->getKey())
                ->where('id', $enrollmentId)
                ->update([
                    'curriculum_owner_organization_id' => $owner->getKey(),
                    'curriculum_version_id' => $release['version_id'],
                    'curriculum_publication_id' => (string) Str::ulid(),
                    'curriculum_share_grant_id' => null,
                    'status' => 'active',
                    'version' => 2,
                    'activated_at' => now(),
                    'updated_at' => now(),
                ]),
            'enrollments_publication_pin_fk',
        );
    }

    public function test_cross_owner_enrollment_pin_rejects_an_unknown_share_grant_tuple(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T16:15:00+00:00');
        $owner = $this->createOrganization('Cross Owner Publication Tenant');
        [, $ownerMembership] = $this->createOperator($owner, []);
        $release = $this->createPublishedRelease(
            $owner,
            $ownerMembership,
        );
        $recipient = $this->createOrganization('Cross Owner Enrollment Tenant');
        [, , $recipientContext] = $this->createOperator($recipient, []);
        $enrollmentId = $this->createPendingEnrollment(
            $recipient,
            $recipientContext,
        );

        $this->assertPinForeignKeyRejected(
            fn (): int => DB::table('enrollments')
                ->where('organization_id', $recipient->getKey())
                ->where('id', $enrollmentId)
                ->update([
                    'curriculum_owner_organization_id' => $owner->getKey(),
                    'curriculum_version_id' => $release['version_id'],
                    'curriculum_publication_id' => $release['publication_id'],
                    'curriculum_share_grant_id' => (string) Str::ulid(),
                    'status' => 'active',
                    'version' => 2,
                    'activated_at' => now(),
                    'updated_at' => now(),
                ]),
            'enrollments_shared_publication_grant_fk',
        );
    }

    public function test_cross_owner_enrollment_pin_rejects_a_grant_issued_to_another_recipient(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T16:30:00+00:00');
        $owner = $this->createOrganization('Grant Recipient Owner');
        [$ownerActor, $ownerMembership, $ownerContext]
            = $this->createOperator(
                $owner,
                ['curriculum.share.manage'],
            );
        $release = $this->createPublishedRelease(
            $owner,
            $ownerMembership,
        );
        $intendedRecipient = $this->createOrganization(
            'Grant Intended Recipient',
        );
        $otherRecipient = $this->createOrganization(
            'Grant Other Recipient',
        );
        [, , $otherRecipientContext] = $this->createOperator(
            $otherRecipient,
            [],
        );
        $grant = $this->contexts()->run(
            $ownerContext,
            fn () => $this->app
                ->make(CurriculumSharingService::class)
                ->issue(
                    actor: $ownerActor,
                    publicationId: $release['publication_id'],
                    recipientOrganizationId: (string) $intendedRecipient
                        ->getKey(),
                    allowedUses: ['enrollment'],
                    effectiveAt: now()->subMinute(),
                    expiresAt: now()->addDay(),
                    idempotencyKey: 'grant-for-intended-recipient',
                ),
        );
        $enrollmentId = $this->createPendingEnrollment(
            $otherRecipient,
            $otherRecipientContext,
        );

        $this->assertPinForeignKeyRejected(
            fn (): int => DB::table('enrollments')
                ->where('organization_id', $otherRecipient->getKey())
                ->where('id', $enrollmentId)
                ->update([
                    'curriculum_owner_organization_id' => $owner->getKey(),
                    'curriculum_version_id' => $release['version_id'],
                    'curriculum_publication_id' => $release['publication_id'],
                    'curriculum_share_grant_id' => $grant->shareGrantId,
                    'status' => 'active',
                    'version' => 2,
                    'activated_at' => now(),
                    'updated_at' => now(),
                ]),
            'enrollments_shared_publication_grant_fk',
        );
    }

    /**
     * @param  list<string>  $permissions
     * @return array{User, Membership, OrganizationContext}
     */
    private function createOperator(
        Organization $organization,
        array $permissions,
    ): array {
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );

        if ($permissions !== []) {
            $role = $this->createRole(
                $context,
                'sharing-'.strtolower((string) Str::ulid()),
            );
            $now = now();
            DB::table('membership_roles')->insert([
                'organization_id' => $organization->getKey(),
                'membership_id' => $membership->getKey(),
                'role_id' => $role->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($permissions as $permissionKey) {
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
                    'organization_id' => $organization->getKey(),
                    'role_id' => $role->getKey(),
                    'permission_id' => $permission->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return [$user, $membership, $context];
    }

    /**
     * @return array{
     *   program_id: string,
     *   version_id: string,
     *   publication_id: string
     * }
     */
    private function createPublishedRelease(
        Organization $organization,
        Membership $publisherMembership,
    ): array {
        $organizationId = (string) $organization->getKey();
        $programId = (string) Str::ulid();
        $versionId = (string) Str::ulid();
        $reviewer = $this->createUser();
        [$reviewerMembership] = $this->createMembership(
            $organization,
            $reviewer,
            Membership::TYPE_TRAINER,
        );
        $appointmentId = (string) Str::ulid();
        $appointedEventId = (string) Str::ulid();
        $acceptedEventId = (string) Str::ulid();
        $graphRevisionId = (string) Str::ulid();
        $graphReviewId = (string) Str::ulid();
        $publicationId = (string) Str::ulid();
        $activationEventId = (string) Str::ulid();
        $now = now();
        $graph = [
            'edges' => [],
            'nodes' => [],
        ];
        $graphHash = CanonicalJson::sha256($graph);

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => 'sharing-'.strtolower((string) Str::ulid()),
            'internal_name' => 'Synthetic sharing isolation program',
            'created_by_membership_id' => $publisherMembership->getKey(),
            'created_by_attribution' => 'synthetic-sharing-test-publisher',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'key' => 'release-1',
            'created_by_membership_id' => $publisherMembership->getKey(),
            'created_by_attribution' => 'synthetic-sharing-test-publisher',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $organizationId,
            'appointee_user_id' => $reviewer->getKey(),
            'appointee_membership_id' => $reviewerMembership->getKey(),
            'authority_kind' => 'english_academic_reviewer',
            'scope_type' => 'program',
            'program_id' => $programId,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'qualification_basis' => 'Synthetic sharing test qualification',
            'approval_evidence_key' => 'APP-TEST-SHARING',
            'appointed_by_membership_id' => $publisherMembership->getKey(),
            'appointed_at' => now()->subMinutes(5),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointment_events')->insert([
            [
                'id' => $appointedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'appointed',
                'actor_membership_id' => $publisherMembership->getKey(),
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => now()->subMinutes(5),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $acceptedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'accepted',
                'actor_membership_id' => $reviewerMembership->getKey(),
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => now()->subMinutes(4),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        DB::table('curriculum_graph_revisions')->insert([
            'id' => $graphRevisionId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'parent_revision_id' => null,
            'parent_revision_number' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($graph),
            'graph_hash' => $graphHash,
            'authored_by_membership_id' => $publisherMembership->getKey(),
            'author_attribution' => 'synthetic-sharing-test-publisher',
            'authored_at' => now()->subMinutes(3),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_reviews')->insert([
            'id' => $graphReviewId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graphHash,
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $appointmentId,
            'authority_appointment_event_id' => $acceptedEventId,
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $reviewerMembership->getKey(),
            'decision' => 'approved',
            'rationale_key' => null,
            'reviewed_at' => now()->subMinutes(2),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $manifest = [
            'schema' => 'global-english.curriculum-publication.v1',
            'organization_id' => $organizationId,
            'program' => [
                'id' => $programId,
                'key' => 'synthetic-sharing-isolation',
            ],
            'curriculum_version' => [
                'id' => $versionId,
                'key' => 'release-1',
            ],
            'graph_revision' => [
                'id' => $graphRevisionId,
                'revision_number' => 1,
                'graph_hash' => $graphHash,
                'author_membership_id' => $publisherMembership->getKey(),
                'graph' => $graph,
                'review' => [
                    'id' => $graphReviewId,
                    'decision' => 'approved',
                    'appointment_id' => $appointmentId,
                    'appointment_event_id' => $acceptedEventId,
                    'reviewer_membership_id' => $reviewerMembership->getKey(),
                    'rationale_key' => null,
                ],
            ],
            'objectives' => [],
            'objective_prerequisites' => [],
            'modules' => [],
            'lessons' => [],
            'lesson_objectives' => [],
            'learning_items' => [],
            'missions' => [],
            'mission_items' => [],
            'content' => [],
        ];
        $manifestHash = CanonicalJson::sha256($manifest);

        DB::table('curriculum_publications')->insert([
            'id' => $publicationId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $versionId,
            'publication_number' => 1,
            'manifest_schema_version' => 1,
            'manifest' => CanonicalJson::encode($manifest),
            'manifest_hash' => $manifestHash,
            'created_by_membership_id' => $publisherMembership->getKey(),
            'created_at' => now()->subMinute(),
        ]);
        DB::table('publication_graph_components')->insert([
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $versionId,
            'curriculum_publication_id' => $publicationId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graphHash,
            'curriculum_graph_review_id' => $graphReviewId,
            'graph_review_decision' => 'approved',
            'academic_appointment_id' => $appointmentId,
            'academic_appointment_event_id' => $acceptedEventId,
            'reviewer_membership_id' => $reviewerMembership->getKey(),
            'created_at' => now()->subMinute(),
        ]);
        DB::table('publication_events')->insert([
            'id' => $activationEventId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $versionId,
            'curriculum_publication_id' => $publicationId,
            'event_type' => 'activated',
            'previous_curriculum_version_id' => null,
            'previous_publication_id' => null,
            'actor_type' => 'owner_membership',
            'actor_membership_id' => $publisherMembership->getKey(),
            'platform_actor_user_id' => null,
            'reason_key' => null,
            'correlation_id' => (string) Str::uuid(),
            'effective_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
        ]);
        DB::table('publication_heads')->insert([
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'active_curriculum_version_id' => $versionId,
            'active_publication_id' => $publicationId,
            'last_event_id' => $activationEventId,
            'is_suspended' => false,
            'lock_version' => 1,
            'updated_at' => $now,
        ]);

        return [
            'program_id' => $programId,
            'version_id' => $versionId,
            'publication_id' => $publicationId,
        ];
    }

    private function createPendingEnrollment(
        Organization $organization,
        OrganizationContext $operatorContext,
    ): string {
        $learner = $this->createUser();
        [$learnerMembership] = $this->createMembership(
            $organization,
            $learner,
        );
        $cohort = $this->createCohort(
            $operatorContext,
            'Synthetic sharing isolation cohort',
        );
        $enrollmentId = (string) Str::ulid();
        $now = now();

        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $learnerMembership->getKey(),
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
     * @param  callable(): int  $operation
     */
    private function assertPinForeignKeyRejected(
        callable $operation,
        string $constraint,
    ): void {
        try {
            $operation();
        } catch (QueryException $exception) {
            self::assertSame('23503', (string) $exception->getCode());
            self::assertStringContainsString(
                $constraint,
                $exception->getMessage(),
            );

            return;
        }

        self::fail('The invalid enrollment pin tuple should be rejected.');
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
