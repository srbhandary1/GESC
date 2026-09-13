<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Curriculum\Enrollment\EnrollmentCurriculumPinner;
use App\Curriculum\Governance\AuthorityAppointmentCommands;
use App\Curriculum\Governance\AuthorityKind;
use App\Curriculum\Governance\ContentReviewCommands;
use App\Curriculum\Governance\CurriculumGraphGovernanceCommands;
use App\Curriculum\Governance\MediaRightsConfirmationCommands;
use App\Curriculum\Publication\CurriculumPublisher;
use App\Learning\LevelVideos\LegacyLevelVideoCommissioner;
use App\Models\Cohort;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class LevelVideoLearnerDeliveryTest extends TenancyIntegrationTestCase
{
    private const string OTP = '864297';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new LevelVideoLearnerDeliveryOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new LevelVideoLearnerDeliveryNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_commissioned_level_videos_are_listed_and_streamed_with_tenant_isolation(): void
    {
        $storageRoot = sys_get_temp_dir()
            .'/ge-level-video-'.strtolower((string) Str::ulid());
        mkdir($storageRoot, 0700, true);
        config()->set('filesystems.disks.local.root', $storageRoot);
        config()->set('learning.lesson_video_disk', 'local');
        config()->set('learning.model_audio_disk', 'local');
        Storage::forgetDisk('local');

        $learner = $this->signInWeb('+919876543240');
        $organization = $this->createOrganization(
            'Level Video Delivery Tenant',
        );
        $foreignOrganization = $this->createOrganization(
            'Level Video Foreign Tenant',
        );
        [$operatorMembership, $operatorContext] = $this->createMembership(
            $organization,
            $this->createUser(),
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            membership: $operatorMembership,
            context: $operatorContext,
            permissionKeys: [
                'curriculum.author',
                'curriculum.translate.kn',
                'curriculum.publish',
                'curriculum.authority.manage',
                'enrollments.curriculum.assign',
            ],
        );
        [$learnerMembership, $learnerContext] = $this->createMembership(
            $organization,
            $learner,
        );
        $this->grantPermissions(
            membership: $learnerMembership,
            context: $learnerContext,
            permissionKeys: ['learning.participate'],
        );

        $videoOne = $this->writeTempVideo('level-one-bytes');
        $videoTwo = $this->writeTempVideo('level-two-bytes!!');

        $commissioned = $this->contexts()->run(
            $operatorContext,
            fn (): array => $this->app->make(LegacyLevelVideoCommissioner::class)
                ->commission(
                    actor: User::query()->findOrFail(
                        $operatorMembership->user_id,
                    ),
                    videos: [
                        [
                            'level_label' => 'Level A',
                            'absolute_path' => $videoOne['path'],
                            'sha256' => $videoOne['sha256'],
                            'bytes' => $videoOne['bytes'],
                        ],
                        [
                            'level_label' => 'Level B',
                            'absolute_path' => $videoTwo['path'],
                            'sha256' => $videoTwo['sha256'],
                            'bytes' => $videoTwo['bytes'],
                        ],
                    ],
                    idempotencyKey: 'level-video-learner-delivery-import',
                ),
        );

        self::assertCount(2, $commissioned['videos']);
        $enrollmentId = $this->reviewPublishAndPin(
            organization: $organization,
            organizationId: (string) $organization->getKey(),
            operator: User::query()->findOrFail(
                $operatorMembership->user_id,
            ),
            operatorContext: $operatorContext,
            learnerMembership: $learnerMembership,
            programId: $commissioned['program_id'],
            versionId: $commissioned['version_id'],
        );
        $first = $commissioned['videos'][0];
        $second = $commissioned['videos'][1];

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => (string) $learnerMembership->getKey(),
            ])
            ->assertOk();

        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->putJson('/api/v1/me/onboarding', [
            'adult_confirmed' => true,
            'goal_key' => 'everyday-conversation',
        ])->assertOk();

        $queryCapture = new LevelVideoQueryCapture;
        DB::listen($queryCapture->record(...));

        $queryCapture->enabled = true;
        $catalog = $this->getJson(sprintf(
            '/api/v1/me/level-videos?enrollment_id=%s',
            $enrollmentId,
        ))
            ->assertOk()
            ->assertJsonPath('enrollment_id', $enrollmentId)
            ->assertJsonCount(2, 'videos')
            ->assertJsonPath('videos.0.level_label', 'Level A')
            ->assertJsonPath('videos.1.level_label', 'Level B');
        $queryCapture->enabled = false;
        $this->assertBoundedVideoDeliveryQueries($queryCapture->queries);
        $this->assertNoStore($catalog);

        $streamUrl = sprintf(
            '/api/v1/media-assets/%s/lesson-video?enrollment_id=%s&lesson_id=%s',
            $first['asset_id'],
            $enrollmentId,
            $first['lesson_id'],
        );
        $queryCapture->queries = [];
        $queryCapture->enabled = true;
        $stream = $this->get($streamUrl)
            ->assertOk()
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Length', (string) $videoOne['bytes'])
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('ETag', '"'.$videoOne['sha256'].'"');
        $queryCapture->enabled = false;
        $this->assertBoundedVideoDeliveryQueries($queryCapture->queries);
        $this->assertNoStore($stream);
        self::assertNull($stream->headers->get('Content-Range'));
        self::assertSame(
            'level-one-bytes',
            $stream->streamedContent(),
        );
        self::assertSame(
            $videoOne['sha256'],
            $stream->headers->get('X-Content-SHA256'),
        );

        $prefix = $this->withHeader('Range', 'bytes=0-4')
            ->get($streamUrl)
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Length', '5')
            ->assertHeader('Content-Range', 'bytes 0-4/15')
            ->assertHeader('ETag', '"'.$videoOne['sha256'].'"');
        self::assertSame('level', $prefix->streamedContent());

        $openEnded = $this->withHeader('Range', 'bytes=6-')
            ->get($streamUrl)
            ->assertStatus(206)
            ->assertHeader('Content-Length', '9')
            ->assertHeader('Content-Range', 'bytes 6-14/15');
        self::assertSame('one-bytes', $openEnded->streamedContent());

        $suffix = $this->withHeader('Range', 'bytes=-5')
            ->get($streamUrl)
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 10-14/15');
        self::assertSame('bytes', $suffix->streamedContent());

        $ifRange = $this->withHeaders([
            'Range' => 'bytes=0-4',
            'If-Range' => '"'.$videoOne['sha256'].'"',
        ])->get($streamUrl)
            ->assertStatus(206);
        self::assertSame('level', $ifRange->streamedContent());

        $staleIfRange = $this->withHeaders([
            'Range' => 'bytes=0-4',
            'If-Range' => '"'.str_repeat('f', 64).'"',
        ])->get($streamUrl)
            ->assertOk()
            ->assertHeader('Content-Length', '15');
        self::assertNull($staleIfRange->headers->get('Content-Range'));
        self::assertSame(
            'level-one-bytes',
            $staleIfRange->streamedContent(),
        );

        $multiple = $this->withoutHeader('If-Range')
            ->withHeader('Range', 'bytes=0-1, 4-5')
            ->get($streamUrl)
            ->assertOk();
        self::assertSame('level-one-bytes', $multiple->streamedContent());

        $unsatisfiable = $this->withHeader('Range', 'bytes=99-')
            ->getJson($streamUrl)
            ->assertStatus(416)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes */15')
            ->assertHeader('ETag', '"'.$videoOne['sha256'].'"')
            ->assertJsonPath('message_key', 'problem.range_not_satisfiable');
        $this->assertNoStore($unsatisfiable);
        $this->withoutHeader('Range');

        $this->get(sprintf(
            '/api/v1/media-assets/%s/lesson-video?enrollment_id=%s&lesson_id=%s',
            $second['asset_id'],
            $enrollmentId,
            $second['lesson_id'],
        ))
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4');

        $this->getJson(sprintf(
            '/api/v1/me/level-videos?enrollment_id=%s',
            (string) Str::ulid(),
        ))->assertNotFound();

        [$foreignLearnerMembership, $foreignLearnerContext]
            = $this->createMembership(
                $foreignOrganization,
                $learner,
            );
        $this->grantPermissions(
            membership: $foreignLearnerMembership,
            context: $foreignLearnerContext,
            permissionKeys: ['learning.participate'],
        );
        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => (string) $foreignLearnerMembership
                    ->getKey(),
            ])
            ->assertOk();
        $this->getJson(sprintf(
            '/api/v1/me/level-videos?enrollment_id=%s',
            $enrollmentId,
        ))->assertNotFound();
        $this->withHeader('Range', 'bytes=0-4')
            ->get($streamUrl)
            ->assertNotFound();
        $this->withoutHeader('Range');

        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => (string) $learnerMembership->getKey(),
            ])
            ->assertOk();

        $storageKey = DB::table('media_assets')
            ->where('organization_id', $organization->getKey())
            ->where('id', $first['asset_id'])
            ->value('storage_object_key');
        self::assertIsString($storageKey);
        Storage::forgetDisk('local');
        Storage::disk('local')->put($storageKey, 'tampered-video-bytes');

        $this->withHeader('Range', 'bytes=0-4')
            ->get($streamUrl)
            ->assertNotFound();
    }

    private function reviewPublishAndPin(
        Organization $organization,
        string $organizationId,
        User $operator,
        OrganizationContext $operatorContext,
        Membership $learnerMembership,
        string $programId,
        string $versionId,
    ): string {
        $englishReviewer = $this->createUser();
        [$englishMembership, $englishContext] = $this->createMembership(
            $organization,
            $englishReviewer,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermissions(
            $englishMembership,
            $englishContext,
            ['curriculum.review.en'],
        );
        $kannadaReviewer = $this->createUser();
        [$kannadaMembership, $kannadaContext] = $this->createMembership(
            $organization,
            $kannadaReviewer,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermissions(
            $kannadaMembership,
            $kannadaContext,
            ['curriculum.review.kn'],
        );
        $rightsConfirmer = $this->createUser();
        [$rightsMembership, $rightsContext] = $this->createMembership(
            $organization,
            $rightsConfirmer,
            Membership::TYPE_TRAINER,
        );
        $this->grantPermissions(
            $rightsMembership,
            $rightsContext,
            ['media.rights.approve'],
        );

        $appointments = $this->app->make(
            AuthorityAppointmentCommands::class,
        );
        $englishAppointmentId = $this->contexts()->run(
            $operatorContext,
            fn (): string => $appointments->appoint(
                actor: $operator,
                appointeeUserId: (string) $englishReviewer->getKey(),
                appointeeMembershipId: (string) $englishMembership->getKey(),
                authorityKind: AuthorityKind::EnglishAcademicReviewer,
                scopeType: 'program',
                programId: $programId,
                validFrom: now()->subMinute(),
                validUntil: now()->addYear(),
                qualificationBasis: 'Independent test English academic reviewer',
                approvalEvidenceKey: 'APP-TEST-LEVEL-VIDEO-EN',
                idempotencyKey: 'level-video-appoint-english',
            ),
        );
        $kannadaAppointmentId = $this->contexts()->run(
            $operatorContext,
            fn (): string => $appointments->appoint(
                actor: $operator,
                appointeeUserId: (string) $kannadaReviewer->getKey(),
                appointeeMembershipId: (string) $kannadaMembership->getKey(),
                authorityKind: AuthorityKind::KannadaReviewer,
                scopeType: 'program',
                programId: $programId,
                validFrom: now()->subMinute(),
                validUntil: now()->addYear(),
                qualificationBasis: 'Independent test Kannada reviewer',
                approvalEvidenceKey: 'APP-TEST-LEVEL-VIDEO-KN',
                idempotencyKey: 'level-video-appoint-kannada',
            ),
        );
        $rightsAppointmentId = $this->contexts()->run(
            $operatorContext,
            fn (): string => $appointments->appoint(
                actor: $operator,
                appointeeUserId: (string) $rightsConfirmer->getKey(),
                appointeeMembershipId: (string) $rightsMembership->getKey(),
                authorityKind: AuthorityKind::RightsConfirmer,
                scopeType: 'program',
                programId: $programId,
                validFrom: now()->subMinute(),
                validUntil: now()->addYear(),
                qualificationBasis: 'Independent test media-rights confirmer',
                approvalEvidenceKey: 'APP-TEST-LEVEL-VIDEO-RIGHTS',
                idempotencyKey: 'level-video-appoint-rights',
            ),
        );

        $this->contexts()->run(
            $englishContext,
            fn (): string => $appointments->accept(
                $englishReviewer,
                $englishAppointmentId,
                'level-video-accept-english',
            ),
        );
        $this->contexts()->run(
            $kannadaContext,
            fn (): string => $appointments->accept(
                $kannadaReviewer,
                $kannadaAppointmentId,
                'level-video-accept-kannada',
            ),
        );
        $this->contexts()->run(
            $rightsContext,
            fn (): string => $appointments->accept(
                $rightsConfirmer,
                $rightsAppointmentId,
                'level-video-accept-rights',
            ),
        );

        $graph = DB::table('curriculum_graph_revisions')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $versionId)
            ->sole(['id', 'graph_hash']);
        $this->contexts()->run(
            $englishContext,
            fn (): string => $this->app
                ->make(CurriculumGraphGovernanceCommands::class)
                ->review(
                    actor: $englishReviewer,
                    curriculumGraphRevisionId: (string) $graph->id,
                    graphHash: (string) $graph->graph_hash,
                    authorityAppointmentId: $englishAppointmentId,
                    decision: 'approved',
                    idempotencyKey: 'level-video-review-graph',
                ),
        );

        $content = DB::table('content_revisions as sources')
            ->join('localized_variants as variants', function ($join): void {
                $join
                    ->on(
                        'variants.organization_id',
                        '=',
                        'sources.organization_id',
                    )
                    ->on(
                        'variants.curriculum_version_id',
                        '=',
                        'sources.curriculum_version_id',
                    )
                    ->on(
                        'variants.source_revision_id',
                        '=',
                        'sources.id',
                    )
                    ->on(
                        'variants.source_content_hash',
                        '=',
                        'sources.content_hash',
                    );
            })
            ->where('sources.organization_id', $organizationId)
            ->where('sources.curriculum_version_id', $versionId)
            ->orderBy('sources.id')
            ->get([
                'sources.id as source_revision_id',
                'sources.content_hash as source_content_hash',
                'variants.id as localized_variant_id',
                'variants.content_hash as localized_content_hash',
            ]);
        $reviews = $this->app->make(ContentReviewCommands::class);

        foreach ($content as $index => $row) {
            $this->contexts()->run(
                $englishContext,
                fn (): string => $reviews->reviewEnglish(
                    actor: $englishReviewer,
                    sourceRevisionId: (string) $row->source_revision_id,
                    sourceContentHash: (string) $row->source_content_hash,
                    authorityAppointmentId: $englishAppointmentId,
                    decision: 'approved',
                    idempotencyKey: 'level-video-review-en-'.($index + 1),
                ),
            );
            $this->contexts()->run(
                $kannadaContext,
                fn (): string => $reviews->reviewKannada(
                    actor: $kannadaReviewer,
                    sourceRevisionId: (string) $row->source_revision_id,
                    sourceContentHash: (string) $row->source_content_hash,
                    localizedVariantId: (string) $row->localized_variant_id,
                    localizedContentHash: (string) $row->localized_content_hash,
                    authorityAppointmentId: $kannadaAppointmentId,
                    decision: 'approved',
                    idempotencyKey: 'level-video-review-kn-'.($index + 1),
                ),
            );
        }

        $pendingRights = DB::table('media_rights_revisions')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('revision_number', 1)
            ->where('evidence_status', 'pending')
            ->orderBy('media_asset_id')
            ->get();
        $rights = $this->app->make(
            MediaRightsConfirmationCommands::class,
        );

        foreach ($pendingRights as $index => $row) {
            $channels = $this->stringList(
                (string) $row->permitted_release_channels,
            );
            $territories = $this->stringList(
                (string) $row->permitted_territories,
            );

            $this->contexts()->run(
                $rightsContext,
                fn (): string => $rights->confirm(
                    actor: $rightsConfirmer,
                    programId: $programId,
                    mediaAssetId: (string) $row->media_asset_id,
                    selectedAssetHash: (string) $row->selected_asset_hash,
                    parentRevisionId: (string) $row->id,
                    evidenceHash: hash(
                        'sha256',
                        'level-video-approved-rights-'.($index + 1),
                    ),
                    rightsHolderReference: (string) $row->rights_holder_reference,
                    provenanceReference: (string) $row->provenance_reference,
                    rightsBasisKey: (string) $row->rights_basis_key,
                    licenseOrContractReference: 'APP-TEST-LEVEL-VIDEO-RIGHTS',
                    permittedReleaseChannels: $channels,
                    permittedTerritories: $territories,
                    learnerDeliveryAllowed: true,
                    sharingAllowed: false,
                    derivativeUseAllowed: false,
                    effectiveAt: now()->subMinute(),
                    expiresAt: null,
                    retentionPolicyKey: (string) $row->retention_policy_key,
                    deletionConstraintKey: (string) $row->deletion_constraint_key,
                    authorityAppointmentId: $rightsAppointmentId,
                    idempotencyKey: 'level-video-rights-'.($index + 1),
                ),
            );
        }

        $publication = $this->contexts()->run(
            $operatorContext,
            fn () => $this->app->make(CurriculumPublisher::class)->publish(
                actor: $operator,
                curriculumVersionId: $versionId,
                expectedHeadVersion: 0,
                idempotencyKey: 'level-video-publish-reviewed-draft',
            ),
        );
        $cohortId = (string) Str::ulid();
        $enrollmentId = (string) Str::ulid();
        $now = now();
        DB::table('cohorts')->insert([
            'id' => $cohortId,
            'organization_id' => $organizationId,
            'key' => 'level-video-test-'.strtolower((string) Str::ulid()),
            'name' => 'Level video test learners',
            'status' => Cohort::STATUS_ACTIVE,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organizationId,
            'learner_membership_id' => $learnerMembership->getKey(),
            'cohort_id' => $cohortId,
            'status' => 'pending_curriculum',
            'enrolled_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->contexts()->run(
            $operatorContext,
            fn () => $this->app
                ->make(EnrollmentCurriculumPinner::class)
                ->activate(
                    actor: $operator,
                    enrollmentId: $enrollmentId,
                    publicationId: $publication->publicationId,
                    shareGrantId: null,
                    idempotencyKey: 'level-video-pin-reviewed-publication',
                ),
        );

        return $enrollmentId;
    }

    /**
     * @return list<string>
     */
    private function stringList(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException('Invalid pending-rights fixture.');
        }

        $values = [];

        foreach ($decoded as $value) {
            if (! is_string($value)) {
                throw new RuntimeException(
                    'Invalid pending-rights fixture value.',
                );
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * @return array{path: string, sha256: string, bytes: int}
     */
    private function writeTempVideo(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'ge-level-video-');

        if (! is_string($path)) {
            throw new RuntimeException(
                'Unable to allocate a temporary level-video fixture.',
            );
        }

        $path .= '.mp4';
        file_put_contents($path, $bytes);

        return [
            'path' => $path,
            'sha256' => hash('sha256', $bytes),
            'bytes' => strlen($bytes),
        ];
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
            'level-video',
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
     * @param  TestResponse<Response>  $response
     */
    private function assertNoStore(TestResponse $response): void
    {
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    /**
     * @param  list<string>  $queries
     */
    private function assertBoundedVideoDeliveryQueries(array $queries): void
    {
        self::assertLessThanOrEqual(
            30,
            count($queries),
            implode("\n", $queries),
        );

        foreach ([
            'curriculum_publications' => 2,
            'publication_graph_components' => 1,
            'curriculum_graph_revisions' => 1,
            'publication_content_components' => 1,
            'publication_media_components' => 1,
            'media_rights_revisions' => 2,
            'authority_appointment_events' => 1,
            'content_revisions' => 1,
            'media_assets' => 1,
        ] as $table => $expected) {
            self::assertSame(
                $expected,
                count(array_filter(
                    $queries,
                    static fn (string $query): bool => preg_match(
                        '/\bfrom\s+["`]?'.preg_quote($table, '/').'["`]?\b/i',
                        $query,
                    ) === 1,
                )),
                sprintf('Unexpected %s query count.', $table),
            );
        }
    }
}

final class LevelVideoLearnerDeliveryOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class LevelVideoQueryCapture
{
    public bool $enabled = false;

    /** @var list<string> */
    public array $queries = [];

    public function record(QueryExecuted $query): void
    {
        if ($this->enabled) {
            $this->queries[] = $query->sql;
        }
    }
}

final class LevelVideoLearnerDeliveryNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(
        string $normalizedMobileNumber,
        string $code,
    ): void {
        //
    }
}
