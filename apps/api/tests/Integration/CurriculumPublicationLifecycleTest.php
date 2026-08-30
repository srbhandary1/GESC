<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Enrollment\EnrollmentCurriculumPinner;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\CurriculumGraphGovernanceCommands;
use App\Curriculum\Publication\CurriculumPublicationActivator;
use App\Curriculum\Publication\CurriculumPublicationScheduleCancellationService;
use App\Curriculum\Publication\CurriculumPublicationScheduler;
use App\Curriculum\Publication\CurriculumPublisher;
use App\Curriculum\Publication\CurriculumRetirementService;
use App\Curriculum\Publication\CurriculumRollbackService;
use App\Curriculum\Publication\PublicationEvidenceRevalidator;
use App\Curriculum\Sharing\CurriculumSharingService;
use App\Learning\Attempts\DailyMissionAttemptService;
use App\Learning\Exceptions\LearningIdempotencyConflict;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Home\LearnerHomeService;
use App\Learning\Media\VerifiedPrivateAudioResponder;
use App\Learning\Missions\DailyMissionReader;
use App\Learning\Offline\OfflineCompletionService;
use App\Learning\Offline\OfflineMissionBundleService;
use App\Learning\Onboarding\LearnerOnboardingService;
use App\Learning\Review\DelayedReviewScheduler;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Operator\ContentWorkspace\ContentDraftGraphService;
use App\Operator\OperatorPublicationStatusQuery;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CurriculumPublicationLifecycleTest extends TenancyIntegrationTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_workspace_v2_publication_contains_levels_managed_media_and_seals_the_exact_workspace(): void
    {
        CarbonImmutable::setTestNow('2026-08-09T10:00:00+00:00');
        $organization = $this->createOrganization('Workspace V2 Publication');
        $authority = $this->createPublicationAuthority($organization);
        $this->grantPermissions(
            membership: $authority['operator_membership'],
            context: $authority['operator_context'],
            permissionKeys: ['curriculum.author'],
        );
        $version = $this->createReadyVersion(
            authority: $authority,
            versionKey: 'workspace-v2',
            dailyMission: true,
        );
        $versionId = $version['version_id'];
        $organizationId = $authority['organization_id'];
        $levelId = (string) Str::ulid();
        $now = now();
        DB::table('curriculum_levels')->insert([
            'id' => $levelId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => 'a1',
            'sequence' => 1,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_objectives')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $versionId)
            ->update(['outcome_code' => 'CAN_COMPLETE_SYNTHETIC_MISSION']);
        DB::table('curriculum_modules')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $versionId)
            ->update(['curriculum_level_id' => $levelId]);
        DB::table('curriculum_workspaces')->insert([
            'organization_id' => $organizationId,
            'program_id' => $authority['program_id'],
            'curriculum_version_id' => $versionId,
            'source_curriculum_version_id' => null,
            'source_curriculum_publication_id' => null,
            'graph_schema_version' => 2,
            'state' => 'draft',
            'version' => 1,
            'current_draft_revision_id' => null,
            'submitted_graph_revision_id' => null,
            'legacy_level_assignment_required' => false,
            'created_by_membership_id' => $authority['operator_membership']->getKey(),
            'sealed_at' => null,
            'abandoned_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $draftGraph = [
            'schema' => ContentDraftGraphService::DRAFT_SCHEMA,
            'levels' => [['key' => 'a1', 'sequence' => 1]],
            'objectives' => [[
                'key' => 'objective-workspace-v2',
                'outcome_code' => 'CAN_COMPLETE_SYNTHETIC_MISSION',
                'sequence' => 1,
                'prerequisite_keys' => [],
            ]],
            'modules' => [[
                'key' => 'module-workspace-v2',
                'level_key' => 'a1',
                'sequence' => 1,
                'estimated_minutes' => 5,
            ]],
            'lessons' => [[
                'key' => 'lesson-workspace-v2',
                'module_key' => 'module-workspace-v2',
                'sequence' => 1,
                'objective_keys' => ['objective-workspace-v2'],
            ]],
            'items' => [[
                'key' => 'item-workspace-v2',
                'lesson_key' => 'lesson-workspace-v2',
                'content_document_key' => 'instruction-workspace-v2',
                'item_type' => 'speaking_prompt',
                'sequence' => 1,
            ]],
            'delivery_bundles' => [[
                'key' => 'mission-workspace-v2',
                'instruction_document_key' => 'instruction-workspace-v2',
                'sequence' => 1,
                'item_keys' => ['item-workspace-v2'],
            ]],
        ];
        $materialized = $this->app
            ->make(ContentDraftGraphService::class)
            ->replace($organizationId, $versionId, $draftGraph, $now);
        self::assertTrue($materialized['is_complete']);
        $projected = $this->app->make(CurriculumGraphProjector::class)->project(
            $organizationId,
            $versionId,
        );
        self::assertSame(
            'global-english.curriculum-graph.v2',
            $projected['graph']['schema'],
        );
        $graphRevisionId = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $this->app
                ->make(CurriculumGraphGovernanceCommands::class)
                ->snapshot(
                    actor: $authority['operator'],
                    curriculumVersionId: $versionId,
                    expectedRevisionNumber: 1,
                    expectedGraphHash: $projected['graph_hash'],
                    idempotencyKey: 'workspace-v2-graph-snapshot',
                    authorAttribution: 'synthetic-workspace-v2-author',
                ),
        );
        $draftRevisionId = (string) Str::ulid();
        DB::table('curriculum_draft_graph_revisions')->insert([
            'id' => $draftRevisionId,
            'organization_id' => $organizationId,
            'program_id' => $authority['program_id'],
            'curriculum_version_id' => $versionId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($materialized['graph']),
            'graph_hash' => $materialized['graph_hash'],
            'blocker_codes' => CanonicalJson::encode([]),
            'is_complete' => true,
            'authored_by_membership_id' => $authority['operator_membership']->getKey(),
            'authored_at' => $now,
            'created_at' => $now,
        ]);
        $graphReviewId = (string) Str::ulid();
        DB::table('curriculum_graph_reviews')->insert([
            'id' => $graphReviewId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $projected['graph_hash'],
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $authority['english_appointment_id'],
            'authority_appointment_event_id' => $authority['english_appointment_event_id'],
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $authority['english_reviewer_membership_id'],
            'decision' => 'approved',
            'rationale_key' => 'meets_standard',
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_workspaces')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $versionId)
            ->where('version', 1)
            ->update([
                'state' => 'in_review',
                'version' => 2,
                'current_draft_revision_id' => $draftRevisionId,
                'submitted_graph_revision_id' => $graphRevisionId,
                'updated_at' => $now,
            ]);

        $managedRights = $this->attachApprovedMediaRights(
            authority: $authority,
            version: $version,
            usageKey: 'model_audio',
            audio: 'governed-workspace-v2-model-audio',
            assetKind: 'model_audio',
        );
        $managed = $this->attachManagedMediaGovernance(
            authority: $authority,
            version: $version,
            rights: $managedRights,
        );

        $published = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $this->app->make(CurriculumPublisher::class)->publish(
                actor: $authority['operator'],
                curriculumVersionId: $versionId,
                expectedHeadVersion: 0,
                idempotencyKey: 'publish-workspace-v2',
            ),
        );
        $manifest = DB::table('curriculum_publications')
            ->where('organization_id', $organizationId)
            ->where('id', $published->publicationId)
            ->value('manifest');
        self::assertIsString($manifest);
        $decoded = json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('global-english.curriculum-publication.v2', $decoded['schema']);
        self::assertSame(
            'global-english.curriculum-graph.v2',
            $decoded['graph_revision']['graph']['schema'],
        );
        self::assertSame('a1', $decoded['graph_revision']['graph']['levels'][0]['key']);
        self::assertSame(
            $managed['scan_id'],
            $decoded['content'][0]['media'][0]['governance']['selected_scan']['id'],
        );
        self::assertSame(
            $managed['metadata_revision_id'],
            $decoded['content'][0]['media'][0]['governance']['metadata']['revision_id'],
        );
        self::assertSame(
            $managedRights['rights_revision_id'],
            $decoded['content'][0]['media'][0]['governance']['rights']['revision_id'],
        );
        $this->assertDatabaseHas('publication_media_governance_components', [
            'organization_id' => $organizationId,
            'curriculum_publication_id' => $published->publicationId,
            'media_asset_id' => $managedRights['media_asset_id'],
            'selected_scan_id' => $managed['scan_id'],
            'metadata_revision_id' => $managed['metadata_revision_id'],
            'english_review_id' => $managed['english_review_id'],
            'metadata_variant_id' => $managed['metadata_variant_id'],
            'kannada_review_id' => $managed['kannada_review_id'],
            'media_rights_revision_id' => $managedRights[
                'rights_revision_id'
            ],
        ]);
        $this->assertDatabaseHas('curriculum_workspaces', [
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'state' => 'sealed',
            'version' => 3,
        ]);
        $this->assertDatabaseHas('curriculum_workspace_events', [
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'event_type' => 'workspace.sealed',
            'workspace_version' => 3,
        ]);

        DB::table('media_asset_lifecycles')
            ->where('organization_id', $organizationId)
            ->where('media_asset_id', $managedRights['media_asset_id'])
            ->update([
                'state' => 'retired',
                'version' => 2,
                'retired_by_membership_id' => $authority[
                    'operator_membership'
                ]->getKey(),
                'retirement_reason_key' => 'publication_fixture_retired',
                'retired_at' => $now,
                'updated_at' => $now,
            ]);
        $revalidator = $this->app->make(
            PublicationEvidenceRevalidator::class,
        );
        $verifiedPrior = $revalidator->verify(
            ownerOrganizationId: $organizationId,
            publicationId: $published->publicationId,
            evaluatedAt: $now,
            requireCurrentManagedMedia: false,
        );
        self::assertSame(
            $published->publicationId,
            $verifiedPrior->publicationId,
        );
        $this->assertOperationRejected(
            fn () => $revalidator->verify(
                ownerOrganizationId: $organizationId,
                publicationId: $published->publicationId,
                evaluatedAt: $now,
                requireCurrentManagedMedia: true,
            ),
        );
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

    public function test_pr006_delivers_a_governed_pinned_daily_mission_and_deterministic_learning_results(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:15:00+00:00');
        config()->set('learning.model_audio_disk', 'local');
        Storage::fake('local');
        $organization = $this->createOrganization(
            'Daily Speaking Slice Tenant',
        );
        $authority = $this->createPublicationAuthority($organization);
        $version = $this->createReadyVersion(
            authority: $authority,
            versionKey: 'daily-speaking-v1',
            dailyMission: true,
        );
        $audio = 'governed-daily-model-audio';
        $rights = $this->attachApprovedMediaRights(
            authority: $authority,
            version: $version,
            usageKey: 'model_audio',
            audio: $audio,
        );
        Storage::disk('local')->put(
            $rights['storage_object_key'],
            $audio,
        );
        $publication = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $this->app->make(CurriculumPublisher::class)
                ->publish(
                    actor: $authority['operator'],
                    curriculumVersionId: $version['version_id'],
                    expectedHeadVersion: 0,
                    idempotencyKey: 'publish-daily-speaking-v1',
                ),
        );
        $learner = $this->createUser();
        [$learnerMembership, $learnerContext] = $this->createMembership(
            $organization,
            $learner,
        );
        $this->grantPermissions(
            membership: $learnerMembership,
            context: $learnerContext,
            permissionKeys: ['learning.participate'],
        );
        $cohort = $this->createCohort(
            $authority['operator_context'],
            'Daily speaking learners',
        );
        $enrollmentId = (string) Str::ulid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $learnerMembership->getKey(),
            'cohort_id' => $cohort->getKey(),
            'status' => 'pending_curriculum',
            'enrolled_at' => now(),
            'ended_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->contexts()->run(
            $authority['operator_context'],
            fn () => $this->app->make(EnrollmentCurriculumPinner::class)
                ->activate(
                    actor: $authority['operator'],
                    enrollmentId: $enrollmentId,
                    publicationId: $publication->publicationId,
                    shareGrantId: null,
                    idempotencyKey: 'pin-daily-speaking-learner',
                ),
        );
        $reader = $this->app->make(DailyMissionReader::class);
        $home = $this->contexts()->run(
            $learnerContext,
            fn (): array => $this->app->make(
                LearnerHomeService::class,
            )->forLearner($learner),
        );

        self::assertFalse($home['onboarding']['completed']);
        self::assertSame($enrollmentId, $home['enrollment']['id']);
        self::assertNull($home['next_mission']);
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $reader->resolve(
                    $learner,
                    $enrollmentId,
                    $version['mission_id'],
                ),
            ),
        );

        $onboarding = $this->contexts()->run(
            $learnerContext,
            fn () => $this->app->make(
                LearnerOnboardingService::class,
            )->complete(
                learner: $learner,
                adultConfirmed: true,
                goalKey: 'everyday-conversation',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        self::assertFalse($onboarding->replayed);
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $reader->resolve(
                    $learner,
                    $enrollmentId,
                    (string) Str::ulid(),
                ),
            ),
        );
        $mission = $this->contexts()->run(
            $learnerContext,
            fn () => $reader->resolve(
                $learner,
                $enrollmentId,
                $version['mission_id'],
            ),
        );

        self::assertSame($publication->publicationId, $mission->publicationId);
        self::assertSame($version['objective_id'], $mission->objectiveId);
        self::assertSame('daily-greeting-check', $mission->checkId);
        self::assertSame($rights['media_asset_id'], $mission->modelAudioAssetId);
        self::assertSame(strlen($audio), $mission->modelAudioByteSize);
        self::assertArrayNotHasKey(
            'correct_option_id',
            $mission->publicPayload()['check'],
        );
        $stream = $this->app->make(
            VerifiedPrivateAudioResponder::class,
        )->respond($mission);
        $callback = $stream->getCallback();

        if (! is_callable($callback)) {
            self::fail('The verified audio response must be streamable.');
        }

        ob_start();
        $callback();
        self::assertSame($audio, ob_get_clean());

        $configuredMaximum = config('learning.model_audio_maximum_bytes');
        config()->set(
            'learning.model_audio_maximum_bytes',
            strlen($audio) - 1,
        );
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $reader->resolve(
                    $learner,
                    $enrollmentId,
                    $version['mission_id'],
                ),
            ),
        );
        config()->set(
            'learning.model_audio_maximum_bytes',
            $configuredMaximum,
        );

        $otherLearner = $this->createUser();
        [$otherMembership, $otherContext] = $this->createMembership(
            $organization,
            $otherLearner,
        );
        $this->grantPermissions(
            membership: $otherMembership,
            context: $otherContext,
            permissionKeys: ['learning.participate'],
        );
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $otherContext,
                fn () => $reader->resolve(
                    $otherLearner,
                    $enrollmentId,
                    $version['mission_id'],
                ),
            ),
        );

        $attempts = $this->app->make(
            DailyMissionAttemptService::class,
        );
        $firstStartKey = (string) Str::uuid();
        $first = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->start(
                $learner,
                $enrollmentId,
                $mission->missionId,
                $firstStartKey,
            ),
        );
        $this->assertDatabaseHas('learner_attempts', [
            'id' => $first['id'],
            'rule_version' => 'daily-speaking-v1',
        ]);
        config()->set(
            'learning.default_rule_version',
            'unsupported-future-rule',
        );
        $firstReplay = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->start(
                $learner,
                $enrollmentId,
                $mission->missionId,
                $firstStartKey,
            ),
        );
        $firstResume = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->start(
                $learner,
                $enrollmentId,
                $mission->missionId,
                (string) Str::uuid(),
            ),
        );

        self::assertFalse($first['replayed']);
        self::assertTrue($firstReplay['replayed']);
        self::assertTrue($firstResume['replayed']);
        self::assertSame($first['id'], $firstReplay['id']);
        self::assertSame($first['id'], $firstResume['id']);

        $firstResponseKey = (string) Str::uuid();
        $responseReplay = $this->contexts()->run(
            $learnerContext,
            function () use (
                $attempts,
                $learner,
                $first,
                $mission,
                $firstResponseKey,
            ): array {
                $recorded = $attempts->respond(
                    $learner,
                    $first['id'],
                    $mission->checkId,
                    'not-a-greeting',
                    null,
                    $firstResponseKey,
                );
                $replayed = $attempts->respond(
                    $learner,
                    $first['id'],
                    $mission->checkId,
                    'not-a-greeting',
                    null,
                    $firstResponseKey,
                );

                return [$recorded, $replayed];
            },
        );
        self::assertSame([false, true], $responseReplay);
        $this->assertLearningIdempotencyConflict(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $attempts->respond(
                    $learner,
                    $first['id'],
                    $mission->checkId,
                    'polite-greeting',
                    null,
                    $firstResponseKey,
                ),
            ),
        );
        $firstSubmitKey = (string) Str::uuid();
        $this->installPr006SubmitOutboxFailureTrigger();

        try {
            try {
                $this->contexts()->run(
                    $learnerContext,
                    fn (): array => $attempts->submit(
                        $learner,
                        $first['id'],
                        $firstSubmitKey,
                    ),
                );
                self::fail(
                    'A deterministic late outbox failure must abort submit.',
                );
            } catch (QueryException $exception) {
                self::assertSame('55000', (string) $exception->getCode());
                self::assertStringContainsString(
                    'PR-006 forced late submit failure',
                    $exception->getMessage(),
                );
            }
        } finally {
            $this->removePr006SubmitOutboxFailureTrigger();
        }

        $this->assertDatabaseHas('learner_attempts', [
            'organization_id' => $organization->getKey(),
            'id' => $first['id'],
            'status' => 'in_progress',
            'submitted_at' => null,
        ]);
        self::assertSame(
            0,
            DB::table('attempt_submissions')
                ->where('organization_id', $organization->getKey())
                ->where('learner_attempt_id', $first['id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('mastery_states')
                ->where('organization_id', $organization->getKey())
                ->where('enrollment_id', $enrollmentId)
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('review_schedules')
                ->where('organization_id', $organization->getKey())
                ->where('source_attempt_id', $first['id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('learning_commands')
                ->where('organization_id', $organization->getKey())
                ->where('operation', 'learner.attempt.submit')
                ->where('aggregate_id', $first['id'])
                ->where('idempotency_key', $firstSubmitKey)
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('audit_events')
                ->where('organization_id', $organization->getKey())
                ->where('action', 'learner.attempt.submitted')
                ->where('subject_id', $first['id'])
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('outbox_events')
                ->where('organization_id', $organization->getKey())
                ->where('event_type', 'learner.attempt.submitted')
                ->where('aggregate_id', $first['id'])
                ->count(),
        );
        $firstResult = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->submit(
                $learner,
                $first['id'],
                $firstSubmitKey,
            ),
        );
        $firstSubmitReplay = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->submit(
                $learner,
                $first['id'],
                $firstSubmitKey,
            ),
        );

        self::assertSame($firstResult, $firstSubmitReplay);
        self::assertSame('incorrect', $firstResult['check_state']);
        self::assertSame('developing', $firstResult['knowledge_state']);
        self::assertSame('unassessed', $firstResult['spoken_state']);
        self::assertFalse($firstResult['local_capture_receipt_present']);
        self::assertSame(
            $first['id'],
            $firstResult['evidence']['best_attempt_id'],
        );
        self::assertTrue(
            CarbonImmutable::parse($firstResult['review']['due_at'])
                ->equalTo(CarbonImmutable::now()),
        );
        $firstStartReplayAfterSubmission = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->start(
                $learner,
                $enrollmentId,
                $mission->missionId,
                $firstStartKey,
            ),
        );
        self::assertSame([
            'id' => $first['id'],
            'status' => 'in_progress',
            'replayed' => true,
        ], $firstStartReplayAfterSubmission);
        config()->set(
            'learning.default_rule_version',
            'daily-speaking-v1',
        );

        $second = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->start(
                $learner,
                $enrollmentId,
                $mission->missionId,
                (string) Str::uuid(),
            ),
        );
        $captureId = (string) Str::uuid();
        $this->contexts()->run(
            $learnerContext,
            fn (): bool => $attempts->respond(
                $learner,
                $second['id'],
                $mission->checkId,
                'polite-greeting',
                [
                    'captured' => true,
                    'client_capture_id' => $captureId,
                    'mime_type' => 'audio/webm',
                    'byte_size' => 512,
                    'duration_ms' => 1200,
                ],
                (string) Str::uuid(),
            ),
        );
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $attempts->respond(
                    $learner,
                    $second['id'],
                    $mission->checkId,
                    'polite-greeting',
                    [
                        'captured' => true,
                        'client_capture_id' => $captureId,
                        'mime_type' => 'audio/webm',
                        'byte_size' => 512,
                        'duration_ms' => 1200,
                    ],
                    (string) Str::uuid(),
                ),
            ),
        );
        $secondResult = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->submit(
                $learner,
                $second['id'],
                (string) Str::uuid(),
            ),
        );

        self::assertSame('correct', $secondResult['check_state']);
        self::assertSame('mastered', $secondResult['knowledge_state']);
        self::assertSame('unassessed', $secondResult['spoken_state']);
        self::assertTrue($secondResult['local_capture_receipt_present']);
        self::assertSame(
            $first['id'],
            $secondResult['evidence']['first_attempt_id'],
        );
        self::assertSame(
            $second['id'],
            $secondResult['evidence']['latest_attempt_id'],
        );
        self::assertSame(
            $second['id'],
            $secondResult['evidence']['best_attempt_id'],
        );
        self::assertSame(
            '2026-07-31T03:30:00+00:00',
            CarbonImmutable::parse(
                $secondResult['review']['due_at'],
            )->toIso8601String(),
        );
        $masteryAttempt = DB::table('learner_attempts')
            ->where('organization_id', $organization->getKey())
            ->where('id', $second['id'])
            ->firstOrFail();
        $masteryAt = CarbonImmutable::parse((string) $masteryAttempt->submitted_at);
        $scheduler = $this->app->make(
            DelayedReviewScheduler::class,
        );
        $scheduler->reconcile($masteryAttempt, $masteryAt);
        $scheduler->reconcile($masteryAttempt, $masteryAt);
        self::assertSame(
            count(config('review.interval_days')),
            DB::table('delayed_review_occurrences')
                ->where('organization_id', $organization->getKey())
                ->where('source_attempt_id', $second['id'])
                ->count(),
            'A replayed mastery event must not duplicate review occurrences.',
        );
        self::assertSame(
            0,
            DB::table('delayed_review_occurrences')
                ->where('organization_id', '!=', $organization->getKey())
                ->where('learner_membership_id', $learnerMembership->getKey())
                ->count(),
        );

        // ADR-0028: only deterministic mastery evidence may create a review
        // occurrence. $first never reached mastery, so a row naming it as the
        // source must be refused however the write is attempted.
        if (DB::connection()->getDriverName() === 'pgsql') {
            $organizationId = (string) $organization->getKey();
            $unmasteredAttemptId = $first['id'];
            $fabricatedOccurrence = [
                'organization_id' => $organizationId,
                'enrollment_id' => $enrollmentId,
                'learner_membership_id' => (string) $learnerMembership->getKey(),
                'learning_objective_id' => $mission->objectiveId,
                'source_attempt_id' => $unmasteredAttemptId,
                'curriculum_mission_id' => $mission->missionId,
                'sequence' => 1,
                'interval_days' => 1,
                'rule_version' => 'provisional-v1',
                'timezone' => 'Asia/Kolkata',
                'due_at' => $masteryAt,
                'completed_at' => null,
                'completion_attempt_id' => null,
                'created_at' => $masteryAt,
                'updated_at' => $masteryAt,
            ];

            try {
                // A nested transaction is a savepoint, so the expected
                // constraint failure does not abort the test transaction.
                DB::transaction(static function () use ($fabricatedOccurrence): void {
                    DB::table('delayed_review_occurrences')->insert([
                        'id' => (string) Str::ulid(),
                        ...$fabricatedOccurrence,
                    ]);
                });
                self::fail(
                    'A review occurrence without mastery evidence must be refused.',
                );
            } catch (QueryException $exception) {
                self::assertStringContainsString(
                    'deterministic mastery evidence',
                    $exception->getMessage(),
                );
            }

            self::assertSame(
                0,
                DB::table('delayed_review_occurrences')
                    ->where('organization_id', $organizationId)
                    ->where('source_attempt_id', $unmasteredAttemptId)
                    ->count(),
                'The refused occurrence must leave no residue.',
            );
        }
        $this->assertDatabaseHas('item_responses', [
            'organization_id' => $organization->getKey(),
            'learner_attempt_id' => $second['id'],
            'client_capture_id' => $captureId,
            'capture_byte_size' => 512,
        ]);

        $third = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->start(
                $learner,
                $enrollmentId,
                $mission->missionId,
                (string) Str::uuid(),
            ),
        );
        $this->contexts()->run(
            $learnerContext,
            fn (): bool => $attempts->respond(
                $learner,
                $third['id'],
                $mission->checkId,
                'polite-greeting',
                null,
                (string) Str::uuid(),
            ),
        );
        $thirdResult = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->submit(
                $learner,
                $third['id'],
                (string) Str::uuid(),
            ),
        );

        self::assertSame(
            $third['id'],
            $thirdResult['evidence']['latest_attempt_id'],
        );
        self::assertSame(
            $second['id'],
            $thirdResult['evidence']['best_attempt_id'],
        );
        self::assertSame('unassessed', $thirdResult['spoken_state']);
        self::assertFalse($thirdResult['local_capture_receipt_present']);
        $fourth = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->start(
                $learner,
                $enrollmentId,
                $mission->missionId,
                (string) Str::uuid(),
            ),
        );
        $this->contexts()->run(
            $learnerContext,
            fn (): bool => $attempts->respond(
                $learner,
                $fourth['id'],
                $mission->checkId,
                'not-a-greeting',
                null,
                (string) Str::uuid(),
            ),
        );
        $fourthResult = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->submit(
                $learner,
                $fourth['id'],
                (string) Str::uuid(),
            ),
        );
        self::assertSame('incorrect', $fourthResult['check_state']);
        self::assertSame('mastered', $fourthResult['knowledge_state']);
        self::assertSame(
            $fourth['id'],
            $fourthResult['evidence']['latest_attempt_id'],
        );
        self::assertSame(
            $second['id'],
            $fourthResult['evidence']['best_attempt_id'],
        );

        $offlineBundle = $this->contexts()->run(
            $learnerContext,
            fn (): array => $this->app->make(
                OfflineMissionBundleService::class,
            )->issue(
                $learner,
                $enrollmentId,
                $mission->missionId,
            ),
        );
        self::assertSame(
            'global-english.offline-mission-bundle.v1',
            $offlineBundle['manifest']['schema'],
        );
        self::assertSame(
            $mission->correctOptionId,
            $offlineBundle['manifest']['mission']['offline_check'][
                'correct_option_id'
            ],
        );
        self::assertSame(
            $mission->modelAudioContentHash,
            $offlineBundle['manifest']['mission']['model_audio'][
                'content_hash'
            ],
        );
        self::assertArrayNotHasKey(
            'storage_object_key',
            $offlineBundle['manifest']['mission']['model_audio'],
        );
        $offlineCommandId = (string) Str::uuid();
        $offlineClientAttemptId = (string) Str::uuid();
        $offlineCompletions = $this->app->make(
            OfflineCompletionService::class,
        );
        $offlineResult = $this->contexts()->run(
            $learnerContext,
            fn (): array => $offlineCompletions->complete(
                learner: $learner,
                bundleId: $offlineBundle['manifest']['bundle_id'],
                lease: $offlineBundle['lease'],
                clientAttemptId: $offlineClientAttemptId,
                checkId: $mission->checkId,
                selectedOptionId: 'polite-greeting',
                localRecording: null,
                idempotencyKey: $offlineCommandId,
            ),
        );
        $offlineReplay = $this->contexts()->run(
            $learnerContext,
            fn (): array => $offlineCompletions->complete(
                learner: $learner,
                bundleId: $offlineBundle['manifest']['bundle_id'],
                lease: $offlineBundle['lease'],
                clientAttemptId: $offlineClientAttemptId,
                checkId: $mission->checkId,
                selectedOptionId: 'polite-greeting',
                localRecording: null,
                idempotencyKey: $offlineCommandId,
            ),
        );

        self::assertSame($offlineResult, $offlineReplay);
        self::assertSame('correct', $offlineResult['check_state']);
        self::assertSame('unassessed', $offlineResult['spoken_state']);
        self::assertFalse(
            $offlineResult['local_capture_receipt_present'],
        );
        self::assertSame(
            $offlineResult['attempt_id'],
            $offlineResult['evidence']['latest_attempt_id'],
        );
        self::assertSame(
            1,
            DB::table('learning_commands')
                ->where('organization_id', $organization->getKey())
                ->where('operation', 'learner.offline.complete')
                ->where('aggregate_id', $enrollmentId)
                ->where('idempotency_key', $offlineCommandId)
                ->count(),
        );
        $this->assertLearningIdempotencyConflict(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $offlineCompletions->complete(
                    learner: $learner,
                    bundleId: $offlineBundle['manifest']['bundle_id'],
                    lease: $offlineBundle['lease'],
                    clientAttemptId: $offlineClientAttemptId,
                    checkId: $mission->checkId,
                    selectedOptionId: 'not-a-greeting',
                    localRecording: null,
                    idempotencyKey: $offlineCommandId,
                ),
            ),
        );
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $otherContext,
                fn () => $offlineCompletions->complete(
                    learner: $otherLearner,
                    bundleId: $offlineBundle['manifest']['bundle_id'],
                    lease: $offlineBundle['lease'],
                    clientAttemptId: (string) Str::uuid(),
                    checkId: $mission->checkId,
                    selectedOptionId: 'polite-greeting',
                    localRecording: null,
                    idempotencyKey: (string) Str::uuid(),
                ),
            ),
        );
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $offlineCompletions->complete(
                    learner: $learner,
                    bundleId: $offlineBundle['manifest']['bundle_id'],
                    lease: $offlineBundle['lease'].'x',
                    clientAttemptId: (string) Str::uuid(),
                    checkId: $mission->checkId,
                    selectedOptionId: 'polite-greeting',
                    localRecording: null,
                    idempotencyKey: (string) Str::uuid(),
                ),
            ),
        );
        $expiringBundle = $this->contexts()->run(
            $learnerContext,
            fn (): array => $this->app->make(
                OfflineMissionBundleService::class,
            )->issue(
                $learner,
                $enrollmentId,
                $mission->missionId,
            ),
        );
        CarbonImmutable::setTestNow('2026-07-31T10:15:00+00:00');
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $offlineCompletions->complete(
                    learner: $learner,
                    bundleId: $expiringBundle['manifest']['bundle_id'],
                    lease: $expiringBundle['lease'],
                    clientAttemptId: (string) Str::uuid(),
                    checkId: $mission->checkId,
                    selectedOptionId: 'polite-greeting',
                    localRecording: null,
                    idempotencyKey: (string) Str::uuid(),
                ),
            ),
        );
        CarbonImmutable::setTestNow('2026-07-30T10:15:00+00:00');
        $attemptCountBeforeRejectedOfflineCommand = DB::table(
            'learner_attempts',
        )->count();
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $offlineCompletions->complete(
                    learner: $learner,
                    bundleId: $offlineBundle['manifest']['bundle_id'],
                    lease: $offlineBundle['lease'],
                    clientAttemptId: (string) Str::uuid(),
                    checkId: $mission->checkId,
                    selectedOptionId: 'unknown-option',
                    localRecording: null,
                    idempotencyKey: (string) Str::uuid(),
                ),
            ),
        );
        self::assertSame(
            $attemptCountBeforeRejectedOfflineCommand,
            DB::table('learner_attempts')->count(),
            'A rejected atomic offline command must roll back its started attempt.',
        );

        $delayedFirstSubmitReplay = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->submit(
                $learner,
                $first['id'],
                $firstSubmitKey,
            ),
        );
        $delayedFirstStartReplay = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->start(
                $learner,
                $enrollmentId,
                $mission->missionId,
                $firstStartKey,
            ),
        );
        self::assertSame($firstResult, $delayedFirstSubmitReplay);
        self::assertSame([
            'id' => $first['id'],
            'status' => 'in_progress',
            'replayed' => true,
        ], $delayedFirstStartReplay);

        $poisonedAttemptId = (string) Str::ulid();
        DB::table('learner_attempts')->insert([
            'id' => $poisonedAttemptId,
            'organization_id' => $organization->getKey(),
            'enrollment_id' => $enrollmentId,
            'learner_membership_id' => $learnerMembership->getKey(),
            'curriculum_owner_organization_id' => $mission
                ->ownerOrganizationId,
            'curriculum_version_id' => $mission->curriculumVersionId,
            'curriculum_publication_id' => $mission->publicationId,
            'curriculum_mission_id' => $mission->missionId,
            'learning_objective_id' => $mission->objectiveId,
            'publication_manifest_hash' => $mission->manifestHash,
            'mission_definition_hash' => hash(
                'sha256',
                'poisoned-open-attempt-definition',
            ),
            'rule_version' => 'daily-speaking-v1',
            'attempt_number' => 6,
            'status' => 'in_progress',
            'started_at' => now(),
            'submitted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $attempts->start(
                    $learner,
                    $enrollmentId,
                    $mission->missionId,
                    (string) Str::uuid(),
                ),
            ),
        );
        $progress = $this->contexts()->run(
            $learnerContext,
            fn (): array => $attempts->progress(
                $learner,
                $enrollmentId,
            ),
        );
        self::assertSame('mastered', $progress['objectives'][0]['knowledge_state']);
        self::assertSame('unassessed', $progress['objectives'][0]['spoken_state']);
        self::assertFalse(
            $progress['objectives'][0]['local_capture_receipt_present'],
        );
        self::assertSame(
            $second['id'],
            $progress['objectives'][0]['evidence']['best_attempt_id'],
        );
        self::assertSame(
            $offlineResult['attempt_id'],
            $progress['objectives'][0]['evidence']['latest_attempt_id'],
        );
        $this->assertLearningRejected(
            fn () => $this->contexts()->run(
                $learnerContext,
                fn () => $attempts->respond(
                    $learner,
                    $poisonedAttemptId,
                    $mission->checkId,
                    'polite-greeting',
                    null,
                    (string) Str::uuid(),
                ),
            ),
        );
        self::assertSame(
            0,
            DB::table('item_responses')
                ->where('learner_attempt_id', $poisonedAttemptId)
                ->count(),
        );

        $this->assertLearningSchemaRejected(
            fn (): int => DB::table('learner_attempts')
                ->where('id', $first['id'])
                ->update([
                    'rule_version' => 'unsupported-future-rule',
                    'updated_at' => now(),
                ]),
        );
        $this->assertLearningSchemaRejected(
            fn (): int => DB::table('mastery_states')
                ->where('organization_id', $organization->getKey())
                ->where('enrollment_id', $enrollmentId)
                ->where('learning_objective_id', $mission->objectiveId)
                ->update([
                    'id' => (string) Str::ulid(),
                    'updated_at' => now(),
                ]),
        );
        $lateResponse = [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'learner_attempt_id' => $fourth['id'],
            'learner_membership_id' => $learnerMembership->getKey(),
            'curriculum_owner_organization_id' => $mission
                ->ownerOrganizationId,
            'curriculum_version_id' => $mission->curriculumVersionId,
            'curriculum_mission_id' => $mission->missionId,
            'learning_item_id' => $mission->learningItemId,
            'response_number' => 2,
            'check_id' => $mission->checkId,
            'selected_option_id' => 'not-a-greeting',
            'check_correct' => false,
            'client_capture_id' => null,
            'capture_mime_type' => null,
            'capture_byte_size' => null,
            'capture_duration_ms' => null,
            'captured_at' => null,
            'created_at' => now(),
        ];
        $this->assertLearningSchemaRejected(
            fn (): bool => DB::table('item_responses')
                ->insert($lateResponse),
        );
        $this->assertLearningSchemaRejected(
            fn (): bool => DB::table('item_responses')->insert([
                ...$lateResponse,
                'id' => (string) Str::ulid(),
                'learner_attempt_id' => $poisonedAttemptId,
                'response_number' => 1,
                'check_id' => 'Invalid Check Identifier',
                'created_at' => now(),
            ]),
            '23514',
        );

        foreach ([
            'capture_mime_type',
            'capture_byte_size',
            'capture_duration_ms',
            'captured_at',
        ] as $missingReceiptField) {
            $receipt = [
                ...$lateResponse,
                'id' => (string) Str::ulid(),
                'learner_attempt_id' => $poisonedAttemptId,
                'response_number' => 1,
                'client_capture_id' => (string) Str::uuid(),
                'capture_mime_type' => 'audio/webm',
                'capture_byte_size' => 512,
                'capture_duration_ms' => 1200,
                'captured_at' => now(),
                'created_at' => now(),
            ];
            $receipt[$missingReceiptField] = null;
            $this->assertLearningSchemaRejected(
                fn (): bool => DB::table('item_responses')
                    ->insert($receipt),
            );
        }

        $this->assertLearningSchemaRejected(
            function () use ($poisonedAttemptId): void {
                $submittedAt = CarbonImmutable::now();
                DB::table('learner_attempts')
                    ->where('id', $poisonedAttemptId)
                    ->update([
                        'status' => 'submitted',
                        'submitted_at' => $submittedAt,
                        'updated_at' => $submittedAt,
                    ]);
                DB::statement(
                    'SET CONSTRAINTS learner_attempts_submission_required IMMEDIATE',
                );
            },
        );
        $this->assertLearningSchemaRejected(
            fn () => $this->directlyFinalizeIncorrectAttempt(
                $poisonedAttemptId,
                $mission->learningItemId,
                [
                    'knowledge_score' => 100,
                    'knowledge_check_correct' => true,
                    'knowledge_state' => 'mastered',
                    'result_key' => 'mission.passed',
                ],
            ),
        );
        $this->assertLearningSchemaRejected(
            function () use (
                $poisonedAttemptId,
                $mission,
            ): void {
                $submittedAt = $this->directlyFinalizeIncorrectAttempt(
                    $poisonedAttemptId,
                    $mission->learningItemId,
                );
                $attempt = DB::table('learner_attempts')
                    ->where('id', $poisonedAttemptId)
                    ->firstOrFail();
                DB::table('mastery_states')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $attempt->organization_id,
                    'enrollment_id' => $attempt->enrollment_id,
                    'learner_membership_id' => $attempt
                        ->learner_membership_id,
                    'curriculum_owner_organization_id' => $attempt
                        ->curriculum_owner_organization_id,
                    'curriculum_version_id' => $attempt
                        ->curriculum_version_id,
                    'curriculum_publication_id' => $attempt
                        ->curriculum_publication_id,
                    'learning_objective_id' => $attempt
                        ->learning_objective_id,
                    'knowledge_state' => 'mastered',
                    'spoken_state' => 'unassessed',
                    'best_knowledge_score' => 100,
                    'evidence_attempt_id' => $poisonedAttemptId,
                    'rule_version' => 'daily-speaking-v1',
                    'mastered_at' => $submittedAt,
                    'lock_version' => 0,
                    'created_at' => $submittedAt,
                    'updated_at' => $submittedAt,
                ]);
            },
        );
        $this->assertLearningSchemaRejected(
            function () use (
                $poisonedAttemptId,
                $mission,
            ): void {
                $submittedAt = $this->directlyFinalizeIncorrectAttempt(
                    $poisonedAttemptId,
                    $mission->learningItemId,
                );
                $attempt = DB::table('learner_attempts')
                    ->where('id', $poisonedAttemptId)
                    ->firstOrFail();
                DB::table('review_schedules')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $attempt->organization_id,
                    'enrollment_id' => $attempt->enrollment_id,
                    'learner_membership_id' => $attempt
                        ->learner_membership_id,
                    'curriculum_owner_organization_id' => $attempt
                        ->curriculum_owner_organization_id,
                    'curriculum_version_id' => $attempt
                        ->curriculum_version_id,
                    'curriculum_publication_id' => $attempt
                        ->curriculum_publication_id,
                    'learning_objective_id' => $attempt
                        ->learning_objective_id,
                    'source_attempt_id' => $poisonedAttemptId,
                    'schedule_kind' => 'delayed_review',
                    'rule_version' => 'daily-speaking-v1',
                    'timezone' => 'Asia/Kolkata',
                    'due_at' => $submittedAt->addDay(),
                    'created_at' => $submittedAt,
                ]);
            },
        );
    }

    public function test_pr006_zero_mission_publication_and_multiple_mission_delivery_fail_closed(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:15:00+00:00');
        config()->set('learning.model_audio_disk', 'local');
        Storage::fake('local');
        $zeroMissionOrganization = $this->createOrganization(
            'Zero Daily Mission Tenant',
        );
        $zeroMissionAuthority = $this->createPublicationAuthority(
            $zeroMissionOrganization,
        );
        $zeroMissionVersion = $this->createReadyVersion(
            authority: $zeroMissionAuthority,
            versionKey: 'zero-daily-speaking-v1',
            dailyMission: true,
        );
        DB::table('mission_items')
            ->where(
                'organization_id',
                $zeroMissionOrganization->getKey(),
            )
            ->where(
                'curriculum_version_id',
                $zeroMissionVersion['version_id'],
            )
            ->delete();
        DB::table('curriculum_missions')
            ->where(
                'organization_id',
                $zeroMissionOrganization->getKey(),
            )
            ->where(
                'curriculum_version_id',
                $zeroMissionVersion['version_id'],
            )
            ->delete();
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $zeroMissionAuthority['operator_context'],
                fn () => $this->app->make(CurriculumPublisher::class)
                    ->publish(
                        actor: $zeroMissionAuthority['operator'],
                        curriculumVersionId: $zeroMissionVersion[
                            'version_id'
                        ],
                        expectedHeadVersion: 0,
                        idempotencyKey: 'publish-zero-daily-speaking-v1',
                    ),
            ),
        );
        self::assertSame(
            0,
            DB::table('curriculum_publications')
                ->where(
                    'organization_id',
                    $zeroMissionOrganization->getKey(),
                )
                ->where(
                    'curriculum_version_id',
                    $zeroMissionVersion['version_id'],
                )
                ->count(),
        );

        $organization = $this->createOrganization(
            'Ambiguous Daily Mission Tenant',
        );
        $authority = $this->createPublicationAuthority($organization);
        $version = $this->createReadyVersion(
            authority: $authority,
            versionKey: 'ambiguous-daily-speaking-v1',
            dailyMission: true,
            additionalMission: true,
        );
        $audio = 'governed-ambiguous-model-audio';
        $rights = $this->attachApprovedMediaRights(
            authority: $authority,
            version: $version,
            usageKey: 'model_audio',
            audio: $audio,
        );
        Storage::disk('local')->put(
            $rights['storage_object_key'],
            $audio,
        );
        $publication = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $this->app->make(CurriculumPublisher::class)
                ->publish(
                    actor: $authority['operator'],
                    curriculumVersionId: $version['version_id'],
                    expectedHeadVersion: 0,
                    idempotencyKey: 'publish-ambiguous-daily-speaking-v1',
                ),
        );
        $manifestJson = DB::table('curriculum_publications')
            ->where('organization_id', $organization->getKey())
            ->where('id', $publication->publicationId)
            ->value('manifest');
        self::assertIsString($manifestJson);
        $manifest = json_decode(
            $manifestJson,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($manifest);
        self::assertCount(2, $manifest['missions']);
        self::assertSame(
            $publication->manifestHash,
            CanonicalJson::sha256($manifest),
        );

        $learner = $this->createUser();
        [$learnerMembership, $learnerContext] = $this->createMembership(
            $organization,
            $learner,
        );
        $this->grantPermissions(
            membership: $learnerMembership,
            context: $learnerContext,
            permissionKeys: ['learning.participate'],
        );
        $cohort = $this->createCohort(
            $authority['operator_context'],
            'Ambiguous daily speaking learners',
        );
        $enrollmentId = (string) Str::ulid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $learnerMembership->getKey(),
            'cohort_id' => $cohort->getKey(),
            'status' => 'pending_curriculum',
            'enrolled_at' => now(),
            'ended_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->contexts()->run(
            $authority['operator_context'],
            fn () => $this->app->make(EnrollmentCurriculumPinner::class)
                ->activate(
                    actor: $authority['operator'],
                    enrollmentId: $enrollmentId,
                    publicationId: $publication->publicationId,
                    shareGrantId: null,
                    idempotencyKey: 'pin-ambiguous-daily-speaking-learner',
                ),
        );
        $this->contexts()->run(
            $learnerContext,
            fn () => $this->app->make(
                LearnerOnboardingService::class,
            )->complete(
                learner: $learner,
                adultConfirmed: true,
                goalKey: 'everyday-conversation',
                idempotencyKey: (string) Str::uuid(),
            ),
        );
        $reader = $this->app->make(DailyMissionReader::class);
        $operations = [
            fn () => $reader->first($learner, $enrollmentId),
            fn () => $reader->resolve(
                $learner,
                $enrollmentId,
                $version['mission_id'],
            ),
            fn () => $reader->resolve(
                $learner,
                $enrollmentId,
                (string) Str::ulid(),
            ),
        ];
        $rejections = 0;

        foreach ($operations as $operation) {
            try {
                $this->contexts()->run($learnerContext, $operation);
                self::fail(
                    'An ambiguous published mission manifest must fail closed.',
                );
            } catch (LearningOperationRejected) {
                $rejections++;
            }
        }

        self::assertSame(3, $rejections);
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

    public function test_schedule_cancellation_replays_blocks_activation_and_permits_one_guarded_replacement(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T16:00:00+00:00');
        $organization = $this->createOrganization('Schedule Cancellation Tenant');
        $authority = $this->createPublicationAuthority($organization);
        $version = $this->createReadyVersion($authority, 'cancel-v1');
        $scheduler = $this->app->make(CurriculumPublicationScheduler::class);
        $cancellations = $this->app->make(
            CurriculumPublicationScheduleCancellationService::class,
        );
        $activationAt = CarbonImmutable::parse('2026-07-30T18:00:00+00:00');
        $scheduled = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $scheduler->schedule(
                actor: $authority['operator'],
                curriculumVersionId: $version['version_id'],
                activationAt: $activationAt,
                expectedHeadVersion: 0,
                idempotencyKey: 'schedule-before-cancellation',
            ),
        );
        $cancelled = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $cancellations->cancel(
                actor: $authority['operator'],
                publicationId: $scheduled->publicationId,
                expectedHeadVersion: 1,
                reasonKey: 'timing_changed',
                idempotencyKey: 'cancel-schedule-v1',
            ),
        );
        $cancelReplay = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $cancellations->cancel(
                actor: $authority['operator'],
                publicationId: $scheduled->publicationId,
                expectedHeadVersion: 1,
                reasonKey: 'timing_changed',
                idempotencyKey: 'cancel-schedule-v1',
            ),
        );

        self::assertFalse($cancelled->replayed);
        self::assertTrue($cancelReplay->replayed);
        self::assertTrue($cancelled->cancelledAt->equalTo($cancelReplay->cancelledAt));
        self::assertSame(2, $cancelled->headVersion);
        self::assertSame(2, $cancelReplay->headVersion);
        $this->assertDatabaseHas('publication_heads', [
            'organization_id' => $organization->getKey(),
            'program_id' => $authority['program_id'],
            'active_publication_id' => null,
            'lock_version' => 2,
        ]);
        $cancelledStatus = $this->contexts()->run(
            $authority['operator_context'],
            fn (): array => $this->app
                ->make(OperatorPublicationStatusQuery::class)
                ->list($authority['operator']),
        );
        self::assertSame(
            'cancelled',
            $cancelledStatus['items'][0]['lifecycle_status'],
        );
        self::assertSame([], $cancelledStatus['items'][0]['allowed_actions']);

        CarbonImmutable::setTestNow($activationAt);
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $this->app
                    ->make(CurriculumPublicationActivator::class)
                    ->activate(
                        actor: $authority['operator'],
                        publicationId: $scheduled->publicationId,
                        expectedHeadVersion: 2,
                        idempotencyKey: 'activate-cancelled-schedule',
                    ),
            ),
        );

        CarbonImmutable::setTestNow('2026-07-30T16:30:00+00:00');
        $replacementAt = CarbonImmutable::parse('2026-07-30T19:00:00+00:00');
        $replacement = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $scheduler->schedule(
                actor: $authority['operator'],
                curriculumVersionId: $version['version_id'],
                activationAt: $replacementAt,
                expectedHeadVersion: 2,
                idempotencyKey: 'replacement-schedule-v1',
            ),
        );
        self::assertSame($scheduled->publicationId, $replacement->publicationId);
        self::assertSame($scheduled->manifestHash, $replacement->manifestHash);
        self::assertSame(3, $replacement->headVersion);
        self::assertSame(
            1,
            DB::table('curriculum_publications')
                ->where('organization_id', $organization->getKey())
                ->where('curriculum_version_id', $version['version_id'])
                ->count(),
        );

        $otherVersion = $this->createReadyVersion($authority, 'cancel-v2');
        $this->assertOperationRejected(
            fn () => $this->contexts()->run(
                $authority['operator_context'],
                fn () => $scheduler->schedule(
                    actor: $authority['operator'],
                    curriculumVersionId: $otherVersion['version_id'],
                    activationAt: $replacementAt->addHour(),
                    expectedHeadVersion: 3,
                    idempotencyKey: 'overlapping-schedule-v2',
                ),
            ),
        );

        $replacementCancellation = $this->contexts()->run(
            $authority['operator_context'],
            fn () => $cancellations->cancel(
                actor: $authority['operator'],
                publicationId: $replacement->publicationId,
                expectedHeadVersion: 3,
                reasonKey: 'release_withdrawn',
                idempotencyKey: 'cancel-replacement-v1',
            ),
        );
        self::assertSame(4, $replacementCancellation->headVersion);
        self::assertSame(
            2,
            DB::table('publication_events')
                ->where('organization_id', $organization->getKey())
                ->where('curriculum_publication_id', $scheduled->publicationId)
                ->where('event_type', 'scheduled')
                ->count(),
        );
        self::assertSame(
            2,
            DB::table('publication_events')
                ->where('organization_id', $organization->getKey())
                ->where('curriculum_publication_id', $scheduled->publicationId)
                ->where('event_type', 'schedule_cancelled')
                ->count(),
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
        $authenticationSession = $this
            ->createTestPrivilegedAuthenticationSession(
                $authority['operator'],
                now(),
                [
                    'idle_expires_at' => now()->addMinutes(30),
                    'absolute_expires_at' => now()->addHours(6),
                ],
            );
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
     * @param  array<string, mixed>  $authority
     * @param  array<string, string>  $version
     * @param  array{
     *   media_asset_id: string,
     *   rights_revision_id: string,
     *   asset_hash: string,
     *   evidence_hash: string,
     *   confirmer_membership_id: string,
     *   appointment_id: string,
     *   appointment_event_id: string,
     *   storage_object_key: string,
     *   audio: string
     * }  $rights
     * @return array{
     *   scan_id: string,
     *   metadata_revision_id: string,
     *   english_review_id: string,
     *   metadata_variant_id: string,
     *   kannada_review_id: string
     * }
     */
    private function attachManagedMediaGovernance(
        array $authority,
        array $version,
        array $rights,
    ): array {
        $organizationId = (string) $authority['organization_id'];
        $assetId = $rights['media_asset_id'];
        $assetHash = $rights['asset_hash'];
        $audio = $rights['audio'];
        $scanId = (string) Str::ulid();
        $metadataRevisionId = (string) Str::ulid();
        $metadataVariantId = (string) Str::ulid();
        $englishReviewId = (string) Str::ulid();
        $kannadaReviewId = (string) Str::ulid();
        $operatorMembershipId = (string) $authority[
            'operator_membership'
        ]->getKey();
        $englishTranscript = 'Hello. How are you today?';
        $kannadaTranscript = 'ನಮಸ್ಕಾರ. ಇಂದು ನೀವು ಹೇಗಿದ್ದೀರಿ?';
        $metadataHash = CanonicalJson::sha256([
            'asset_kind' => 'model_audio',
            'locale' => 'en-IN',
            'operator_label' => 'Governed workspace model audio',
            'text_alternative' => null,
            'transcript' => $englishTranscript,
        ]);
        $variantHash = CanonicalJson::sha256([
            'asset_kind' => 'model_audio',
            'locale' => 'kn-IN',
            'source_content_hash' => $metadataHash,
            'text_alternative' => null,
            'transcript' => $kannadaTranscript,
        ]);
        $technicalHash = CanonicalJson::sha256([
            'asset_kind' => 'model_audio',
            'byte_size' => strlen($audio),
            'detected_mime_type' => 'audio/ogg',
            'duration_milliseconds' => 1400,
            'inspection_outcome' => 'passed',
            'inspector_name' => 'synthetic-managed-inspector',
            'inspector_policy_version' => 'test-managed-v1',
            'malware_outcome' => 'clean',
            'object_hash' => $assetHash,
            'scanner_engine_version' => 'test-managed-v1',
            'scanner_name' => 'synthetic-managed-scanner',
            'scanner_policy_version' => 'test-managed-v1',
        ]);
        $now = now();

        DB::table('media_security_scans')->insert([
            'id' => $scanId,
            'organization_id' => $organizationId,
            'media_upload_id' => null,
            'media_asset_id' => $assetId,
            'asset_kind' => 'model_audio',
            'object_hash' => $assetHash,
            'byte_size' => strlen($audio),
            'inspection_outcome' => 'passed',
            'malware_outcome' => 'clean',
            'detected_mime_type' => 'audio/ogg',
            'duration_milliseconds' => 1400,
            'inspector_name' => 'synthetic-managed-inspector',
            'inspector_policy_version' => 'test-managed-v1',
            'scanner_name' => 'synthetic-managed-scanner',
            'scanner_engine_version' => 'test-managed-v1',
            'scanner_policy_version' => 'test-managed-v1',
            'technical_metadata_hash' => $technicalHash,
            'inspected_at' => $now,
            'scanned_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_asset_lifecycles')->insert([
            'organization_id' => $organizationId,
            'media_asset_id' => $assetId,
            'asset_kind' => 'model_audio',
            'state' => 'active',
            'version' => 1,
            'selected_scan_id' => $scanId,
            'predecessor_media_asset_id' => null,
            'managed_by_membership_id' => $operatorMembershipId,
            'retired_by_membership_id' => null,
            'retirement_reason_key' => null,
            'managed_at' => $now,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_asset_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'media_asset_id' => $assetId,
            'event_type' => 'asset.adopted',
            'lifecycle_version' => 1,
            'selected_scan_id' => $scanId,
            'related_media_asset_id' => null,
            'media_upload_id' => null,
            'actor_membership_id' => $operatorMembershipId,
            'reason_key' => null,
            'occurred_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_asset_metadata_revisions')->insert([
            'id' => $metadataRevisionId,
            'organization_id' => $organizationId,
            'media_asset_id' => $assetId,
            'asset_kind' => 'model_audio',
            'parent_revision_id' => null,
            'revision_number' => 1,
            'operator_label' => 'Governed workspace model audio',
            'transcript' => $englishTranscript,
            'text_alternative' => null,
            'content_hash' => $metadataHash,
            'authored_by_membership_id' => $authority['author_membership_id'],
            'authored_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_asset_metadata_heads')->insert([
            'organization_id' => $organizationId,
            'media_asset_id' => $assetId,
            'metadata_revision_id' => $metadataRevisionId,
            'version' => 1,
            'updated_by_membership_id' => $authority['author_membership_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_asset_metadata_variants')->insert([
            'id' => $metadataVariantId,
            'organization_id' => $organizationId,
            'media_asset_id' => $assetId,
            'metadata_revision_id' => $metadataRevisionId,
            'asset_kind' => 'model_audio',
            'locale' => 'kn-IN',
            'parent_variant_id' => null,
            'revision_number' => 1,
            'source_content_hash' => $metadataHash,
            'transcript' => $kannadaTranscript,
            'text_alternative' => null,
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $authority[
                'translator_membership_id'
            ],
            'translated_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_asset_metadata_variant_heads')->insert([
            'organization_id' => $organizationId,
            'metadata_revision_id' => $metadataRevisionId,
            'metadata_variant_id' => $metadataVariantId,
            'version' => 1,
            'updated_by_membership_id' => $authority[
                'translator_membership_id'
            ],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_asset_metadata_reviews')->insert([
            [
                'id' => $englishReviewId,
                'organization_id' => $organizationId,
                'media_asset_id' => $assetId,
                'program_id' => $authority['program_id'],
                'metadata_revision_id' => $metadataRevisionId,
                'source_content_hash' => $metadataHash,
                'review_kind' => 'academic_en',
                'metadata_variant_id' => null,
                'variant_content_hash' => null,
                'decision' => 'approved',
                'rationale_key' => null,
                'authority_appointment_id' => $authority[
                    'english_appointment_id'
                ],
                'authority_appointment_event_id' => $authority[
                    'english_appointment_event_id'
                ],
                'reviewer_membership_id' => $authority[
                    'english_reviewer_membership_id'
                ],
                'reviewed_at' => $now,
                'created_at' => $now,
            ],
            [
                'id' => $kannadaReviewId,
                'organization_id' => $organizationId,
                'media_asset_id' => $assetId,
                'program_id' => $authority['program_id'],
                'metadata_revision_id' => $metadataRevisionId,
                'source_content_hash' => $metadataHash,
                'review_kind' => 'kannada',
                'metadata_variant_id' => $metadataVariantId,
                'variant_content_hash' => $variantHash,
                'decision' => 'approved',
                'rationale_key' => null,
                'authority_appointment_id' => $authority[
                    'kannada_appointment_id'
                ],
                'authority_appointment_event_id' => $authority[
                    'kannada_appointment_event_id'
                ],
                'reviewer_membership_id' => $authority[
                    'kannada_reviewer_membership_id'
                ],
                'reviewed_at' => $now,
                'created_at' => $now,
            ],
        ]);
        DB::table('media_attachment_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'program_id' => $authority['program_id'],
            'curriculum_version_id' => $version['version_id'],
            'source_revision_id' => $version['source_revision_id'],
            'media_asset_id' => $assetId,
            'usage_key' => 'model_audio',
            'previous_workspace_version' => 1,
            'successor_workspace_version' => 2,
            'selected_scan_id' => $scanId,
            'metadata_revision_id' => $metadataRevisionId,
            'english_review_id' => $englishReviewId,
            'metadata_variant_id' => $metadataVariantId,
            'kannada_review_id' => $kannadaReviewId,
            'media_rights_revision_id' => $rights['rights_revision_id'],
            'attached_by_membership_id' => $operatorMembershipId,
            'attached_at' => $now,
            'created_at' => $now,
        ]);

        return [
            'scan_id' => $scanId,
            'metadata_revision_id' => $metadataRevisionId,
            'english_review_id' => $englishReviewId,
            'metadata_variant_id' => $metadataVariantId,
            'kannada_review_id' => $kannadaReviewId,
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
     *   graph_review_id: string,
     *   mission_id: string,
     *   learning_item_id: string
     * }
     */
    private function createReadyVersion(
        array $authority,
        string $versionKey,
        bool $includeKannadaReview = true,
        bool $includeGraphReview = true,
        bool $dailyMission = false,
        bool $additionalMission = false,
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
        $sourcePayload = $dailyMission
            ? [
                'schema' => 'global-english.daily-speaking-mission.v1',
                'goal_key' => 'everyday-conversation',
                'estimated_minutes' => 5,
                'title' => 'Greet a neighbour',
                'model_text' => 'Hello. How are you today?',
                'transcript' => 'Hello. How are you today?',
                'can_do' => 'I can greet a neighbour politely.',
                'model_audio_duration_ms' => 1400,
                'check' => [
                    'id' => 'daily-greeting-check',
                    'prompt' => 'Which option is a polite greeting?',
                    'options' => [
                        [
                            'id' => 'polite-greeting',
                            'label' => 'Hello. How are you today?',
                        ],
                        [
                            'id' => 'not-a-greeting',
                            'label' => 'Close the door.',
                        ],
                    ],
                    'correct_option_id' => 'polite-greeting',
                ],
            ]
            : [
                'instruction' => sprintf(
                    'Synthetic English instruction for %s.',
                    $versionKey,
                ),
            ];
        $variantPayload = $dailyMission
            ? [
                'schema' => 'global-english.daily-speaking-mission.v1',
                'guidance' => 'ನೆರೆಯವರಿಗೆ ವಿನಯವಾಗಿ ಶುಭಾಶಯ ಹೇಳಿ.',
            ]
            : [
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
        if ($additionalMission) {
            $additionalMissionId = (string) Str::ulid();
            DB::table('curriculum_missions')->insert([
                'id' => $additionalMissionId,
                'organization_id' => $authority['organization_id'],
                'curriculum_version_id' => $versionId,
                'instruction_content_document_id' => $documentId,
                'key' => 'additional-mission-'.$versionKey,
                'sequence' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('mission_items')->insert([
                'organization_id' => $authority['organization_id'],
                'curriculum_version_id' => $versionId,
                'mission_id' => $additionalMissionId,
                'learning_item_id' => $itemId,
                'sequence' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

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
            'mission_id' => $missionId,
            'learning_item_id' => $itemId,
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
        $this->grantTestPermissions(
            $membership,
            $context,
            $permissionKeys,
            'publication',
        );
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
     *   evidence_hash: string,
     *   confirmer_membership_id: string,
     *   appointment_id: string,
     *   appointment_event_id: string,
     *   storage_object_key: string,
     *   audio: string
     * }
     */
    private function attachApprovedMediaRights(
        array $authority,
        array $version,
        ?CarbonInterface $expiresAt = null,
        string $usageKey = 'instruction_audio',
        string $audio = 'synthetic-scheduled-audio',
        string $assetKind = 'instruction_audio',
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
        $assetHash = hash('sha256', $audio);
        $evidenceHash = hash('sha256', 'synthetic-scheduled-rights-evidence');
        $storageObjectKey = 'synthetic/scheduled-audio-'
            .strtolower($mediaAssetId).'.ogg';
        $now = now();

        DB::table('media_assets')->insert([
            'id' => $mediaAssetId,
            'organization_id' => $authority['organization_id'],
            'key' => 'scheduled-audio-'.strtolower((string) Str::ulid()),
            'asset_kind' => $assetKind,
            'storage_object_key' => $storageObjectKey,
            'mime_type' => 'audio/ogg',
            'byte_size' => strlen($audio),
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
            'usage_key' => $usageKey,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'media_asset_id' => $mediaAssetId,
            'rights_revision_id' => $rightsRevisionId,
            'asset_hash' => $assetHash,
            'evidence_hash' => $evidenceHash,
            'confirmer_membership_id' => (string) $confirmerMembership->getKey(),
            'appointment_id' => $appointmentId,
            'appointment_event_id' => $appointmentEventId,
            'storage_object_key' => $storageObjectKey,
            'audio' => $audio,
        ];
    }

    /**
     * @param  array<string, mixed>  $submissionOverrides
     */
    private function directlyFinalizeIncorrectAttempt(
        string $attemptId,
        string $learningItemId,
        array $submissionOverrides = [],
    ): CarbonImmutable {
        $attempt = DB::table('learner_attempts')
            ->where('id', $attemptId)
            ->firstOrFail();
        $submittedAt = CarbonImmutable::now();
        DB::table('item_responses')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $attempt->organization_id,
            'learner_attempt_id' => $attemptId,
            'learner_membership_id' => $attempt->learner_membership_id,
            'curriculum_owner_organization_id' => $attempt
                ->curriculum_owner_organization_id,
            'curriculum_version_id' => $attempt->curriculum_version_id,
            'curriculum_mission_id' => $attempt->curriculum_mission_id,
            'learning_item_id' => $learningItemId,
            'response_number' => 1,
            'check_id' => 'daily-greeting-check',
            'selected_option_id' => 'not-a-greeting',
            'check_correct' => false,
            'client_capture_id' => null,
            'capture_mime_type' => null,
            'capture_byte_size' => null,
            'capture_duration_ms' => null,
            'captured_at' => null,
            'created_at' => $submittedAt,
        ]);
        DB::table('learner_attempts')
            ->where('id', $attemptId)
            ->update([
                'status' => 'submitted',
                'submitted_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ]);
        DB::table('attempt_submissions')->insert([
            'organization_id' => $attempt->organization_id,
            'learner_attempt_id' => $attemptId,
            'learner_membership_id' => $attempt->learner_membership_id,
            'attempt_number' => $attempt->attempt_number,
            'knowledge_score' => 0,
            'knowledge_check_correct' => false,
            'local_capture_receipt_present' => false,
            'knowledge_state' => 'not_mastered',
            'spoken_state' => 'unassessed',
            'result_key' => 'mission.retry',
            'rule_version' => 'daily-speaking-v1',
            'submitted_at' => $submittedAt,
            'created_at' => $submittedAt,
            ...$submissionOverrides,
        ]);

        return $submittedAt;
    }

    private function installPr006SubmitOutboxFailureTrigger(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION test_pr006_fail_submit_outbox()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.event_type = 'learner.attempt.submitted' THEN
        RAISE EXCEPTION 'PR-006 forced late submit failure'
            USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER test_pr006_fail_submit_outbox
BEFORE INSERT ON outbox_events
FOR EACH ROW
EXECUTE FUNCTION test_pr006_fail_submit_outbox();
SQL);
    }

    private function removePr006SubmitOutboxFailureTrigger(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS test_pr006_fail_submit_outbox ON outbox_events;
DROP FUNCTION IF EXISTS test_pr006_fail_submit_outbox();
SQL);
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertLearningSchemaRejected(
        callable $operation,
        string $expectedCode = '55000',
    ): void {
        try {
            DB::transaction(function () use ($operation): void {
                $operation();
            });
            self::fail(
                'The PostgreSQL learning authority should have rejected the mutation.',
            );
        } catch (QueryException $exception) {
            self::assertSame(
                $expectedCode,
                (string) $exception->getCode(),
            );
        }
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
    private function assertLearningRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('The learning operation should have failed closed.');
        } catch (
            CurriculumOperationRejected
            |LearningOperationRejected
        ) {
            return;
        }
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function assertLearningIdempotencyConflict(
        callable $operation,
    ): void {
        try {
            $operation();
            self::fail(
                'The learning idempotency key should have conflicted.',
            );
        } catch (LearningIdempotencyConflict) {
            return;
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

        self::fail('The curriculum operation should have failed closed.');
    }
}
