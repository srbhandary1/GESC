<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Media\Exceptions\MediaObjectStoreFailure;
use App\Media\MediaMalwareScanner;
use App\Media\MediaObjectDigest;
use App\Media\MediaObjectStore;
use App\Media\Scanning\UnavailableMediaMalwareScanner;
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

final class AdminMediaLibraryApiTest extends TenancyIntegrationTestCase
{
    private const string OTP = '830417';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new AdminMediaLibraryOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new AdminMediaLibraryNullOtpDeliveryAdapter,
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

    public function test_audio_upload_is_strict_replay_safe_private_and_tenant_scoped(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T03:00:00+00:00');
        $actor = $this->signInWeb('+919310000001');
        $organization = $this->createOrganization('Media Library Primary');
        [$membership, $context] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            [
                'media.library.read',
                'media.upload',
                'media.quarantine.review',
                'media.metadata.manage',
                'media.replace',
                'media.retire',
            ],
            'media-operator',
        );
        $this->selectMembership($membership);
        $bytes = 'RIFF'.pack('V', 16).'WAVEfmt synthetic-audio';
        $hash = hash('sha256', $bytes);
        $startBody = [
            'asset_kind' => 'model_audio',
            'safe_label' => 'Welcome model audio',
            'total_bytes' => strlen($bytes),
            'whole_object_hash' => $hash,
        ];

        $startKey = (string) Str::ulid();
        $started = $this->postCommand(
            '/api/v1/operator/media/uploads',
            $startBody,
            $startKey,
        )
            ->assertCreated()
            ->assertJsonPath('result_type', 'media_upload')
            ->assertJsonPath('state', 'uploading')
            ->assertJsonPath('version', 1)
            ->assertJsonPath('replayed', false);
        $uploadId = $started->json('id');
        self::assertIsString($uploadId);

