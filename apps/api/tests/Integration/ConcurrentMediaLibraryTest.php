<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Exceptions\CurriculumHeadConflict;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\MediaRightsConfirmationCommands;
use App\Curriculum\Publication\CurriculumPublisher;
use App\Curriculum\Publication\PublicationEvidenceRevalidator;
use App\Curriculum\Publication\PublicationResult;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Operator\ContentWorkspace\ContentDraftGraphService;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryConflict;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Operator\MediaLibrary\MediaLibraryCommandResult;
use App\Operator\MediaLibrary\MediaLifecycleCommands;
use App\Operator\MediaLibrary\MediaMetadataCommands;
use App\Operator\MediaLibrary\MediaUploadCommands;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Carbon\CarbonImmutable;
use Closure;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * @phpstan-type Actor array{user_id: string, membership_id: string, role_id: string}
 * @phpstan-type RaceCommand array{
 *     operation: string,
 *     actor_user_id: string,
 *     actor_membership_id: string,
 *     payload: array<string, mixed>
 * }
 * @phpstan-type RaceResult array{outcome: string, result_id?: string}
 * @phpstan-type Worker array{pid: int, pipe: resource, reaped: bool}
 */
final class ConcurrentMediaLibraryTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The media-library concurrency gate requires pcntl.',
        );
        $this->seed(FoundationSeeder::class);
        Storage::fake('media_concurrency_quarantine');
        Storage::fake('media_concurrency_accepted');
        config()->set('media.quarantine_disk', 'media_concurrency_quarantine');
        config()->set(
            'media.accepted_disks.model_audio',
            'media_concurrency_accepted',
        );
    }

    public function test_upload_finalization_and_acceptance_have_one_exact_winner(): void
    {
        $organization = $this->createOrganization('Concurrent media upload');
        $first = $this->createActor(
            $organization,
            ['media.upload', 'media.quarantine.review'],
            'media-upload-first',
        );
        $second = $this->createActor(
            $organization,
            ['media.upload', 'media.quarantine.review'],
            'media-upload-second',
        );
        $bytes = 'RIFF'.pack('V', 24).'WAVEfmt concurrent-clean-audio';
        $hash = hash('sha256', $bytes);
        $started = $this->runAs(
            (string) $organization->getKey(),
            $first,
            fn (User $actor) => $this->app
                ->make(MediaUploadCommands::class)
                ->start(
                    actor: $actor,
                    assetKind: 'model_audio',
                    safeLabel: 'Concurrent upload',
                    totalBytes: strlen($bytes),
                    wholeObjectHash: $hash,
                    idempotencyKey: 'media-race-upload-start',
                ),
        );
        $uploadId = $started->resultId;
        $this->runAs(
            (string) $organization->getKey(),
            $first,
            fn (User $actor) => $this->app
                ->make(MediaUploadCommands::class)
                ->part(
                    actor: $actor,
                    uploadId: $uploadId,
                    partNumber: 1,
                    expectedUploadVersion: 1,
                    partHash: $hash,
                    bytes: $bytes,
                    idempotencyKey: 'media-race-upload-part',
                ),
        );
        $fixture = [
            'organization_id' => (string) $organization->getKey(),
            'upload_id' => $uploadId,
        ];
        $finalization = $this->runRace(
            $fixture,
            [
                $this->raceCommand('finalize', $first, [
                    'upload_id' => $uploadId,
                    'expected_upload_version' => 2,
                    'idempotency_key' => 'media-race-finalize-first',
                ]),
                $this->raceCommand('finalize', $second, [
                    'upload_id' => $uploadId,
                    'expected_upload_version' => 2,
                    'idempotency_key' => 'media-race-finalize-second',
                ]),
            ],
            fn (PDO $connection) => $this->lockRow(
                $connection,
                <<<'SQL'
                    select id
                    from media_uploads
                    where organization_id = :organization_id
                      and id = :upload_id
                    for update
                    SQL,
                $fixture,
            ),
        );

        $this->assertRace($finalization, ['conflict', 'finalized']);
        $this->assertDatabaseHas('media_uploads', [
            'organization_id' => $organization->getKey(),
            'id' => $uploadId,
            'state' => 'scan_clean',
            'version' => 4,
        ]);
        self::assertSame(1, DB::table('media_security_scans')
            ->where('organization_id', $organization->getKey())
            ->where('media_upload_id', $uploadId)
            ->count());

        $acceptance = $this->runRace(
            $fixture,
            [
                $this->raceCommand('accept', $first, [
                    'upload_id' => $uploadId,
                    'expected_upload_version' => 4,
                    'idempotency_key' => 'media-race-accept-first',
                ]),
                $this->raceCommand('accept', $second, [
                    'upload_id' => $uploadId,
                    'expected_upload_version' => 4,
                    'idempotency_key' => 'media-race-accept-second',
                ]),
            ],
            fn (PDO $connection) => $this->lockRow(
                $connection,
                <<<'SQL'
                    select id
                    from media_uploads
                    where organization_id = :organization_id
                      and id = :upload_id
                    for update
                    SQL,
                $fixture,
            ),
        );

        $this->assertRace($acceptance, ['accepted', 'conflict']);
        self::assertSame(1, DB::table('media_assets')
            ->where('organization_id', $organization->getKey())
            ->where('asset_hash', $hash)
            ->count());
        self::assertSame(1, DB::table('media_asset_lifecycles')
            ->where('organization_id', $organization->getKey())
            ->where('state', 'active')
            ->count());
    }

    public function test_metadata_variant_and_review_heads_have_one_exact_winner(): void
    {
        $organization = $this->createOrganization('Concurrent media metadata');
        $firstAuthor = $this->createActor(
            $organization,
            ['media.metadata.manage'],
            'media-metadata-first',
        );
        $secondAuthor = $this->createActor(
            $organization,
            ['media.metadata.manage'],
            'media-metadata-second',
        );
        $firstTranslator = $this->createActor(
            $organization,
            ['media.metadata.manage', 'curriculum.translate.kn'],
            'media-translation-first',
        );
        $secondTranslator = $this->createActor(
            $organization,
            ['media.metadata.manage', 'curriculum.translate.kn'],
            'media-translation-second',
        );
        $firstReviewer = $this->createActor(
            $organization,
            ['curriculum.review.en'],
            'media-review-first',
        );
        $secondReviewer = $this->createActor(
            $organization,
            ['curriculum.review.en'],
            'media-review-second',
        );
        $programId = $this->createProgram(
            $organization,
            $firstAuthor['membership_id'],
            'metadata-race',
        );
        $asset = $this->createManagedAsset(
            (string) $organization->getKey(),
            $firstAuthor['membership_id'],
            'metadata-race-audio',
        );
        $fixture = [
            'organization_id' => (string) $organization->getKey(),
            'media_asset_id' => $asset['id'],
        ];
        $english = $this->runRace(
            $fixture,
            [
                $this->raceCommand('append_english', $firstAuthor, [
                    'media_asset_id' => $asset['id'],
                    'operator_label' => 'First concurrent label',
                    'transcript' => 'First concurrent transcript.',
                    'idempotency_key' => 'media-race-english-first',
                ]),
                $this->raceCommand('append_english', $secondAuthor, [
                    'media_asset_id' => $asset['id'],
                    'operator_label' => 'Second concurrent label',
                    'transcript' => 'Second concurrent transcript.',
                    'idempotency_key' => 'media-race-english-second',
                ]),
            ],
            fn (PDO $connection) => $this->lockRow(
                $connection,
                <<<'SQL'
                    select media_asset_id
                    from media_asset_lifecycles
                    where organization_id = :organization_id
                      and media_asset_id = :media_asset_id
                    for update
                    SQL,
                $fixture,
            ),
        );

        $this->assertRace($english, ['conflict', 'metadata_appended']);
        $metadataHead = DB::table('media_asset_metadata_heads')
            ->where('organization_id', $organization->getKey())
            ->where('media_asset_id', $asset['id'])
            ->sole(['metadata_revision_id', 'version']);
        self::assertSame(1, $metadataHead->version);
        $metadataRevision = DB::table('media_asset_metadata_revisions')
            ->where('organization_id', $organization->getKey())
            ->where('id', $metadataHead->metadata_revision_id)
            ->sole(['id', 'content_hash']);
        $variantFixture = [
            ...$fixture,
            'metadata_revision_id' => (string) $metadataRevision->id,
        ];
        $variant = $this->runRace(
            $variantFixture,
            [
                $this->raceCommand('append_kannada', $firstTranslator, [
                    'metadata_revision_id' => (string) $metadataRevision->id,
                    'source_content_hash' => (string) $metadataRevision->content_hash,
                    'transcript' => 'ಮೊದಲ ಸಮಕಾಲೀನ ಅನುವಾದ.',
                    'idempotency_key' => 'media-race-kannada-first',
                ]),
                $this->raceCommand('append_kannada', $secondTranslator, [
                    'metadata_revision_id' => (string) $metadataRevision->id,
                    'source_content_hash' => (string) $metadataRevision->content_hash,
                    'transcript' => 'ಎರಡನೇ ಸಮಕಾಲೀನ ಅನುವಾದ.',
                    'idempotency_key' => 'media-race-kannada-second',
                ]),
            ],
            fn (PDO $connection) => $this->lockRow(
                $connection,
                <<<'SQL'
                    select id
                    from media_asset_metadata_revisions
                    where organization_id = :organization_id
                      and id = :metadata_revision_id
                    for update
                    SQL,
                $variantFixture,
            ),
        );

        $this->assertRace($variant, ['conflict', 'variant_appended']);
        $variantHead = DB::table('media_asset_metadata_variant_heads')
            ->where('organization_id', $organization->getKey())
            ->where('metadata_revision_id', $metadataRevision->id)
            ->sole(['metadata_variant_id', 'version']);
        self::assertSame(1, $variantHead->version);
        $firstAppointment = $this->insertAcceptedAppointment(
            organizationId: (string) $organization->getKey(),
            programId: $programId,
            appointeeUserId: $firstReviewer['user_id'],
            appointeeMembershipId: $firstReviewer['membership_id'],
            appointedByMembershipId: $firstAuthor['membership_id'],
            authorityKind: 'english_academic_reviewer',
        );
        $secondAppointment = $this->insertAcceptedAppointment(
            organizationId: (string) $organization->getKey(),
            programId: $programId,
            appointeeUserId: $secondReviewer['user_id'],
            appointeeMembershipId: $secondReviewer['membership_id'],
            appointedByMembershipId: $firstAuthor['membership_id'],
            authorityKind: 'english_academic_reviewer',
        );
        $review = $this->runRace(
            $variantFixture,
            [
                $this->raceCommand('review_english', $firstReviewer, [
                    'program_id' => $programId,
                    'metadata_revision_id' => (string) $metadataRevision->id,
                    'source_content_hash' => (string) $metadataRevision->content_hash,
                    'authority_appointment_id' => $firstAppointment['id'],
                    'idempotency_key' => 'media-race-review-first',
                ]),
                $this->raceCommand('review_english', $secondReviewer, [
                    'program_id' => $programId,
                    'metadata_revision_id' => (string) $metadataRevision->id,
                    'source_content_hash' => (string) $metadataRevision->content_hash,
                    'authority_appointment_id' => $secondAppointment['id'],
                    'idempotency_key' => 'media-race-review-second',
                ]),
            ],
            fn (PDO $connection) => $this->lockRow(
                $connection,
                <<<'SQL'
                    select id
                    from media_asset_metadata_revisions
                    where organization_id = :organization_id
                      and id = :metadata_revision_id
                    for update
                    SQL,
                $variantFixture,
            ),
        );

        $this->assertRace($review, ['conflict', 'reviewed']);
        self::assertSame(1, DB::table('media_asset_metadata_reviews')
            ->where('organization_id', $organization->getKey())
            ->where('program_id', $programId)
            ->where('metadata_revision_id', $metadataRevision->id)
            ->where('review_kind', 'academic_en')
            ->count());
    }

    public function test_rights_successor_serializes_and_appointment_invalidation_waits(): void
    {
        $organization = $this->createOrganization('Concurrent media rights');
        $manager = $this->createActor($organization, [], 'media-rights-manager');
        $first = $this->createActor(
            $organization,
            ['media.rights.approve'],
            'media-rights-first',
        );
        $second = $this->createActor(
            $organization,
            ['media.rights.approve'],
            'media-rights-second',
        );
        $third = $this->createActor(
            $organization,
            ['media.rights.approve'],
            'media-rights-third',
        );
        $programId = $this->createProgram(
            $organization,
            $manager['membership_id'],
            'rights-race',
        );
        $asset = $this->createManagedAsset(
            (string) $organization->getKey(),
            $manager['membership_id'],
            'rights-race-audio',
        );
        $appointments = [];

        foreach ([$first, $second, $third] as $index => $actor) {
            $appointments[$index] = $this->insertAcceptedAppointment(
                organizationId: (string) $organization->getKey(),
                programId: $programId,
                appointeeUserId: $actor['user_id'],
                appointeeMembershipId: $actor['membership_id'],
                appointedByMembershipId: $manager['membership_id'],
                authorityKind: 'rights_confirmer',
            );
        }

        $initialRightsId = $this->runAs(
            (string) $organization->getKey(),
            $first,
            fn (User $actor): string => $this->confirmRights(
                $actor,
                $programId,
                $asset['id'],
                $asset['hash'],
                null,
                $appointments[0]['id'],
                'initial',
            ),
        );
        $fixture = [
            'organization_id' => (string) $organization->getKey(),
            'program_id' => $programId,
        ];
        $race = $this->runRace(
            $fixture,
            [
                $this->raceCommand('confirm_rights', $first, [
                    'program_id' => $programId,
                    'media_asset_id' => $asset['id'],
                    'selected_asset_hash' => $asset['hash'],
                    'parent_revision_id' => $initialRightsId,
                    'authority_appointment_id' => $appointments[0]['id'],
                    'suffix' => 'successor-first',
                ]),
                $this->raceCommand('confirm_rights', $second, [
                    'program_id' => $programId,
                    'media_asset_id' => $asset['id'],
                    'selected_asset_hash' => $asset['hash'],
                    'parent_revision_id' => $initialRightsId,
                    'authority_appointment_id' => $appointments[1]['id'],
                    'suffix' => 'successor-second',
                ]),
            ],
            fn (PDO $connection) => $this->lockRow(
                $connection,
                <<<'SQL'
                    select id
                    from programs
                    where organization_id = :organization_id
                      and id = :program_id
                    for update
                    SQL,
                $fixture,
            ),
        );

        $this->assertRace($race, ['conflict', 'rights_confirmed']);
        self::assertSame(2, DB::table('media_rights_revisions')
            ->where('organization_id', $organization->getKey())
            ->where('program_id', $programId)
            ->where('media_asset_id', $asset['id'])
            ->count());
        $successor = DB::table('media_rights_revisions')
            ->where('organization_id', $organization->getKey())
            ->where('program_id', $programId)
            ->where('media_asset_id', $asset['id'])
            ->where('revision_number', 2)
            ->sole(['parent_revision_id']);
        self::assertSame($initialRightsId, $successor->parent_revision_id);

        $invalidationAsset = $this->createManagedAsset(
            (string) $organization->getKey(),
            $manager['membership_id'],
            'appointment-invalidation-audio',
        );
        $invalidation = $this->openPostgreSqlConnection(
            DB::connection()->getConfig(),
        );
        $invalidation->exec("set lock_timeout = '250ms'");
        DB::beginTransaction();

        try {
            $rightsId = $this->runAs(
                (string) $organization->getKey(),
                $third,
                fn (User $actor): string => $this->confirmRights(
                    $actor,
                    $programId,
                    $invalidationAsset['id'],
                    $invalidationAsset['hash'],
                    null,
                    $appointments[2]['id'],
                    'appointment-lock',
                ),
            );
            self::assertNotSame('', $rightsId);
            $statement = $invalidation->prepare(
                <<<'SQL'
                    insert into authority_appointment_events (
                        id,
                        organization_id,
                        authority_appointment_id,
                        event_type,
                        actor_membership_id,
                        reason_key,
                        invalidates_prior_approvals,
                        effective_at,
                        created_at,
                        updated_at
                    ) values (
                        :id,
                        :organization_id,
                        :authority_appointment_id,
                        'revoked',
                        :actor_membership_id,
                        'concurrent_invalidation',
                        false,
                        :at,
                        :at,
                        :at
                    )
                    SQL,
            );

            try {
                $statement->execute([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organization->getKey(),
                    'authority_appointment_id' => $appointments[2]['id'],
                    'actor_membership_id' => $manager['membership_id'],
                    'at' => now()->toISOString(),
                ]);
                self::fail(
                    'Appointment invalidation must wait for rights confirmation.',
                );
            } catch (PDOException $exception) {
                self::assertSame('55P03', $exception->getCode());
            }
        } finally {
            DB::rollBack();
        }

        self::assertSame(0, DB::table('media_rights_revisions')
            ->where('organization_id', $organization->getKey())
            ->where('media_asset_id', $invalidationAsset['id'])
            ->count());
    }

    public function test_replacement_serializes_and_permission_invalidation_waits(): void
    {
        $organization = $this->createOrganization('Concurrent media lifecycle');
        $first = $this->createActor(
            $organization,
            ['media.replace'],
            'media-replace-first',
        );
        $second = $this->createActor(
            $organization,
            ['media.replace'],
            'media-replace-second',
        );
        $retirer = $this->createActor(
            $organization,
            ['media.retire'],
            'media-retire-lock',
        );
        $predecessor = $this->createManagedAsset(
            (string) $organization->getKey(),
            $first['membership_id'],
            'replacement-predecessor',
        );
        $successor = $this->createManagedAsset(
            (string) $organization->getKey(),
            $first['membership_id'],
            'replacement-successor',
        );
        $fixture = [
            'organization_id' => (string) $organization->getKey(),
            'media_asset_id' => $predecessor['id'],
        ];
        $race = $this->runRace(
            $fixture,
            [
                $this->raceCommand('replace', $first, [
                    'predecessor_media_asset_id' => $predecessor['id'],
                    'successor_media_asset_id' => $successor['id'],
                    'idempotency_key' => 'media-race-replace-first',
                ]),
                $this->raceCommand('replace', $second, [
                    'predecessor_media_asset_id' => $predecessor['id'],
                    'successor_media_asset_id' => $successor['id'],
                    'idempotency_key' => 'media-race-replace-second',
                ]),
            ],
            fn (PDO $connection) => $this->lockRow(
                $connection,
                <<<'SQL'
                    select media_asset_id
                    from media_asset_lifecycles
                    where organization_id = :organization_id
                      and media_asset_id = :media_asset_id
                    for update
                    SQL,
                $fixture,
            ),
        );

        $this->assertRace($race, ['conflict', 'replaced']);
        self::assertSame(1, DB::table('media_asset_replacements')
            ->where('organization_id', $organization->getKey())
            ->where('predecessor_media_asset_id', $predecessor['id'])
            ->where('successor_media_asset_id', $successor['id'])
            ->count());
        $this->assertDatabaseHas('media_asset_lifecycles', [
            'organization_id' => $organization->getKey(),
            'media_asset_id' => $successor['id'],
            'predecessor_media_asset_id' => $predecessor['id'],
            'version' => 2,
        ]);

        $permissionAsset = $this->createManagedAsset(
            (string) $organization->getKey(),
            $retirer['membership_id'],
            'permission-invalidation-audio',
        );
        $revoker = $this->openPostgreSqlConnection(
            DB::connection()->getConfig(),
        );
        $revoker->exec("set lock_timeout = '250ms'");
        DB::beginTransaction();

        try {
            $retired = $this->runAs(
                (string) $organization->getKey(),
                $retirer,
                fn (User $actor) => $this->app
                    ->make(MediaLifecycleCommands::class)
                    ->retire(
                        actor: $actor,
                        assetId: $permissionAsset['id'],
                        expectedLifecycleVersion: 1,
                        reasonKey: 'concurrency_permission_lock',
                        idempotencyKey: 'media-race-permission-retire',
                    ),
            );
            self::assertSame($permissionAsset['id'], $retired->resultId);
            $delete = $revoker->prepare(
                <<<'SQL'
                    delete from role_permissions
                    where organization_id = :organization_id
                      and role_id = :role_id
                      and permission_id = (
                          select id from permissions where key = 'media.retire'
                      )
                    SQL,
            );

            try {
                $delete->execute([
                    'organization_id' => $organization->getKey(),
                    'role_id' => $retirer['role_id'],
                ]);
                self::fail(
                    'Permission invalidation must wait for the media command.',
                );
            } catch (PDOException $exception) {
                self::assertSame('55P03', $exception->getCode());
            }
        } finally {
            DB::rollBack();
        }

        $this->assertDatabaseHas('media_asset_lifecycles', [
            'organization_id' => $organization->getKey(),
            'media_asset_id' => $permissionAsset['id'],
            'state' => 'active',
            'version' => 1,
        ]);
        self::assertSame(1, DB::table('role_permissions')
            ->where('organization_id', $organization->getKey())
            ->where('role_id', $retirer['role_id'])
            ->count());
    }

    public function test_attachment_serializes_and_retirement_cannot_overtake_publication(): void
    {
        $organization = $this->createOrganization(
            'Concurrent media attachment publication',
        );
        $firstAttacher = $this->createActor(
            $organization,
            ['curriculum.author', 'media.metadata.manage'],
            'media-attach-first',
        );
        $secondAttacher = $this->createActor(
            $organization,
            ['curriculum.author', 'media.metadata.manage'],
            'media-attach-second',
        );
        $publisher = $this->createActor(
            $organization,
            ['curriculum.publish'],
            'media-publication-actor',
        );
        $retirer = $this->createActor(
            $organization,
            ['media.retire'],
            'media-publication-retirer',
        );
        $translator = $this->createActor(
            $organization,
            [],
            'media-publication-translator',
        );
        $englishReviewer = $this->createActor(
            $organization,
            [],
            'media-publication-english-reviewer',
        );
        $kannadaReviewer = $this->createActor(
            $organization,
            [],
            'media-publication-kannada-reviewer',
        );
        $rightsConfirmer = $this->createActor(
            $organization,
            [],
            'media-publication-rights-confirmer',
        );
        $programId = $this->createProgram(
            $organization,
            $firstAttacher['membership_id'],
            'attachment-publication-race',
        );
        $workspace = $this->createWorkspaceSource(
            organizationId: (string) $organization->getKey(),
            programId: $programId,
            creatorMembershipId: $firstAttacher['membership_id'],
        );
        $asset = $this->createManagedAsset(
            (string) $organization->getKey(),
            $firstAttacher['membership_id'],
            'attachment-publication-race-audio',
        );
        $governance = $this->createManagedGovernance(
            organizationId: (string) $organization->getKey(),
            programId: $programId,
            asset: $asset,
            metadataAuthor: $firstAttacher,
            translator: $translator,
            englishReviewer: $englishReviewer,
            kannadaReviewer: $kannadaReviewer,
            rightsConfirmer: $rightsConfirmer,
        );
        $fixture = [
            'organization_id' => (string) $organization->getKey(),
            'program_id' => $programId,
            'curriculum_version_id' => $workspace['curriculum_version_id'],
        ];
        $attachmentPayload = [
            'media_asset_id' => $asset['id'],
            'program_id' => $programId,
            'curriculum_version_id' => $workspace['curriculum_version_id'],
            'source_revision_id' => $workspace['source_revision_id'],
            'metadata_revision_id' => $governance['metadata_revision_id'],
            'metadata_content_hash' => $governance['metadata_content_hash'],
            'english_review_id' => $governance['english_review_id'],
            'metadata_variant_id' => $governance['metadata_variant_id'],
            'variant_content_hash' => $governance['variant_content_hash'],
            'kannada_review_id' => $governance['kannada_review_id'],
            'media_rights_revision_id' => $governance['rights_revision_id'],
        ];
        $attachment = $this->runRace(
            $fixture,
            [
                $this->raceCommand('attach', $firstAttacher, [
                    ...$attachmentPayload,
                    'idempotency_key' => 'media-race-attach-first',
                ]),
                $this->raceCommand('attach', $secondAttacher, [
                    ...$attachmentPayload,
                    'idempotency_key' => 'media-race-attach-second',
                ]),
            ],
            fn (PDO $connection) => $this->lockRow(
                $connection,
                <<<'SQL'
                    select curriculum_version_id
                    from curriculum_workspaces
                    where organization_id = :organization_id
                      and program_id = :program_id
                      and curriculum_version_id = :curriculum_version_id
                    for update
                    SQL,
                $fixture,
            ),
        );

        $this->assertRace($attachment, ['attached', 'conflict']);
        $this->assertDatabaseHas('curriculum_workspaces', [
            'organization_id' => $organization->getKey(),
            'curriculum_version_id' => $workspace['curriculum_version_id'],
            'state' => 'draft',
            'version' => 2,
        ]);
        self::assertSame(1, DB::table('media_attachment_events')
            ->where('organization_id', $organization->getKey())
            ->where('curriculum_version_id', $workspace['curriculum_version_id'])
            ->where('source_revision_id', $workspace['source_revision_id'])
            ->where('media_asset_id', $asset['id'])
            ->count());
        self::assertSame(1, DB::table('content_revision_media_assets')
            ->where('organization_id', $organization->getKey())
            ->where('curriculum_version_id', $workspace['curriculum_version_id'])
            ->where('content_revision_id', $workspace['source_revision_id'])
            ->where('media_asset_id', $asset['id'])
            ->count());

        $this->completePublishableWorkspace(
            organizationId: (string) $organization->getKey(),
            programId: $programId,
            workspace: $workspace,
            contentAuthor: $firstAttacher,
            translator: $translator,
            governance: $governance,
        );
        $publicationFixture = [
            ...$fixture,
            'media_asset_id' => $asset['id'],
        ];
        $publicationRace = $this->runRace(
            $publicationFixture,
            [
                $this->raceCommand('publish', $publisher, [
                    'curriculum_version_id' => $workspace['curriculum_version_id'],
                    'idempotency_key' => 'media-race-publish',
                ]),
                $this->raceCommand('retire', $retirer, [
                    'media_asset_id' => $asset['id'],
                    'idempotency_key' => 'media-race-retire',
                ]),
            ],
            fn (PDO $connection) => $this->lockRow(
                $connection,
                <<<'SQL'
                    select media_asset_id
                    from media_asset_lifecycles
                    where organization_id = :organization_id
                      and media_asset_id = :media_asset_id
                    for update
                    SQL,
                $publicationFixture,
            ),
        );

        self::assertTrue(
            $publicationRace['both_blocked'],
            'Publication and retirement must both enter the lifecycle lock queue.',
        );
        $outcomes = collect($publicationRace['results'])
            ->pluck('outcome')
            ->sort()
            ->values()
            ->all();
        self::assertContains(
            $outcomes,
            [
                ['conflict', 'retired'],
                ['published', 'retired'],
            ],
        );
        $this->assertDatabaseHas('media_asset_lifecycles', [
            'organization_id' => $organization->getKey(),
            'media_asset_id' => $asset['id'],
            'state' => 'retired',
            'version' => 2,
        ]);
        $publicationCount = DB::table('curriculum_publications')
            ->where('organization_id', $organization->getKey())
            ->where('program_id', $programId)
            ->count();
        self::assertContains($publicationCount, [0, 1]);
        self::assertSame(
            $publicationCount,
            DB::table('publication_media_governance_components')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $programId)
                ->where('media_asset_id', $asset['id'])
                ->count(),
        );

        if ($publicationCount === 1) {
            $publicationId = DB::table('curriculum_publications')
                ->where('organization_id', $organization->getKey())
                ->where('program_id', $programId)
                ->value('id');
            self::assertIsString($publicationId);
            $verified = $this->runAs(
                (string) $organization->getKey(),
                $publisher,
                fn () => $this->app
                    ->make(PublicationEvidenceRevalidator::class)
                    ->verify(
                        ownerOrganizationId: (string) $organization->getKey(),
                        publicationId: $publicationId,
                        evaluatedAt: now(),
                        requireCurrentManagedMedia: false,
                    ),
            );
            self::assertSame($publicationId, $verified->publicationId);
            $this->assertDatabaseHas('curriculum_workspaces', [
                'organization_id' => $organization->getKey(),
                'curriculum_version_id' => $workspace['curriculum_version_id'],
                'state' => 'sealed',
                'version' => 4,
            ]);
        } else {
            $this->assertDatabaseHas('curriculum_workspaces', [
                'organization_id' => $organization->getKey(),
                'curriculum_version_id' => $workspace['curriculum_version_id'],
                'state' => 'in_review',
                'version' => 3,
            ]);
        }
    }

    /**
     * @return array{
     *     curriculum_version_id: string,
     *     content_document_id: string,
     *     content_document_key: string,
     *     source_revision_id: string,
     *     source_content_hash: string
     * }
     */
    private function createWorkspaceSource(
        string $organizationId,
        string $programId,
        string $creatorMembershipId,
    ): array {
        $versionId = (string) Str::ulid();
        $documentId = (string) Str::ulid();
        $sourceRevisionId = (string) Str::ulid();
        $documentKey = 'instruction-media-concurrency';
        $sourcePayload = [
            'schema' => 'global-english.daily-speaking-mission.v1',
            'goal_key' => 'everyday-conversation',
            'estimated_minutes' => 5,
            'title' => 'Practise a concurrent greeting',
            'model_text' => 'Hello. How are you today?',
            'transcript' => 'Hello. How are you today?',
            'can_do' => 'I can greet a neighbour politely.',
            'model_audio_duration_ms' => 1500,
            'check' => [
                'id' => 'concurrent-greeting-check',
                'prompt' => 'Which option is a polite greeting?',
                'options' => [
                    ['id' => 'greeting', 'label' => 'Hello. How are you?'],
                    ['id' => 'command', 'label' => 'Close the door.'],
                ],
                'correct_option_id' => 'greeting',
            ],
        ];
        $sourceHash = CanonicalJson::sha256($sourcePayload);
        $now = now();
        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'key' => 'media-concurrency-v1',
            'created_by_membership_id' => $creatorMembershipId,
            'created_by_attribution' => 'synthetic-media-concurrency',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_workspaces')->insert([
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $versionId,
            'source_curriculum_version_id' => null,
            'source_curriculum_publication_id' => null,
            'graph_schema_version' => 2,
            'state' => 'draft',
            'version' => 1,
            'current_draft_revision_id' => null,
            'submitted_graph_revision_id' => null,
            'legacy_level_assignment_required' => false,
            'created_by_membership_id' => $creatorMembershipId,
            'sealed_at' => null,
            'abandoned_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_documents')->insert([
            'id' => $documentId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $versionId,
            'key' => $documentKey,
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
            'authored_by_membership_id' => $creatorMembershipId,
            'author_attribution' => 'synthetic-media-concurrency',
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

        return [
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'content_document_key' => $documentKey,
            'source_revision_id' => $sourceRevisionId,
            'source_content_hash' => $sourceHash,
        ];
    }

    /**
     * @param  array{id: string, hash: string, scan_id: string}  $asset
     * @param  Actor  $metadataAuthor
     * @param  Actor  $translator
     * @param  Actor  $englishReviewer
     * @param  Actor  $kannadaReviewer
     * @param  Actor  $rightsConfirmer
     * @return array{
     *     metadata_revision_id: string,
     *     metadata_content_hash: string,
     *     english_review_id: string,
     *     metadata_variant_id: string,
     *     variant_content_hash: string,
     *     kannada_review_id: string,
     *     rights_revision_id: string,
     *     rights_evidence_hash: string,
     *     english_appointment_id: string,
     *     english_appointment_event_id: string,
     *     english_reviewer_membership_id: string,
     *     kannada_appointment_id: string,
     *     kannada_appointment_event_id: string,
     *     kannada_reviewer_membership_id: string
     * }
     */
    private function createManagedGovernance(
        string $organizationId,
        string $programId,
        array $asset,
        array $metadataAuthor,
        array $translator,
        array $englishReviewer,
        array $kannadaReviewer,
        array $rightsConfirmer,
    ): array {
        $englishAppointment = $this->insertAcceptedAppointment(
            organizationId: $organizationId,
            programId: $programId,
            appointeeUserId: $englishReviewer['user_id'],
            appointeeMembershipId: $englishReviewer['membership_id'],
            appointedByMembershipId: $metadataAuthor['membership_id'],
            authorityKind: 'english_academic_reviewer',
        );
        $kannadaAppointment = $this->insertAcceptedAppointment(
            organizationId: $organizationId,
            programId: $programId,
            appointeeUserId: $kannadaReviewer['user_id'],
            appointeeMembershipId: $kannadaReviewer['membership_id'],
            appointedByMembershipId: $metadataAuthor['membership_id'],
            authorityKind: 'kannada_reviewer',
        );
        $rightsAppointment = $this->insertAcceptedAppointment(
            organizationId: $organizationId,
            programId: $programId,
            appointeeUserId: $rightsConfirmer['user_id'],
            appointeeMembershipId: $rightsConfirmer['membership_id'],
            appointedByMembershipId: $metadataAuthor['membership_id'],
            authorityKind: 'rights_confirmer',
        );
        $metadataRevisionId = (string) Str::ulid();
        $metadataVariantId = (string) Str::ulid();
        $englishReviewId = (string) Str::ulid();
        $kannadaReviewId = (string) Str::ulid();
        $rightsRevisionId = (string) Str::ulid();
        $metadataHash = CanonicalJson::sha256([
            'asset_kind' => 'model_audio',
            'locale' => 'en-IN',
            'operator_label' => 'Concurrent governed model audio',
            'text_alternative' => null,
            'transcript' => 'Hello. How are you today?',
        ]);
        $variantHash = CanonicalJson::sha256([
            'asset_kind' => 'model_audio',
            'locale' => 'kn-IN',
            'source_content_hash' => $metadataHash,
            'text_alternative' => null,
            'transcript' => 'ನಮಸ್ಕಾರ. ಇಂದು ನೀವು ಹೇಗಿದ್ದೀರಿ?',
        ]);
        $rightsEvidenceHash = hash(
            'sha256',
            'synthetic-concurrent-publication-rights',
        );
        $now = now();
        DB::table('media_asset_metadata_revisions')->insert([
            'id' => $metadataRevisionId,
            'organization_id' => $organizationId,
            'media_asset_id' => $asset['id'],
            'asset_kind' => 'model_audio',
            'parent_revision_id' => null,
            'revision_number' => 1,
            'operator_label' => 'Concurrent governed model audio',
            'transcript' => 'Hello. How are you today?',
            'text_alternative' => null,
            'content_hash' => $metadataHash,
            'authored_by_membership_id' => $metadataAuthor['membership_id'],
            'authored_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_asset_metadata_heads')->insert([
            'organization_id' => $organizationId,
            'media_asset_id' => $asset['id'],
            'metadata_revision_id' => $metadataRevisionId,
            'version' => 1,
            'updated_by_membership_id' => $metadataAuthor['membership_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_asset_metadata_variants')->insert([
            'id' => $metadataVariantId,
            'organization_id' => $organizationId,
            'media_asset_id' => $asset['id'],
            'metadata_revision_id' => $metadataRevisionId,
            'asset_kind' => 'model_audio',
            'locale' => 'kn-IN',
            'parent_variant_id' => null,
            'revision_number' => 1,
            'source_content_hash' => $metadataHash,
            'transcript' => 'ನಮಸ್ಕಾರ. ಇಂದು ನೀವು ಹೇಗಿದ್ದೀರಿ?',
            'text_alternative' => null,
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $translator['membership_id'],
            'translated_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('media_asset_metadata_variant_heads')->insert([
            'organization_id' => $organizationId,
            'metadata_revision_id' => $metadataRevisionId,
            'metadata_variant_id' => $metadataVariantId,
            'version' => 1,
            'updated_by_membership_id' => $translator['membership_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_asset_metadata_reviews')->insert([
            [
                'id' => $englishReviewId,
                'organization_id' => $organizationId,
                'media_asset_id' => $asset['id'],
                'program_id' => $programId,
                'metadata_revision_id' => $metadataRevisionId,
                'source_content_hash' => $metadataHash,
                'review_kind' => 'academic_en',
                'metadata_variant_id' => null,
                'variant_content_hash' => null,
                'decision' => 'approved',
                'rationale_key' => null,
                'authority_appointment_id' => $englishAppointment['id'],
                'authority_appointment_event_id' => $englishAppointment['event_id'],
                'reviewer_membership_id' => $englishReviewer['membership_id'],
                'reviewed_at' => $now,
                'created_at' => $now,
            ],
            [
                'id' => $kannadaReviewId,
                'organization_id' => $organizationId,
                'media_asset_id' => $asset['id'],
                'program_id' => $programId,
                'metadata_revision_id' => $metadataRevisionId,
                'source_content_hash' => $metadataHash,
                'review_kind' => 'kannada',
                'metadata_variant_id' => $metadataVariantId,
                'variant_content_hash' => $variantHash,
                'decision' => 'approved',
                'rationale_key' => null,
                'authority_appointment_id' => $kannadaAppointment['id'],
                'authority_appointment_event_id' => $kannadaAppointment['event_id'],
                'reviewer_membership_id' => $kannadaReviewer['membership_id'],
                'reviewed_at' => $now,
                'created_at' => $now,
            ],
        ]);
        DB::table('media_rights_revisions')->insert([
            'id' => $rightsRevisionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'media_asset_id' => $asset['id'],
            'parent_revision_id' => null,
            'revision_number' => 1,
            'evidence_status' => 'approved',
            'evidence_hash' => $rightsEvidenceHash,
            'selected_asset_hash' => $asset['hash'],
            'rights_holder_reference' => 'project-owner',
            'provenance_reference' => 'APP-0044',
            'rights_basis_key' => 'owner_attestation',
            'license_or_contract_reference' => 'APP-0044',
            'permitted_release_channels' => CanonicalJson::encode([
                'android',
                'web',
            ]),
            'permitted_territories' => CanonicalJson::encode(['IN']),
            'learner_delivery_allowed' => true,
            'sharing_allowed' => false,
            'derivative_use_allowed' => true,
            'effective_at' => $now->copy()->subMinute(),
            'expires_at' => $now->copy()->addYear(),
            'retention_policy_key' => 'accepted_media',
            'deletion_constraint_key' => 'preserve_while_referenced',
            'confirmed_by_membership_id' => $rightsConfirmer['membership_id'],
            'rights_appointment_id' => $rightsAppointment['id'],
            'rights_appointment_event_id' => $rightsAppointment['event_id'],
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'metadata_revision_id' => $metadataRevisionId,
            'metadata_content_hash' => $metadataHash,
            'english_review_id' => $englishReviewId,
            'metadata_variant_id' => $metadataVariantId,
            'variant_content_hash' => $variantHash,
            'kannada_review_id' => $kannadaReviewId,
            'rights_revision_id' => $rightsRevisionId,
            'rights_evidence_hash' => $rightsEvidenceHash,
            'english_appointment_id' => $englishAppointment['id'],
            'english_appointment_event_id' => $englishAppointment['event_id'],
            'english_reviewer_membership_id' => $englishReviewer['membership_id'],
            'kannada_appointment_id' => $kannadaAppointment['id'],
            'kannada_appointment_event_id' => $kannadaAppointment['event_id'],
            'kannada_reviewer_membership_id' => $kannadaReviewer['membership_id'],
        ];
    }

    /**
     * @param  array{
     *     curriculum_version_id: string,
     *     content_document_id: string,
     *     content_document_key: string,
     *     source_revision_id: string,
     *     source_content_hash: string
     * }  $workspace
     * @param  Actor  $contentAuthor
     * @param  Actor  $translator
     * @param  array<string, string>  $governance
     */
    private function completePublishableWorkspace(
        string $organizationId,
        string $programId,
        array $workspace,
        array $contentAuthor,
        array $translator,
        array $governance,
    ): void {
        $variantId = (string) Str::ulid();
        $variantPayload = [
            'schema' => 'global-english.daily-speaking-mission.v1',
            'guidance' => 'ನೆರೆಯವರಿಗೆ ವಿನಯವಾಗಿ ಶುಭಾಶಯ ಹೇಳಿ.',
        ];
        $variantHash = CanonicalJson::sha256($variantPayload);
        $now = now();
        DB::table('localized_variants')->insert([
            'id' => $variantId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $workspace['curriculum_version_id'],
            'source_revision_id' => $workspace['source_revision_id'],
            'parent_variant_id' => null,
            'locale' => 'kn-IN',
            'revision_number' => 1,
            'source_content_hash' => $workspace['source_content_hash'],
            'payload' => CanonicalJson::encode($variantPayload),
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $translator['membership_id'],
            'translator_attribution' => 'synthetic-media-concurrency',
            'translated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_reviews')->insert([
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'curriculum_version_id' => $workspace['curriculum_version_id'],
                'review_kind' => 'academic_en',
                'source_revision_id' => $workspace['source_revision_id'],
                'localized_variant_id' => null,
                'authority_appointment_id' => $governance[
                    'english_appointment_id'
                ],
                'authority_appointment_event_id' => $governance[
                    'english_appointment_event_id'
                ],
                'reviewer_membership_id' => $governance[
                    'english_reviewer_membership_id'
                ],
                'decision' => 'approved',
                'source_content_hash' => $workspace['source_content_hash'],
                'localized_content_hash' => null,
                'rationale_key' => null,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'curriculum_version_id' => $workspace['curriculum_version_id'],
                'review_kind' => 'kannada',
                'source_revision_id' => $workspace['source_revision_id'],
                'localized_variant_id' => $variantId,
                'authority_appointment_id' => $governance[
                    'kannada_appointment_id'
                ],
                'authority_appointment_event_id' => $governance[
                    'kannada_appointment_event_id'
                ],
                'reviewer_membership_id' => $governance[
                    'kannada_reviewer_membership_id'
                ],
                'decision' => 'approved',
                'source_content_hash' => $workspace['source_content_hash'],
                'localized_content_hash' => $variantHash,
                'rationale_key' => null,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        $draftGraph = [
            'schema' => ContentDraftGraphService::DRAFT_SCHEMA,
            'levels' => [['key' => 'a1', 'sequence' => 1]],
            'objectives' => [[
                'key' => 'objective-media-concurrency',
                'outcome_code' => 'CAN_COMPLETE_SYNTHETIC_MISSION',
                'sequence' => 1,
                'prerequisite_keys' => [],
            ]],
            'modules' => [[
                'key' => 'module-media-concurrency',
                'level_key' => 'a1',
                'sequence' => 1,
                'estimated_minutes' => 5,
            ]],
            'lessons' => [[
                'key' => 'lesson-media-concurrency',
                'module_key' => 'module-media-concurrency',
                'sequence' => 1,
                'objective_keys' => ['objective-media-concurrency'],
            ]],
            'items' => [[
                'key' => 'item-media-concurrency',
                'lesson_key' => 'lesson-media-concurrency',
                'content_document_key' => $workspace['content_document_key'],
                'item_type' => 'speaking_prompt',
                'sequence' => 1,
            ]],
            'delivery_bundles' => [[
                'key' => 'mission-media-concurrency',
                'instruction_document_key' => $workspace['content_document_key'],
                'sequence' => 1,
                'item_keys' => ['item-media-concurrency'],
            ]],
        ];
        $materialized = $this->app
            ->make(ContentDraftGraphService::class)
            ->replace(
                $organizationId,
                $workspace['curriculum_version_id'],
                $draftGraph,
                $now,
            );

        if (! $materialized['is_complete']) {
            throw new RuntimeException('The publication race graph is incomplete.');
        }

        $projected = $this->app->make(CurriculumGraphProjector::class)->project(
            $organizationId,
            $workspace['curriculum_version_id'],
        );
        $graphRevisionId = (string) Str::ulid();
        $draftRevisionId = (string) Str::ulid();
        DB::table('curriculum_graph_revisions')->insert([
            'id' => $graphRevisionId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $workspace['curriculum_version_id'],
            'parent_revision_id' => null,
            'parent_revision_number' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($projected['graph']),
            'graph_hash' => $projected['graph_hash'],
            'authored_by_membership_id' => $contentAuthor['membership_id'],
            'author_attribution' => 'synthetic-media-concurrency',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_heads')->insert([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $workspace['curriculum_version_id'],
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $projected['graph_hash'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_reviews')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $workspace['curriculum_version_id'],
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $projected['graph_hash'],
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $governance[
                'english_appointment_id'
            ],
            'authority_appointment_event_id' => $governance[
                'english_appointment_event_id'
            ],
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $governance[
                'english_reviewer_membership_id'
            ],
            'decision' => 'approved',
            'rationale_key' => null,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_draft_graph_revisions')->insert([
            'id' => $draftRevisionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $workspace['curriculum_version_id'],
            'parent_revision_id' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($materialized['graph']),
            'graph_hash' => $materialized['graph_hash'],
            'blocker_codes' => CanonicalJson::encode([]),
            'is_complete' => true,
            'authored_by_membership_id' => $contentAuthor['membership_id'],
            'authored_at' => $now,
            'created_at' => $now,
        ]);
        $updated = DB::table('curriculum_workspaces')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('curriculum_version_id', $workspace['curriculum_version_id'])
            ->where('state', 'draft')
            ->where('version', 2)
            ->update([
                'state' => 'in_review',
                'version' => 3,
                'current_draft_revision_id' => $draftRevisionId,
                'submitted_graph_revision_id' => $graphRevisionId,
                'updated_at' => $now,
            ]);

        if ($updated !== 1) {
            throw new RuntimeException(
                'The publication race workspace could not enter review.',
            );
        }
    }

    /**
     * @param  Actor  $actor
     * @param  array<string, mixed>  $payload
     * @return RaceCommand
     */
    private function raceCommand(
        string $operation,
        array $actor,
        array $payload,
    ): array {
        return [
            'operation' => $operation,
            'actor_user_id' => $actor['user_id'],
            'actor_membership_id' => $actor['membership_id'],
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  list<RaceCommand>  $commands
     * @return array{both_blocked: bool, results: list<RaceResult>}
     */
    private function runRace(
        array $fixture,
        array $commands,
        Closure $lock,
    ): array {
        $databaseConfig = DB::connection()->getConfig();
        DB::disconnect();

        /** @var list<Worker> $workers */
        $workers = [];
        $lockConnection = null;
        $backendPids = [];
        $results = [];
        $bothBlocked = false;

        try {
            foreach ($commands as $command) {
                $workers[] = $this->spawnWorker($fixture, $command);
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 10);
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException(
                        'A media concurrency worker did not become ready.',
                    );
                }

                $ready = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $backendPid = is_array($ready) ? ($ready['backend_pid'] ?? null) : null;

                if (! is_int($backendPid)) {
                    throw new RuntimeException(
                        'A media concurrency worker returned no backend PID.',
                    );
                }

                $backendPids[] = $backendPid;
            }

            $lockConnection = $this->openPostgreSqlConnection($databaseConfig);
            $lockConnection->beginTransaction();
            $lock($lockConnection);

            foreach ($workers as $worker) {
                fwrite($worker['pipe'], "go\n");
                fflush($worker['pipe']);
            }

            $bothBlocked = $this->waitUntilWorkersBlock(
                $lockConnection,
                $backendPids,
            );
            $lockConnection->commit();

            foreach ($workers as $index => $worker) {
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException(
                        'A media concurrency worker returned no result.',
                    );
                }

                $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

                if (! is_array($result) || ! is_string($result['outcome'] ?? null)) {
                    throw new RuntimeException(
                        'A media concurrency worker returned an invalid result.',
                    );
                }

                /** @var RaceResult $result */
                $results[] = $result;
                $waited = pcntl_waitpid($worker['pid'], $status);

                if (
                    $waited !== $worker['pid']
                    || pcntl_wexitstatus($status) !== 0
                ) {
                    throw new RuntimeException(
                        'A media concurrency worker failed.',
                    );
                }

                $workers[$index]['reaped'] = true;
            }
        } finally {
            if (
                $lockConnection instanceof PDO
                && $lockConnection->inTransaction()
            ) {
                $lockConnection->rollBack();
            }

            foreach ($workers as $index => $worker) {
                if (is_resource($worker['pipe'])) {
                    fclose($worker['pipe']);
                }

                if ($worker['reaped']) {
                    continue;
                }

                $waited = pcntl_waitpid($worker['pid'], $status, WNOHANG);

                if ($waited === 0 && function_exists('posix_kill')) {
                    posix_kill($worker['pid'], SIGKILL);
                    pcntl_waitpid($worker['pid'], $status);
                }

                $workers[$index]['reaped'] = true;
            }

            DB::purge();
            DB::reconnect();
        }

        return ['both_blocked' => $bothBlocked, 'results' => $results];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  RaceCommand  $command
     * @return Worker
     */
    private function spawnWorker(array $fixture, array $command): array
    {
        $sockets = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );

        if ($sockets === false) {
            throw new RuntimeException('The media concurrency pipe failed.');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);
            throw new RuntimeException('The media concurrency fork failed.');
        }

        if ($pid === 0) {
            fclose($sockets[0]);
            $this->runWorker($sockets[1], $fixture, $command);
        }

        fclose($sockets[1]);

        return ['pid' => $pid, 'pipe' => $sockets[0], 'reaped' => false];
    }

    /**
     * @param  resource  $pipe
     * @param  array<string, mixed>  $fixture
     * @param  RaceCommand  $command
     */
    private function runWorker(
        mixed $pipe,
        array $fixture,
        array $command,
    ): never {
        try {
            DB::purge();
            DB::reconnect();
            $backend = DB::selectOne('select pg_backend_pid() as pid');
            $backendPid = is_object($backend) ? ($backend->pid ?? null) : null;

            if (! is_int($backendPid)) {
                throw new RuntimeException('The media worker backend PID is missing.');
            }

            $this->writeWorkerResult($pipe, ['backend_pid' => $backendPid], false);

            if (fgets($pipe) !== "go\n") {
                throw new RuntimeException('The media worker release signal is invalid.');
            }

            $actor = [
                'user_id' => $command['actor_user_id'],
                'membership_id' => $command['actor_membership_id'],
                'role_id' => '',
            ];
            $result = $this->runAs(
                $this->arrayString($fixture, 'organization_id'),
                $actor,
                fn (User $user) => $this->executeCommand($user, $command),
            );
            $payload = [
                'outcome' => $this->successOutcome($command['operation']),
                'result_id' => $this->commandResultId($result),
            ];
        } catch (
            CurriculumHeadConflict
            |CurriculumIdempotencyConflict
            |CurriculumOperationRejected
            |MediaLibraryConflict
            |MediaLibraryRejected
        ) {
            $payload = ['outcome' => 'conflict'];
        } catch (Throwable $exception) {
            $this->writeWorkerResult($pipe, [
                'outcome' => 'error',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            exit(1);
        }

        $this->writeWorkerResult($pipe, $payload);
        exit(0);
    }

    /** @param RaceCommand $command */
    private function executeCommand(User $actor, array $command): mixed
    {
        $payload = $command['payload'];

        return match ($command['operation']) {
            'finalize' => $this->app->make(MediaUploadCommands::class)->finalize(
                actor: $actor,
                uploadId: $this->arrayString($payload, 'upload_id'),
                expectedUploadVersion: $this->arrayInt(
                    $payload,
                    'expected_upload_version',
                ),
                idempotencyKey: $this->arrayString($payload, 'idempotency_key'),
            ),
            'accept' => $this->app->make(MediaUploadCommands::class)->accept(
                actor: $actor,
                uploadId: $this->arrayString($payload, 'upload_id'),
                expectedUploadVersion: $this->arrayInt(
                    $payload,
                    'expected_upload_version',
                ),
                idempotencyKey: $this->arrayString($payload, 'idempotency_key'),
            ),
            'append_english' => $this->app
                ->make(MediaMetadataCommands::class)
                ->appendEnglish(
                    actor: $actor,
                    assetId: $this->arrayString($payload, 'media_asset_id'),
                    expectedHeadRevisionId: null,
                    expectedMetadataVersion: 0,
                    operatorLabel: $this->arrayString($payload, 'operator_label'),
                    transcript: $this->arrayString($payload, 'transcript'),
                    textAlternative: null,
                    idempotencyKey: $this->arrayString(
                        $payload,
                        'idempotency_key',
                    ),
                ),
            'append_kannada' => $this->app
                ->make(MediaMetadataCommands::class)
                ->appendKannada(
                    actor: $actor,
                    metadataRevisionId: $this->arrayString(
                        $payload,
                        'metadata_revision_id',
                    ),
                    sourceContentHash: $this->arrayString(
                        $payload,
                        'source_content_hash',
                    ),
                    expectedHeadVariantId: null,
                    expectedVariantVersion: 0,
                    transcript: $this->arrayString($payload, 'transcript'),
                    textAlternative: null,
                    idempotencyKey: $this->arrayString(
                        $payload,
                        'idempotency_key',
                    ),
                ),
            'review_english' => $this->app
                ->make(MediaMetadataCommands::class)
                ->review(
                    actor: $actor,
                    programId: $this->arrayString($payload, 'program_id'),
                    metadataRevisionId: $this->arrayString(
                        $payload,
                        'metadata_revision_id',
                    ),
                    sourceContentHash: $this->arrayString(
                        $payload,
                        'source_content_hash',
                    ),
                    reviewKind: 'academic_en',
                    metadataVariantId: null,
                    variantContentHash: null,
                    decision: 'approved',
                    rationaleKey: null,
                    authorityAppointmentId: $this->arrayString(
                        $payload,
                        'authority_appointment_id',
                    ),
                    idempotencyKey: $this->arrayString(
                        $payload,
                        'idempotency_key',
                    ),
                ),
            'confirm_rights' => $this->confirmRights(
                $actor,
                $this->arrayString($payload, 'program_id'),
                $this->arrayString($payload, 'media_asset_id'),
                $this->arrayString($payload, 'selected_asset_hash'),
                $this->arrayString($payload, 'parent_revision_id'),
                $this->arrayString($payload, 'authority_appointment_id'),
                $this->arrayString($payload, 'suffix'),
            ),
            'replace' => $this->app->make(MediaLifecycleCommands::class)->replace(
                actor: $actor,
                predecessorAssetId: $this->arrayString(
                    $payload,
                    'predecessor_media_asset_id',
                ),
                successorAssetId: $this->arrayString(
                    $payload,
                    'successor_media_asset_id',
                ),
                expectedPredecessorLifecycleVersion: 1,
                expectedSuccessorLifecycleVersion: 1,
                idempotencyKey: $this->arrayString($payload, 'idempotency_key'),
            ),
            'attach' => $this->app->make(MediaLifecycleCommands::class)->attach(
                actor: $actor,
                assetId: $this->arrayString($payload, 'media_asset_id'),
                programId: $this->arrayString($payload, 'program_id'),
                curriculumVersionId: $this->arrayString(
                    $payload,
                    'curriculum_version_id',
                ),
                sourceRevisionId: $this->arrayString(
                    $payload,
                    'source_revision_id',
                ),
                usageKey: 'model_audio',
                expectedWorkspaceVersion: 1,
                expectedLifecycleVersion: 1,
                metadataRevisionId: $this->arrayString(
                    $payload,
                    'metadata_revision_id',
                ),
                metadataContentHash: $this->arrayString(
                    $payload,
                    'metadata_content_hash',
                ),
                englishReviewId: $this->arrayString(
                    $payload,
                    'english_review_id',
                ),
                metadataVariantId: $this->arrayString(
                    $payload,
                    'metadata_variant_id',
                ),
                variantContentHash: $this->arrayString(
                    $payload,
                    'variant_content_hash',
                ),
                kannadaReviewId: $this->arrayString(
                    $payload,
                    'kannada_review_id',
                ),
                mediaRightsRevisionId: $this->arrayString(
                    $payload,
                    'media_rights_revision_id',
                ),
                idempotencyKey: $this->arrayString($payload, 'idempotency_key'),
            ),
            'retire' => $this->app->make(MediaLifecycleCommands::class)->retire(
                actor: $actor,
                assetId: $this->arrayString($payload, 'media_asset_id'),
                expectedLifecycleVersion: 1,
                reasonKey: 'concurrent_publication_retirement',
                idempotencyKey: $this->arrayString($payload, 'idempotency_key'),
            ),
            'publish' => $this->app->make(CurriculumPublisher::class)->publish(
                actor: $actor,
                curriculumVersionId: $this->arrayString(
                    $payload,
                    'curriculum_version_id',
                ),
                expectedHeadVersion: 0,
                idempotencyKey: $this->arrayString($payload, 'idempotency_key'),
            ),
            default => throw new RuntimeException(
                'The media concurrency operation is unsupported.',
            ),
        };
    }

    private function successOutcome(string $operation): string
    {
        return match ($operation) {
            'finalize' => 'finalized',
            'accept' => 'accepted',
            'append_english' => 'metadata_appended',
            'append_kannada' => 'variant_appended',
            'review_english' => 'reviewed',
            'confirm_rights' => 'rights_confirmed',
            'replace' => 'replaced',
            'attach' => 'attached',
            'retire' => 'retired',
            'publish' => 'published',
            default => throw new RuntimeException(
                'The media concurrency outcome is unsupported.',
            ),
        };
    }

    private function commandResultId(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        if ($result instanceof MediaLibraryCommandResult) {
            return $result->resultId;
        }

        if ($result instanceof PublicationResult) {
            return $result->publicationId;
        }

        throw new RuntimeException('The media concurrency result is invalid.');
    }

    private function confirmRights(
        User $actor,
        string $programId,
        string $assetId,
        string $assetHash,
        ?string $parentRevisionId,
        string $appointmentId,
        string $suffix,
    ): string {
        return $this->app->make(MediaRightsConfirmationCommands::class)->confirm(
            actor: $actor,
            programId: $programId,
            mediaAssetId: $assetId,
            selectedAssetHash: $assetHash,
            parentRevisionId: $parentRevisionId,
            evidenceHash: hash('sha256', 'rights-evidence-'.$suffix),
            rightsHolderReference: 'project-owner',
            provenanceReference: 'APP-0044',
            rightsBasisKey: 'owner_attestation',
            licenseOrContractReference: 'APP-0044-'.$suffix,
            permittedReleaseChannels: ['android', 'web'],
            permittedTerritories: ['IN'],
            learnerDeliveryAllowed: true,
            sharingAllowed: false,
            derivativeUseAllowed: true,
            effectiveAt: CarbonImmutable::now()->subMinute(),
            expiresAt: CarbonImmutable::now()->addYear(),
            retentionPolicyKey: 'accepted_media',
            deletionConstraintKey: 'preserve_while_referenced',
            authorityAppointmentId: $appointmentId,
            idempotencyKey: 'media-rights-'.$suffix,
        );
    }

    /**
     * @param  Actor  $actor
     */
    private function runAs(
        string $organizationId,
        array $actor,
        Closure $command,
    ): mixed {
        $user = User::query()->findOrFail($actor['user_id']);
        $context = new OrganizationContext(
            organizationId: $organizationId,
            actorUserId: $actor['user_id'],
            membershipId: $actor['membership_id'],
            source: OrganizationContextSource::Membership,
        );

        return $this->app->make(OrganizationContextManager::class)->run(
            $context,
            fn () => $command($user),
        );
    }

    private function createOrganization(string $name): Organization
    {
        return Organization::query()->create([
            'system_key' => null,
            'name' => $name,
            'kind' => Organization::KIND_INSTITUTION,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
        ]);
    }

    /**
     * @param  list<string>  $permissionKeys
     * @return Actor
     */
    private function createActor(
        Organization $organization,
        array $permissionKeys,
        string $roleKey,
    ): array {
        $user = User::query()->forceCreate([
            'display_name' => null,
            'status' => User::STATUS_ACTIVE,
        ]);
        $membershipId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        $now = now();
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'membership_type' => Membership::TYPE_ORGANIZATION_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'ended_at' => null,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $organization->getKey(),
            'key' => $roleKey,
            'name' => Str::headline($roleKey),
            'is_system' => false,
            'status' => 'active',
            'version' => 1,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membershipId,
            'role_id' => $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($permissionKeys as $permissionKey) {
            $permissionId = Permission::query()
                ->where('key', $permissionKey)
                ->value('id');

            if (! is_string($permissionId)) {
                throw new RuntimeException(
                    sprintf('Required permission %s is not seeded.', $permissionKey),
                );
            }

            DB::table('role_permissions')->insert([
                'organization_id' => $organization->getKey(),
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [
            'user_id' => (string) $user->getKey(),
            'membership_id' => $membershipId,
            'role_id' => $roleId,
        ];
    }

    private function createProgram(
        Organization $organization,
        string $membershipId,
        string $suffix,
    ): string {
        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organization->getKey(),
            'key' => $suffix.'-'.strtolower((string) Str::ulid()),
            'internal_name' => 'Synthetic media concurrency program',
            'created_by_membership_id' => $membershipId,
            'created_by_attribution' => 'synthetic-media-concurrency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $programId;
    }

    /** @return array{id: string, hash: string, scan_id: string} */
    private function createManagedAsset(
        string $organizationId,
        string $membershipId,
        string $bytes,
    ): array {
        $assetId = (string) Str::ulid();
        $scanId = (string) Str::ulid();
        $hash = hash('sha256', $bytes);
        $now = now();
        $technicalHash = CanonicalJson::sha256([
            'asset_kind' => 'model_audio',
            'byte_size' => strlen($bytes),
            'detected_mime_type' => 'audio/wav',
            'duration_milliseconds' => 1500,
            'inspection_outcome' => 'passed',
            'inspector_name' => 'synthetic-concurrency-inspector',
            'inspector_policy_version' => 'test-v1',
            'malware_outcome' => 'clean',
            'object_hash' => $hash,
            'scanner_engine_version' => 'test-v1',
            'scanner_name' => 'synthetic-concurrency-scanner',
            'scanner_policy_version' => 'test-v1',
        ]);
        DB::table('media_assets')->insert([
            'id' => $assetId,
            'organization_id' => $organizationId,
            'key' => 'managed-'.strtolower($assetId),
            'asset_kind' => 'model_audio',
            'storage_object_key' => 'accepted/'.strtolower($assetId).'/'.$hash,
            'mime_type' => 'audio/wav',
            'byte_size' => strlen($bytes),
            'asset_hash' => $hash,
            'uploaded_by_membership_id' => $membershipId,
            'uploader_attribution' => 'synthetic-media-concurrency',
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('media_security_scans')->insert([
            'id' => $scanId,
            'organization_id' => $organizationId,
            'media_upload_id' => null,
            'media_asset_id' => $assetId,
            'asset_kind' => 'model_audio',
            'object_hash' => $hash,
            'byte_size' => strlen($bytes),
            'inspection_outcome' => 'passed',
            'malware_outcome' => 'clean',
            'detected_mime_type' => 'audio/wav',
            'duration_milliseconds' => 1500,
            'inspector_name' => 'synthetic-concurrency-inspector',
            'inspector_policy_version' => 'test-v1',
            'scanner_name' => 'synthetic-concurrency-scanner',
            'scanner_engine_version' => 'test-v1',
            'scanner_policy_version' => 'test-v1',
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
            'managed_by_membership_id' => $membershipId,
            'retired_by_membership_id' => null,
            'retirement_reason_key' => null,
            'managed_at' => $now,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $assetId, 'hash' => $hash, 'scan_id' => $scanId];
    }

    /** @return array{id: string, event_id: string} */
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
            'valid_from' => $now->copy()->subDay(),
            'valid_until' => $now->copy()->addMonth(),
            'qualification_basis' => 'Synthetic media concurrency evidence',
            'approval_evidence_key' => 'APP-TEST-MEDIA-CONCURRENCY',
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
                'effective_at' => $now->copy()->subMinute(),
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
                'effective_at' => $now->copy()->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return ['id' => $appointmentId, 'event_id' => $acceptedEventId];
    }

    /**
     * @param  array<string, scalar>  $bindings
     */
    private function lockRow(
        PDO $connection,
        string $sql,
        array $bindings,
    ): void {
        preg_match_all('/(?<!:):([a-z][a-z0-9_]*)/', $sql, $matches);
        $parameters = array_fill_keys($matches[1], true);
        $statement = $connection->prepare($sql);
        $statement->execute(array_intersect_key($bindings, $parameters));

        if ($statement->fetchColumn() === false) {
            throw new RuntimeException('The forced media race row is missing.');
        }
    }

    /** @param list<int> $backendPids */
    private function waitUntilWorkersBlock(
        PDO $connection,
        array $backendPids,
    ): bool {
        if (count($backendPids) !== 2) {
            throw new RuntimeException('Exactly two backend PIDs are required.');
        }

        $statement = $connection->prepare(
            <<<'SQL'
                select count(*)
                from pg_stat_activity
                where pid in (:first_pid, :second_pid)
                  and wait_event_type = 'Lock'
                SQL,
        );

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $connection->exec('select pg_stat_clear_snapshot()');
            $statement->execute([
                'first_pid' => $backendPids[0],
                'second_pid' => $backendPids[1],
            ]);

            if ((int) $statement->fetchColumn() === 2) {
                return true;
            }

            usleep(10_000);
        }

        return false;
    }

    /**
     * @param  array{both_blocked: bool, results: list<RaceResult>}  $race
     * @param  list<string>  $expectedOutcomes
     */
    private function assertRace(array $race, array $expectedOutcomes): void
    {
        self::assertTrue(
            $race['both_blocked'],
            'Both media workers must enter the PostgreSQL lock queue.',
        );
        self::assertSame(
            $expectedOutcomes,
            collect($race['results'])
                ->pluck('outcome')
                ->sort()
                ->values()
                ->all(),
        );
    }

    /**
     * @param  resource  $pipe
     * @param  array<string, mixed>  $payload
     */
    private function writeWorkerResult(
        mixed $pipe,
        array $payload,
        bool $close = true,
    ): void {
        if (! is_resource($pipe)) {
            return;
        }

        fwrite($pipe, json_encode($payload, JSON_THROW_ON_ERROR)."\n");
        fflush($pipe);

        if ($close) {
            fclose($pipe);
        }
    }

    /** @param array<string, mixed> $config */
    private function openPostgreSqlConnection(array $config): PDO
    {
        return new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                (string) ($config['host'] ?? '127.0.0.1'),
                (string) ($config['port'] ?? '5432'),
                (string) ($config['database'] ?? ''),
                (string) ($config['sslmode'] ?? 'prefer'),
            ),
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
    }

    /** @param array<string, mixed> $value */
    private function arrayString(array $value, string $key): string
    {
        $field = $value[$key] ?? null;

        if (! is_string($field) || $field === '') {
            throw new RuntimeException("The {$key} fixture value is invalid.");
        }

        return $field;
    }

    /** @param array<string, mixed> $value */
    private function arrayInt(array $value, string $key): int
    {
        $field = $value[$key] ?? null;

        if (! is_int($field)) {
            throw new RuntimeException("The {$key} fixture value is invalid.");
        }

        return $field;
    }
}
