<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Media\MediaObjectDigest;
use App\Media\MediaObjectStore;
use App\Models\Membership;
use App\Operator\MediaLibrary\MediaLibraryMaintenance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class MediaLibraryMaintenanceTest extends TenancyIntegrationTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_due_upload_expires_and_every_quarantine_object_is_reconciled(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T06:00:00+00:00');
        $organization = $this->createOrganization('Media cleanup tenant');
        [$membership] = $this->createMembership(
            $organization,
            $this->createUser(),
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $uploadId = (string) Str::ulid();
        $partKey = 'uploads/'.$uploadId.'/parts/1';
        $assembledKey = 'uploads/'.$uploadId.'/assembled';
        $this->insertUpload(
            organizationId: (string) $organization->getKey(),
            membershipId: (string) $membership->getKey(),
            uploadId: $uploadId,
            state: 'uploading',
            createdAt: now()->subHours(2),
            expiresAt: now()->subMinute(),
            quarantineKey: $assembledKey,
        );
        DB::table('media_upload_parts')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'media_upload_id' => $uploadId,
            'part_number' => 1,
            'byte_size' => 10,
            'part_hash' => hash('sha256', 'part-bytes'),
            'storage_object_key' => $partKey,
            'received_by_membership_id' => $membership->getKey(),
            'received_at' => now()->subMinutes(90),
            'created_at' => now()->subMinutes(90),
        ]);
        $objects = new RecordingMaintenanceMediaObjectStore;

        $result = (new MediaLibraryMaintenance($objects))->run();

        self::assertSame(1, $result['expired']);
        self::assertSame(2, $result['deleted']);
        self::assertSame(0, $result['retried']);
        self::assertSame(0, $result['dead_lettered']);
        self::assertSame(0, $result['unavailable']);
        self::assertSame(0, $result['overdue_cleanup_backlog']);
        self::assertSame(0, $result['dead_letter_backlog']);
        self::assertSame(
            ['state' => 'expired', 'version' => 2],
            (array) DB::table('media_uploads')->where('id', $uploadId)
                ->first(['state', 'version']),
        );
        self::assertSame(1, DB::table('media_upload_events')
            ->where('media_upload_id', $uploadId)
            ->where('event_type', 'upload.expired')
            ->count());
        self::assertSame(2, DB::table('media_object_deletion_requests')
            ->where('media_upload_id', $uploadId)
            ->where('state', 'completed')
            ->count());
        sort($objects->deletedKeys);
        self::assertSame([$assembledKey, $partKey], $objects->deletedKeys);
    }

    public function test_sixth_failed_cleanup_attempt_dead_letters_without_raw_failure_detail(): void
    {
        CarbonImmutable::setTestNow('2026-08-10T07:00:00+00:00');
        $organization = $this->createOrganization('Media dead-letter tenant');
        [$membership] = $this->createMembership(
            $organization,
            $this->createUser(),
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $uploadId = (string) Str::ulid();
        $this->insertUpload(
            organizationId: (string) $organization->getKey(),
            membershipId: (string) $membership->getKey(),
            uploadId: $uploadId,
            state: 'rejected',
            createdAt: now()->subDays(2),
            expiresAt: now()->subDay(),
            quarantineKey: null,
        );
        $requestId = (string) Str::ulid();
        DB::table('media_object_deletion_requests')->insert([
            'id' => $requestId,
            'organization_id' => $organization->getKey(),
            'media_upload_id' => $uploadId,
            'storage_object_key' => 'uploads/'.$uploadId.'/failed-object',
            'object_hash' => str_repeat('a', 64),
            'reason_key' => 'operator_rejected',
            'state' => 'pending',
            'attempt_count' => 5,
            'last_error_key' => 'media_quarantine_delete_failed',
            'due_at' => now()->subMinute(),
            'last_attempted_at' => now()->subMinutes(6),
            'completed_at' => null,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subMinutes(6),
        ]);
        $objects = new RecordingMaintenanceMediaObjectStore(failDeletes: true);

        $result = (new MediaLibraryMaintenance($objects))->run();

        self::assertSame(0, $result['expired']);
        self::assertSame(0, $result['deleted']);
        self::assertSame(0, $result['retried']);
        self::assertSame(1, $result['dead_lettered']);
        self::assertSame(1, $result['dead_letter_backlog']);
        $request = DB::table('media_object_deletion_requests')
            ->where('id', $requestId)
            ->sole(['state', 'attempt_count', 'last_error_key']);
        self::assertSame('dead_letter', $request->state);
        self::assertSame(6, $request->attempt_count);
        self::assertSame(
            'media_quarantine_delete_failed',
            $request->last_error_key,
        );
    }

    private function insertUpload(
        string $organizationId,
        string $membershipId,
        string $uploadId,
        string $state,
        mixed $createdAt,
        mixed $expiresAt,
        ?string $quarantineKey,
    ): void {
        $decided = in_array($state, ['accepted', 'rejected'], true);
        DB::table('media_uploads')->insert([
            'id' => $uploadId,
            'organization_id' => $organizationId,
            'candidate_asset_id' => (string) Str::ulid(),
            'asset_kind' => 'model_audio',
            'safe_label' => 'content-free cleanup candidate',
            'state' => $state,
            'version' => 1,
            'total_bytes' => 10,
            'whole_object_hash' => str_repeat('a', 64),
            'part_size_bytes' => 1_000_000,
            'expected_part_count' => 1,
            'received_part_count' => 1,
            'quarantine_object_key' => $quarantineKey,
            'accepted_object_key' => 'assets/'.Str::ulid().'/accepted',
            'selected_scan_id' => null,
            'accepted_asset_id' => null,
            'created_by_membership_id' => $membershipId,
            'decided_by_membership_id' => $decided ? $membershipId : null,
            'decision_reason_key' => $decided ? 'operator_rejected' : null,
            'expires_at' => $expiresAt,
            'review_expires_at' => null,
            'decided_at' => $decided ? now()->subDay() : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}

final class RecordingMaintenanceMediaObjectStore implements MediaObjectStore
{
    /** @var list<string> */
    public array $deletedKeys = [];

    public function __construct(private readonly bool $failDeletes = false) {}

    public function putQuarantineBytes(string $key, string $bytes): void
    {
        throw new RuntimeException('Not used by maintenance tests.');
    }

    public function assembleQuarantine(
        array $partKeys,
        string $targetKey,
    ): MediaObjectDigest {
        throw new RuntimeException('Not used by maintenance tests.');
    }

    public function openQuarantine(string $key)
    {
        throw new RuntimeException('Not used by maintenance tests.');
    }

    public function promote(
        string $assetKind,
        string $quarantineKey,
        string $acceptedKey,
        int $expectedBytes,
        string $expectedSha256,
    ): void {
        throw new RuntimeException('Not used by maintenance tests.');
    }

    public function openAccepted(string $assetKind, string $key)
    {
        throw new RuntimeException('Not used by maintenance tests.');
    }

    public function deleteQuarantine(string $key): void
    {
        if ($this->failDeletes) {
            throw new RuntimeException('private endpoint detail');
        }

        $this->deletedKeys[] = $key;
    }
}