        $this->postCommand(
            '/api/v1/operator/media/uploads',
            $startBody,
            $startKey,
        )
            ->assertOk()
            ->assertJsonPath('id', $uploadId)
            ->assertJsonPath('replayed', true);
        $this->postCommand(
            '/api/v1/operator/media/uploads',
            [...$startBody, 'organization_id' => $organization->getKey()],
            (string) Str::ulid(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('message_key', 'problem.validation_failed');

        $partKey = (string) Str::ulid();
        $this->postPart($uploadId, $bytes, $hash, 1, $partKey)
            ->assertCreated()
            ->assertJsonPath('result_type', 'media_upload_part')
            ->assertJsonPath('part_number', 1)
            ->assertJsonPath('upload_version', 2);
        $this->postPart($uploadId, $bytes, $hash, 1, $partKey)
            ->assertOk()
            ->assertJsonPath('replayed', true);

        $finalized = $this->postCommand(
            "/api/v1/operator/media/uploads/{$uploadId}/finalizations",
            ['expected_upload_version' => 2],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('inspection_outcome', 'passed')
            ->assertJsonPath('malware_outcome', 'clean')
            ->assertJsonPath('upload_state', 'scan_clean')
            ->assertJsonPath('upload_version', 4);
        self::assertIsString($finalized->json('id'));

        $quarantinePreview = $this->get(
            "/api/v1/operator/media/uploads/{$uploadId}/preview",
        )->assertOk();
        self::assertSame($bytes, $quarantinePreview->streamedContent());
        $cacheControl = $quarantinePreview->headers->get('Cache-Control');
        self::assertIsString($cacheControl);
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertNull($quarantinePreview->headers->get('X-Storage-Object-Key'));

        $accepted = $this->postCommand(
            "/api/v1/operator/media/uploads/{$uploadId}/acceptances",
            ['expected_upload_version' => 4],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('state', 'accepted')
            ->assertJsonPath('lifecycle_version', 1);
        $assetId = $accepted->json('media_asset_id');
        self::assertIsString($assetId);

        $this->getJson('/api/v1/operator/media/manifest')
            ->assertOk()
            ->assertJsonPath('organization_id', $organization->getKey())
            ->assertJsonPath('supported_kinds.0', 'model_audio')
            ->assertJsonPath('rights_attestation.approval_reference', 'APP-0044')
            ->assertJsonPath('rights_attestation.creates_asset_approval', false);
        $this->getJson('/api/v1/operator/media/assets')
            ->assertOk()
            ->assertJsonPath('data.0.media_asset_id', $assetId)
            ->assertJsonPath('data.0.state', 'active')
            ->assertJsonMissing(['storage_object_key']);
        $this->getJson("/api/v1/operator/media/assets/{$assetId}")
            ->assertOk()
            ->assertJsonPath('data.media_asset_id', $assetId)
            ->assertJsonPath('data.asset_hash', $hash)
            ->assertJsonMissing(['storage_object_key']);
        $assetPreview = $this->get(
            "/api/v1/operator/media/assets/{$assetId}/preview",
        )->assertOk();
        self::assertSame($bytes, $assetPreview->streamedContent());
        self::assertSame($hash, $assetPreview->headers->get('X-Content-SHA256'));

        $foreign = $this->createOrganization('Media Library Foreign');
        [$foreignMembership, $foreignContext] = $this->createMembership(
            $foreign,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $foreignMembership,
            $foreignContext,
            ['media.library.read'],
            'foreign-media-reader',
        );
        $this->selectMembership($foreignMembership);
        $this->getJson("/api/v1/operator/media/assets/{$assetId}")
            ->assertNotFound()
            ->assertJsonMissing([$hash, 'Welcome model audio']);
        $this->getJson('/api/v1/operator/media/assets?unexpected=1')
            ->assertUnprocessable();
    }

    public function test_infected_candidate_never_becomes_previewable_or_accepted(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T04:00:00+00:00');
        $actor = $this->signInWeb('+919310000002');
        $organization = $this->createOrganization('Media Scan Tenant');
        [$membership, $context] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['media.library.read', 'media.upload', 'media.quarantine.review'],
            'media-scan-reviewer',
        );
        $this->selectMembership($membership);
        $this->freshenCurrentWebSession($actor);
        $bytes = 'RIFF'.pack('V', 64).'WAVEfmt EICAR-STANDARD-ANTIVIRUS-TEST-FILE';
        $hash = hash('sha256', $bytes);
        $started = $this->postCommand(
            '/api/v1/operator/media/uploads',
            [
                'asset_kind' => 'model_audio',
                'safe_label' => 'Rejected candidate',
                'total_bytes' => strlen($bytes),
                'whole_object_hash' => $hash,
            ],
            (string) Str::ulid(),
        )->assertCreated();
        $uploadId = $started->json('id');
        self::assertIsString($uploadId);
        $this->postPart(
            $uploadId,
            $bytes,
            $hash,
            1,
            (string) Str::ulid(),
        )->assertCreated();
        $this->postCommand(
            "/api/v1/operator/media/uploads/{$uploadId}/finalizations",
            ['expected_upload_version' => 2],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('upload_state', 'scan_failed')
            ->assertJsonPath('malware_outcome', 'infected');

        $this->get("/api/v1/operator/media/uploads/{$uploadId}/preview")
            ->assertNotFound();
        $this->postCommand(
            "/api/v1/operator/media/uploads/{$uploadId}/acceptances",
            ['expected_upload_version' => 4],
            (string) Str::ulid(),
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.media_library.stale_upload',
            );
        self::assertSame(0, DB::table('media_assets')
            ->where('organization_id', $organization->getKey())
            ->count());
    }

    public function test_lesson_audio_has_a_separate_limit_private_range_preview_and_filter(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T03:30:00+00:00');
        $this->prepareMediaOperator(
            '+919310000006',
            'Long Form Lesson Audio Tenant',
        );

        $this->postCommand(
            '/api/v1/operator/media/uploads',
            [
                'asset_kind' => 'model_audio',
                'safe_label' => 'Too large for a model line',
                'total_bytes' => 10_000_001,
                'whole_object_hash' => str_repeat('a', 64),
            ],
            (string) Str::ulid(),
        )->assertNotFound();

        $this->postCommand(
            '/api/v1/operator/media/uploads',
            [
                'asset_kind' => 'lesson_audio',
                'safe_label' => 'Bounded long form candidate',
                'total_bytes' => 10_000_001,
                'whole_object_hash' => str_repeat('b', 64),
            ],
            (string) Str::ulid(),
        )->assertCreated();

        $bytes = 'ID3-long-form-level-one-teaching-audio';
        $uploadId = $this->startUploadAndPart(
            'lesson_audio',
            'Level 1 Introduction',
            $bytes,
        );
        $this->postCommand(
            "/api/v1/operator/media/uploads/{$uploadId}/finalizations",
            ['expected_upload_version' => 2],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('inspection_outcome', 'passed')
            ->assertJsonPath('upload_state', 'scan_clean');
        $accepted = $this->postCommand(
            "/api/v1/operator/media/uploads/{$uploadId}/acceptances",
            ['expected_upload_version' => 4],
            (string) Str::ulid(),
        )->assertCreated();
        $assetId = $accepted->json('media_asset_id');
        self::assertIsString($assetId);

        $this->getJson('/api/v1/operator/media/manifest')
            ->assertOk()
            ->assertJsonPath('limits.model_audio_maximum_bytes', 10_000_000)
            ->assertJsonPath('limits.lesson_audio_maximum_bytes', 20_000_000)
            ->assertJsonPath('supported_kinds.1', 'lesson_audio');
        $this->getJson('/api/v1/operator/media/assets?kind=lesson_audio')
            ->assertOk()
            ->assertJsonPath('data.0.media_asset_id', $assetId)
            ->assertJsonPath('data.0.asset_kind', 'lesson_audio');

        $preview = $this->withHeader('Range', 'bytes=4-12')
            ->get("/api/v1/operator/media/assets/{$assetId}/preview")
            ->assertStatus(206);
        self::assertSame(substr($bytes, 4, 9), $preview->streamedContent());
        self::assertSame('bytes', $preview->headers->get('Accept-Ranges'));
        self::assertSame(
            'bytes 4-12/'.strlen($bytes),
            $preview->headers->get('Content-Range'),
        );
        self::assertNull($preview->headers->get('X-Storage-Object-Key'));
    }

    public function test_size_container_and_unavailable_scanner_fail_closed(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T05:00:00+00:00');
        $organization = $this->prepareMediaOperator(
            '+919310000003',
            'Media Validation Tenant',
        );

        $oversized = $this->postCommand(
            '/api/v1/operator/media/uploads',
            [
                'asset_kind' => 'model_audio',
                'safe_label' => 'Oversized candidate',
                'total_bytes' => 10_000_001,
                'whole_object_hash' => str_repeat('a', 64),
            ],
            (string) Str::ulid(),
        )
            ->assertNotFound()
            ->assertJsonPath(
                'message_key',
                'problem.media_library.not_found',
            );
        self::assertStringNotContainsString(
            '10000000',
            (string) $oversized->getContent(),
        );

        $wrongContainerBytes = 'RIFF'.pack('V', 16).'WAVEfmt synthetic-audio';
        $wrongContainerUpload = $this->startUploadAndPart(
            'lesson_video',
            'Audio disguised as video',
            $wrongContainerBytes,
        );
        $this->postCommand(
            "/api/v1/operator/media/uploads/{$wrongContainerUpload}/finalizations",
            ['expected_upload_version' => 2],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('inspection_outcome', 'failed')
            ->assertJsonPath('malware_outcome', 'clean')
            ->assertJsonPath('upload_state', 'inspection_failed')
            ->assertJsonPath('upload_version', 4);
        $this->get(
            "/api/v1/operator/media/uploads/{$wrongContainerUpload}/preview",
        )->assertNotFound();
        $this->postCommand(
            "/api/v1/operator/media/uploads/{$wrongContainerUpload}/acceptances",
            ['expected_upload_version' => 4],
            (string) Str::ulid(),
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.media_library.stale_upload',
            );

        self::assertSame(0, DB::table('media_assets')
            ->where('organization_id', $organization->getKey())
            ->count());
    }

    public function test_unavailable_scanner_fails_closed(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T05:30:00+00:00');
        $organization = $this->prepareMediaOperator(
            '+919310000005',
            'Media Scanner Failure Tenant',
        );
        $this->app->instance(
            MediaMalwareScanner::class,
            new UnavailableMediaMalwareScanner,
        );
        $validAudio = 'RIFF'.pack('V', 16).'WAVEfmt clean-audio';
        $uploadId = $this->startUploadAndPart(
            'model_audio',
            'Scanner unavailable candidate',
            $validAudio,
        );

        $this->postCommand(
            "/api/v1/operator/media/uploads/{$uploadId}/finalizations",
            ['expected_upload_version' => 2],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('inspection_outcome', 'passed')
            ->assertJsonPath('malware_outcome', 'unavailable')
            ->assertJsonPath('scanner_name', 'unavailable')
            ->assertJsonPath('upload_state', 'scan_failed')
            ->assertJsonPath('upload_version', 4);
        $this->get(
            "/api/v1/operator/media/uploads/{$uploadId}/preview",
        )->assertNotFound();
        self::assertSame(0, DB::table('media_assets')
            ->where('organization_id', $organization->getKey())
            ->count());
    }

    public function test_private_storage_failure_is_safe_and_transactional(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T06:00:00+00:00');
        $this->prepareMediaOperator(
            '+919310000004',
            'Media Storage Failure Tenant',
        );
        $bytes = 'RIFF'.pack('V', 16).'WAVEfmt storage-failure';
        $hash = hash('sha256', $bytes);
        $started = $this->postCommand(
            '/api/v1/operator/media/uploads',
            [
                'asset_kind' => 'model_audio',
                'safe_label' => 'Storage failure candidate',
                'total_bytes' => strlen($bytes),
                'whole_object_hash' => $hash,
            ],
            (string) Str::ulid(),
        )->assertCreated();
        $uploadId = $started->json('id');
        self::assertIsString($uploadId);
        $this->app->instance(MediaObjectStore::class, new FailingMediaObjectStore);

        $failed = $this->postPart(
            $uploadId,
            $bytes,
            $hash,
            1,
            (string) Str::ulid(),
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.media_library.storage_failure',
            );
        self::assertStringNotContainsString(
            'private storage endpoint detail',
            (string) $failed->getContent(),
        );
        self::assertStringNotContainsString(
            "parts/{$uploadId}",
            (string) $failed->getContent(),
        );
        self::assertSame(0, DB::table('media_upload_parts')
            ->where('media_upload_id', $uploadId)
            ->count());
        $this->assertDatabaseHas('media_uploads', [
            'id' => $uploadId,
            'state' => 'uploading',
            'version' => 1,
            'received_part_count' => 0,
        ]);
    }

    private function prepareMediaOperator(
        string $mobileNumber,
        string $organizationName,
    ): Organization {
        $actor = $this->signInWeb($mobileNumber);
        $organization = $this->createOrganization($organizationName);
        [$membership, $context] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['media.library.read', 'media.upload', 'media.quarantine.review'],
            'media-validation-operator',
        );
        $this->selectMembership($membership);
        $this->freshenCurrentWebSession($actor);

        return $organization;
    }

    private function startUploadAndPart(
        string $assetKind,
        string $safeLabel,
        string $bytes,
    ): string {
        $hash = hash('sha256', $bytes);
        $started = $this->postCommand(
            '/api/v1/operator/media/uploads',
            [
                'asset_kind' => $assetKind,
                'safe_label' => $safeLabel,
                'total_bytes' => strlen($bytes),
                'whole_object_hash' => $hash,
            ],
            (string) Str::ulid(),
        )->assertCreated();
        $uploadId = $started->json('id');
        self::assertIsString($uploadId);
        $this->postPart(
            $uploadId,
            $bytes,
            $hash,
            1,
            (string) Str::ulid(),
        )->assertCreated();

        return $uploadId;
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

    /** @return TestResponse<Response> */
    private function postPart(
        string $uploadId,
        string $bytes,
        string $hash,
        int $uploadVersion,
        string $idempotencyKey,
    ): TestResponse {
        return $this->call(
            'POST',
            "/api/v1/operator/media/uploads/{$uploadId}/parts",
            cookies: $this->prepareCookiesForJsonRequest(),
            server: $this->transformHeadersToServerVars([
                'Accept' => 'application/json',
                'Content-Length' => (string) strlen($bytes),
                'Content-Type' => 'application/octet-stream',
                'Idempotency-Key' => $idempotencyKey,
                'Media-Part-Number' => '1',
                'Media-Part-SHA256' => $hash,
                'Media-Upload-Version' => (string) $uploadVersion,
                'Origin' => 'https://localhost',
            ]),
            content: $bytes,
        );
    }
}

final class AdminMediaLibraryOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class AdminMediaLibraryNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(string $mobileNumber, string $code): void {}
}

final class FailingMediaObjectStore implements MediaObjectStore
{
    public function putQuarantineBytes(string $key, string $bytes): void
    {
        throw new MediaObjectStoreFailure('private storage endpoint detail');
    }

    public function assembleQuarantine(
        array $partKeys,
        string $targetKey,
    ): MediaObjectDigest {
        throw new MediaObjectStoreFailure('private storage endpoint detail');
    }

    public function openQuarantine(string $key)
    {
        throw new MediaObjectStoreFailure('private storage endpoint detail');
    }

    public function promote(
        string $assetKind,
        string $quarantineKey,
        string $acceptedKey,
        int $expectedBytes,
        string $expectedSha256,
    ): void {
        throw new MediaObjectStoreFailure('private storage endpoint detail');
    }

    public function openAccepted(string $assetKind, string $key)
    {
        throw new MediaObjectStoreFailure('private storage endpoint detail');
    }

    public function deleteQuarantine(string $key): void
    {
        throw new MediaObjectStoreFailure('private storage endpoint detail');
    }
}
