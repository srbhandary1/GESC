<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\CurriculumPublisher;
use App\Learning\LevelVideos\LegacyLevelVideoCommissioner;
use App\Models\Membership;
use App\Tenancy\OrganizationContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;

final class LegacyLevelVideoCommissionerTest extends TenancyIntegrationTestCase
{
    public function test_import_creates_only_an_idempotent_review_blocked_draft(): void
    {
        $this->configureLocalStorage();

        $organization = $this->createOrganization(
            'Legacy Level Video Draft Tenant',
        );
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
                'curriculum.author',
                'curriculum.translate.kn',
                'curriculum.publish',
            ],
        );
        $videoOne = $this->writeTempVideo('level-one-draft-bytes');
        $videoTwo = $this->writeTempVideo('level-two-draft-bytes');
        $videos = [
            [
                'level_label' => 'Level 2',
                'absolute_path' => $videoTwo['path'],
                'sha256' => $videoTwo['sha256'],
                'bytes' => $videoTwo['bytes'],
            ],
            [
                'level_label' => 'Level 1',
                'absolute_path' => $videoOne['path'],
                'sha256' => $videoOne['sha256'],
                'bytes' => $videoOne['bytes'],
            ],
        ];
        $userCount = DB::table('users')->count();
        $membershipCount = DB::table('memberships')->count();
        $idempotencyKey = 'legacy-level-video-draft-001';

        $result = $this->contexts()->run(
            $operatorContext,
            fn (): array => $this->app
                ->make(LegacyLevelVideoCommissioner::class)
                ->commission(
                    actor: $operator,
                    videos: $videos,
                    idempotencyKey: $idempotencyKey,
                ),
        );

        self::assertSame(
            'draft_pending_independent_review',
            $result['workflow_state'],
        );
        self::assertFalse($result['replayed']);
        self::assertSame(
            [
                'graph_review',
                'academic_en_review',
                'kannada_review',
                'media_rights_confirmation',
            ],
            $result['publication_blockers'],
        );
        self::assertSame(
            ['Level 1', 'Level 2'],
            array_column($result['videos'], 'level_label'),
        );
        self::assertSame($userCount, DB::table('users')->count());
        self::assertSame(
            $membershipCount,
            DB::table('memberships')->count(),
        );
        self::assertSame(0, DB::table('authority_appointments')->count());
        self::assertSame(
            0,
            DB::table('authority_appointment_events')->count(),
        );
        self::assertSame(0, DB::table('curriculum_graph_reviews')->count());
        self::assertSame(0, DB::table('content_reviews')->count());
        self::assertSame(0, DB::table('curriculum_publications')->count());
        self::assertSame(0, DB::table('enrollments')->count());
        self::assertSame(
            3,
            DB::table('media_rights_revisions')
                ->where('evidence_status', 'pending')
                ->whereNull('confirmed_by_membership_id')
                ->whereNull('rights_appointment_id')
                ->whereNull('rights_appointment_event_id')
                ->count(),
        );
        self::assertSame(
            [(string) $operatorMembership->getKey()],
            DB::table('content_revisions')
                ->where('curriculum_version_id', $result['version_id'])
                ->distinct()
                ->pluck('authored_by_membership_id')
                ->all(),
        );
        self::assertSame(
            [(string) $operatorMembership->getKey()],
            DB::table('localized_variants')
                ->where('curriculum_version_id', $result['version_id'])
                ->distinct()
                ->pluck('translated_by_membership_id')
                ->all(),
        );
        self::assertCount(
            3,
            Storage::disk('local')->allFiles('legacy-level-videos'),
        );
        $this->assertDatabaseHas('audit_events', [
            'action' => 'curriculum.legacy_level_videos.draft_imported',
            'subject_id' => $result['version_id'],
            'actor_membership_id' => $operatorMembership->getKey(),
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'curriculum.legacy_level_videos.draft_imported',
            'aggregate_id' => $result['version_id'],
        ]);

        try {
            $this->contexts()->run(
                $operatorContext,
                fn () => $this->app->make(CurriculumPublisher::class)->publish(
                    actor: $operator,
                    curriculumVersionId: $result['version_id'],
                    expectedHeadVersion: 0,
                    idempotencyKey: 'publish-unreviewed-level-video-draft',
                ),
            );
            self::fail('Unreviewed legacy content must not publish.');
        } catch (CurriculumOperationRejected) {
            self::assertSame(
                0,
                DB::table('curriculum_publications')->count(),
            );
        }

        $replay = $this->contexts()->run(
            $operatorContext,
            fn (): array => $this->app
                ->make(LegacyLevelVideoCommissioner::class)
                ->commission(
                    actor: $operator,
                    videos: array_reverse($videos),
                    idempotencyKey: $idempotencyKey,
                ),
        );

        self::assertTrue($replay['replayed']);
        self::assertSame($result['version_id'], $replay['version_id']);
        self::assertSame($result['program_id'], $replay['program_id']);
        self::assertSame($result['videos'], $replay['videos']);
        self::assertSame(
            1,
            DB::table('programs')
                ->where('organization_id', $organization->getKey())
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('curriculum_versions')
                ->where('organization_id', $organization->getKey())
                ->count(),
        );
        self::assertCount(
            3,
            Storage::disk('local')->allFiles('legacy-level-videos'),
        );

        $this->expectException(CurriculumIdempotencyConflict::class);
        $this->contexts()->run(
            $operatorContext,
            fn (): array => $this->app
                ->make(LegacyLevelVideoCommissioner::class)
                ->commission(
                    actor: $operator,
                    videos: [$videos[0]],
                    idempotencyKey: $idempotencyKey,
                ),
        );
    }

    public function test_import_requires_both_draft_authoring_permissions(): void
    {
        $this->configureLocalStorage();

        $organization = $this->createOrganization(
            'Legacy Level Video Permission Tenant',
        );
        $operator = $this->createUser();
        [$operatorMembership, $operatorContext] = $this->createMembership(
            $organization,
            $operator,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermissions(
            membership: $operatorMembership,
            context: $operatorContext,
            permissionKeys: ['curriculum.author'],
        );
        $video = $this->writeTempVideo('permission-video-bytes');

        try {
            $this->contexts()->run(
                $operatorContext,
                fn (): array => $this->app
                    ->make(LegacyLevelVideoCommissioner::class)
                    ->commission(
                        actor: $operator,
                        videos: [[
                            'level_label' => 'Level 1',
                            'absolute_path' => $video['path'],
                            'sha256' => $video['sha256'],
                            'bytes' => $video['bytes'],
                        ]],
                        idempotencyKey: 'legacy-level-video-permission-001',
                    ),
            );
            self::fail('Translation permission must be required.');
        } catch (CurriculumOperationRejected) {
            self::assertSame(
                0,
                DB::table('programs')
                    ->where('organization_id', $organization->getKey())
                    ->count(),
            );
            self::assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_partial_object_upload_is_cleaned_before_database_failure_returns(): void
    {
        config()->set('learning.lesson_video_disk', 'video-test');
        config()->set('learning.model_audio_disk', 'audio-test');

        $organization = $this->createOrganization(
            'Legacy Level Video Upload Failure Tenant',
        );
        $operator = $this->createUser();
        [$operatorMembership, $operatorContext] = $this->createMembership(
            $organization,
            $operator,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermissions(
            membership: $operatorMembership,
            context: $operatorContext,
            permissionKeys: [
                'curriculum.author',
                'curriculum.translate.kn',
            ],
        );
        $video = $this->writeTempVideo('failed-upload-video-bytes');
        $audioStream = fopen('php://memory', 'w+b');

        if (! is_resource($audioStream)) {
            throw new RuntimeException('Unable to create an audio stream.');
        }

        fwrite($audioStream, 'legacy-import-scaffold-model-audio');
        rewind($audioStream);
        $audioDisk = Mockery::mock(Filesystem::class, [
            'put' => true,
            'readStream' => $audioStream,
            'exists' => true,
            'delete' => true,
        ]);
        $videoDisk = Mockery::mock(Filesystem::class, [
            'put' => false,
            'exists' => false,
        ]);
        Storage::shouldReceive('disk')
            ->twice()
            ->with('audio-test')
            ->andReturn($audioDisk);
        Storage::shouldReceive('disk')
            ->twice()
            ->with('video-test')
            ->andReturn($videoDisk);

        try {
            $this->contexts()->run(
                $operatorContext,
                fn (): array => $this->app
                    ->make(LegacyLevelVideoCommissioner::class)
                    ->commission(
                        actor: $operator,
                        videos: [[
                            'level_label' => 'Level 1',
                            'absolute_path' => $video['path'],
                            'sha256' => $video['sha256'],
                            'bytes' => $video['bytes'],
                        ]],
                        idempotencyKey: 'legacy-level-video-upload-failure',
                    ),
            );
            self::fail('A failed video upload must fail the import.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Unable to store legacy level video: Level 1.',
                $exception->getMessage(),
            );
            self::assertSame(
                0,
                DB::table('programs')
                    ->where('organization_id', $organization->getKey())
                    ->count(),
            );
            self::assertSame(
                0,
                DB::table('publication_commands')
                    ->where('organization_id', $organization->getKey())
                    ->count(),
            );
        }
    }

    public function test_identical_retry_keys_are_isolated_by_organization(): void
    {
        $this->configureLocalStorage();

        $firstOrganization = $this->createOrganization(
            'Legacy Level Video First Tenant',
        );
        $firstActor = $this->createUser();
        [$firstMembership, $firstContext] = $this->createMembership(
            $firstOrganization,
            $firstActor,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermissions(
            $firstMembership,
            $firstContext,
            ['curriculum.author', 'curriculum.translate.kn'],
        );
        $secondOrganization = $this->createOrganization(
            'Legacy Level Video Second Tenant',
        );
        $secondActor = $this->createUser();
        [$secondMembership, $secondContext] = $this->createMembership(
            $secondOrganization,
            $secondActor,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermissions(
            $secondMembership,
            $secondContext,
            ['curriculum.author', 'curriculum.translate.kn'],
        );
        $video = $this->writeTempVideo('tenant-isolated-video-bytes');
        $videos = [[
            'level_label' => 'Level 1',
            'absolute_path' => $video['path'],
            'sha256' => $video['sha256'],
            'bytes' => $video['bytes'],
        ]];
        $idempotencyKey = 'same-key-in-two-organizations';
        $commissioner = $this->app->make(
            LegacyLevelVideoCommissioner::class,
        );

        $first = $this->contexts()->run(
            $firstContext,
            fn (): array => $commissioner->commission(
                actor: $firstActor,
                videos: $videos,
                idempotencyKey: $idempotencyKey,
            ),
        );
        $second = $this->contexts()->run(
            $secondContext,
            fn (): array => $commissioner->commission(
                actor: $secondActor,
                videos: $videos,
                idempotencyKey: $idempotencyKey,
            ),
        );

        self::assertNotSame($first['program_id'], $second['program_id']);
        self::assertNotSame($first['version_id'], $second['version_id']);
        self::assertNotSame(
            $first['videos'][0]['asset_id'],
            $second['videos'][0]['asset_id'],
        );
        self::assertSame(
            1,
            DB::table('publication_commands')
                ->where(
                    'organization_id',
                    $firstOrganization->getKey(),
                )
                ->where('idempotency_key', $idempotencyKey)
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('publication_commands')
                ->where(
                    'organization_id',
                    $secondOrganization->getKey(),
                )
                ->where('idempotency_key', $idempotencyKey)
                ->count(),
        );
    }

    /**
     * @return array{path: string, sha256: string, bytes: int}
     */
    private function writeTempVideo(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'ge-level-video-draft-');

        if (! is_string($path)) {
            throw new RuntimeException(
                'Unable to allocate a temporary level-video fixture.',
            );
        }

        file_put_contents($path, $bytes);

        return [
            'path' => $path,
            'sha256' => hash('sha256', $bytes),
            'bytes' => strlen($bytes),
        ];
    }

    private function configureLocalStorage(): void
    {
        $storageRoot = sys_get_temp_dir()
            .'/ge-level-video-draft-'.strtolower((string) Str::ulid());

        if (! mkdir($storageRoot, 0700, true) && ! is_dir($storageRoot)) {
            throw new RuntimeException(
                'Unable to create a temporary level-video storage root.',
            );
        }

        config()->set('filesystems.disks.local.root', $storageRoot);
        config()->set('learning.lesson_video_disk', 'local');
        config()->set('learning.model_audio_disk', 'local');
        Storage::forgetDisk('local');
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
            'legacy-video-draft',
        );
    }
}
