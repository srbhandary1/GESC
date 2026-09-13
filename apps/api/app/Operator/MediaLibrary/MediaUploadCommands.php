<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary;

use App\Audit\AuditEventRecorder;
use App\Curriculum\CanonicalJson;
use App\Media\Exceptions\MediaObjectStoreFailure;
use App\Media\MediaInspection;
use App\Media\MediaInspector;
use App\Media\MediaKindPolicy;
use App\Media\MediaMalwareScan;
use App\Media\MediaMalwareScanner;
use App\Media\MediaObjectStore;
use App\Models\User;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryConflict;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Outbox\OutboxEventRecorder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class MediaUploadCommands
{
    public function __construct(
        private MediaLibraryAuthorization $authorization,
        private MediaLibraryIdempotency $idempotency,
        private MediaObjectStore $objects,
        private MediaInspector $inspector,
        private MediaMalwareScanner $scanner,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function start(
        User $actor,
        string $assetKind,
        string $safeLabel,
        int $totalBytes,
        string $wholeObjectHash,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        return DB::transaction(function () use (
            $actor,
            $assetKind,
            $safeLabel,
            $totalBytes,
            $wholeObjectHash,
            $idempotencyKey,
        ): MediaLibraryCommandResult {
            $this->authorization->assert($actor, 'media.upload');
            $context = $this->authorization->context($actor);
            $membership = $this->authorization->actorMembership($actor);
            $safeLabel = $this->safeLabel($safeLabel);
            $maximum = $this->maximumBytes($assetKind);

            if (
                $totalBytes < 1
                || $totalBytes > $maximum
                || preg_match('/\A[0-9a-f]{64}\z/', $wholeObjectHash) !== 1
            ) {
                throw new MediaLibraryRejected;
            }

            $operation = 'media.upload.start';
            $fingerprint = $this->idempotency->fingerprint(
                $operation,
                'organization',
                $context->organizationId,
                [
                    'asset_kind' => $assetKind,
                    'safe_label' => $safeLabel,
                    'total_bytes' => $totalBytes,
                    'whole_object_hash' => $wholeObjectHash,
                ],
            );
            $claim = $this->idempotency->claim(
                $actor,
                $idempotencyKey,
                $operation,
                'organization',
                $context->organizationId,
                $fingerprint,
            );

            if ($claim->replayed) {
                $uploadId = $claim->resultId
                    ?? throw new MediaLibraryConflict(
                        MediaLibraryConflict::IDEMPOTENCY,
                    );

                return new MediaLibraryCommandResult(
                    'media_upload',
                    $uploadId,
                    true,
                    $this->uploadSummary($context->organizationId, $uploadId),
                );
            }

            $partSize = $this->partSize();
            $expectedParts = (int) ceil($totalBytes / $partSize);
            $uploadId = (string) Str::ulid();
            $candidateAssetId = (string) Str::ulid();
            $now = CarbonImmutable::now();
            DB::table('media_uploads')->insert([
                'id' => $uploadId,
                'organization_id' => $context->organizationId,
                'candidate_asset_id' => $candidateAssetId,
                'asset_kind' => $assetKind,
                'safe_label' => $safeLabel,
                'state' => 'uploading',
                'version' => 1,
                'total_bytes' => $totalBytes,
                'whole_object_hash' => $wholeObjectHash,
                'part_size_bytes' => $partSize,
                'expected_part_count' => $expectedParts,
                'received_part_count' => 0,
                'quarantine_object_key' => null,
                'accepted_object_key' => sprintf(
                    'accepted/%s/%s',
                    $candidateAssetId,
                    $wholeObjectHash,
                ),
                'selected_scan_id' => null,
                'accepted_asset_id' => null,
                'created_by_membership_id' => $this->string(
                    $membership,
                    'id',
                ),
                'decided_by_membership_id' => null,
                'decision_reason_key' => null,
                'expires_at' => $now->addHours($this->uploadExpiryHours()),
                'review_expires_at' => null,
                'decided_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->uploadEvent(
                organizationId: $context->organizationId,
                uploadId: $uploadId,
                eventType: 'upload.started',
                uploadVersion: 1,
                actorMembershipId: $this->string($membership, 'id'),
                at: $now,
            );
            $this->idempotency->complete(
                $claim,
                'media_upload',
                $uploadId,
                $now,
            );
            $this->recordEvidence(
                'media.upload.started',
                'media_upload',
                $uploadId,
                ['media_upload_id' => $uploadId],
            );

            return new MediaLibraryCommandResult(
                'media_upload',
                $uploadId,
                false,
                $this->uploadSummary($context->organizationId, $uploadId),
            );
        }, 3);
    }

    public function part(
        User $actor,
        string $uploadId,
        int $partNumber,
        int $expectedUploadVersion,
        string $partHash,
        string $bytes,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        try {
            return DB::transaction(function () use (
                $actor,
                $uploadId,
                $partNumber,
                $expectedUploadVersion,
                $partHash,
                $bytes,
                $idempotencyKey,
            ): MediaLibraryCommandResult {
                $this->authorization->assert($actor, 'media.upload');
                $context = $this->authorization->context($actor);
                $membership = $this->authorization->actorMembership($actor);
                $operation = 'media.upload.part';
                $fingerprint = $this->idempotency->fingerprint(
                    $operation,
                    'media_upload',
                    $uploadId,
                    [
                        'expected_upload_version' => $expectedUploadVersion,
                        'part_hash' => $partHash,
                        'part_number' => $partNumber,
                    ],
                );
                $claim = $this->idempotency->claim(
                    $actor,
                    $idempotencyKey,
                    $operation,
                    'media_upload',
                    $uploadId,
                    $fingerprint,
                );

                if ($claim->replayed) {
                    $partId = $claim->resultId
                        ?? throw new MediaLibraryConflict(
                            MediaLibraryConflict::IDEMPOTENCY,
                        );

                    return new MediaLibraryCommandResult(
                        'media_upload_part',
                        $partId,
                        true,
                        $this->partSummary(
                            $context->organizationId,
                            $uploadId,
                            $partId,
                        ),
                    );
                }

                $upload = $this->lockedUpload(
                    $context->organizationId,
                    $uploadId,
                );
                $this->assertUploadState(
                    $upload,
                    'uploading',
                    $expectedUploadVersion,
                );
                $received = $this->integer($upload, 'received_part_count');
                $expectedParts = $this->integer(
                    $upload,
                    'expected_part_count',
                );

                if (
                    $partNumber !== $received + 1
                    || $partNumber > $expectedParts
                    || preg_match('/\A[0-9a-f]{64}\z/', $partHash) !== 1
                    || ! hash_equals(hash('sha256', $bytes), $partHash)
                    || strlen($bytes) !== $this->expectedPartBytes(
                        $upload,
                        $partNumber,
                    )
                ) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_UPLOAD,
                    );
                }

                $partId = (string) Str::ulid();
                $partKey = sprintf(
                    'parts/%s/%06d-%s',
                    $uploadId,
                    $partNumber,
                    $partHash,
                );
                $this->objects->putQuarantineBytes($partKey, $bytes);
                $now = CarbonImmutable::now();
                DB::table('media_upload_parts')->insert([
                    'id' => $partId,
                    'organization_id' => $context->organizationId,
                    'media_upload_id' => $uploadId,
                    'part_number' => $partNumber,
                    'byte_size' => strlen($bytes),
                    'part_hash' => $partHash,
                    'storage_object_key' => $partKey,
                    'received_by_membership_id' => $this->string(
                        $membership,
                        'id',
                    ),
                    'received_at' => $now,
                    'created_at' => $now,
                ]);
                $nextVersion = $expectedUploadVersion + 1;
                $updated = DB::table('media_uploads')
                    ->where('organization_id', $context->organizationId)
                    ->where('id', $uploadId)
                    ->where('version', $expectedUploadVersion)
                    ->update([
                        'version' => $nextVersion,
                        'received_part_count' => $partNumber,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_UPLOAD,
                    );
                }

                $this->uploadEvent(
                    organizationId: $context->organizationId,
                    uploadId: $uploadId,
                    eventType: 'part.received',
                    uploadVersion: $nextVersion,
                    actorMembershipId: $this->string($membership, 'id'),
                    at: $now,
                );
                $this->idempotency->complete(
                    $claim,
                    'media_upload_part',
                    $partId,
                    $now,
                );

                return new MediaLibraryCommandResult(
                    'media_upload_part',
                    $partId,
                    false,
                    $this->partSummary(
                        $context->organizationId,
                        $uploadId,
                        $partId,
                    ),
                );
            }, 3);
        } catch (MediaObjectStoreFailure $exception) {
            throw new MediaLibraryConflict(
                MediaLibraryConflict::STORAGE,
                $exception,
            );
        }
    }

    public function finalize(
        User $actor,
        string $uploadId,
        int $expectedUploadVersion,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        try {
            return DB::transaction(function () use (
                $actor,
                $uploadId,
                $expectedUploadVersion,
                $idempotencyKey,
            ): MediaLibraryCommandResult {
                $this->authorization->assert($actor, 'media.upload');
                $context = $this->authorization->context($actor);
                $membership = $this->authorization->actorMembership($actor);
                $operation = 'media.upload.finalize';
                $fingerprint = $this->idempotency->fingerprint(
                    $operation,
                    'media_upload',
                    $uploadId,
                    ['expected_upload_version' => $expectedUploadVersion],
                );
                $claim = $this->idempotency->claim(
                    $actor,
                    $idempotencyKey,
                    $operation,
                    'media_upload',
                    $uploadId,
                    $fingerprint,
                );

                if ($claim->replayed) {
                    $scanId = $claim->resultId
                        ?? throw new MediaLibraryConflict(
                            MediaLibraryConflict::IDEMPOTENCY,
                        );

                    return new MediaLibraryCommandResult(
                        'media_security_scan',
                        $scanId,
                        true,
                        $this->scanSummary(
                            $context->organizationId,
                            $uploadId,
                            $scanId,
                        ),
                    );
                }

                $upload = $this->lockedUpload(
                    $context->organizationId,
                    $uploadId,
                );
                $this->assertUploadState(
                    $upload,
                    'uploading',
                    $expectedUploadVersion,
                );

                if (
                    $this->integer($upload, 'received_part_count')
                        !== $this->integer($upload, 'expected_part_count')
                ) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_UPLOAD,
                    );
                }

                $parts = DB::table('media_upload_parts')
                    ->where('organization_id', $context->organizationId)
                    ->where('media_upload_id', $uploadId)
                    ->orderBy('part_number')
                    ->lockForUpdate()
                    ->get(['part_number', 'storage_object_key']);
                $partKeys = [];
                $expectedPart = 1;

                foreach ($parts as $part) {
                    if ($this->integer($part, 'part_number') !== $expectedPart) {
                        throw new MediaLibraryConflict(
                            MediaLibraryConflict::STALE_UPLOAD,
                        );
                    }

                    $partKeys[] = $this->string($part, 'storage_object_key');
                    $expectedPart++;
                }

                if (count($partKeys) !== $this->integer($upload, 'expected_part_count')) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_UPLOAD,
                    );
                }

                $now = CarbonImmutable::now();
                $finalizedVersion = $expectedUploadVersion + 1;
                DB::table('media_uploads')
                    ->where('organization_id', $context->organizationId)
                    ->where('id', $uploadId)
                    ->where('version', $expectedUploadVersion)
                    ->update([
                        'state' => 'finalized',
                        'version' => $finalizedVersion,
                        'updated_at' => $now,
                    ]);
                $this->uploadEvent(
                    organizationId: $context->organizationId,
                    uploadId: $uploadId,
                    eventType: 'upload.finalized',
                    uploadVersion: $finalizedVersion,
                    actorMembershipId: $this->string($membership, 'id'),
                    at: $now,
                );
                $objectHash = $this->string($upload, 'whole_object_hash');
                $objectKey = sprintf('objects/%s/%s', $uploadId, $objectHash);
                $digest = $this->objects->assembleQuarantine(
                    $partKeys,
                    $objectKey,
                );
                $expectedBytes = $this->integer($upload, 'total_bytes');

                if (
                    $digest->byteSize !== $expectedBytes
                    || ! hash_equals($digest->sha256, $objectHash)
                ) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STORAGE,
                    );
                }

                $inspectionStream = $this->objects->openQuarantine($objectKey);

                try {
                    $inspection = $this->inspector->inspect(
                        $inspectionStream,
                        $this->string($upload, 'asset_kind'),
                        $expectedBytes,
                        $objectHash,
                    );
                } finally {
                    fclose($inspectionStream);
                }

                $scanStream = $this->objects->openQuarantine($objectKey);

                try {
                    $malware = $this->scanner->scan(
                        $scanStream,
                        $expectedBytes,
                        $objectHash,
                    );
                } finally {
                    fclose($scanStream);
                }

                $this->assertInspectionPolicy(
                    $inspection,
                    $this->string($upload, 'asset_kind'),
                );
                $scanId = (string) Str::ulid();
                $technicalHash = CanonicalJson::sha256([
                    'asset_kind' => $this->string($upload, 'asset_kind'),
                    'byte_size' => $expectedBytes,
                    'detected_mime_type' => $inspection->mimeType,
                    'duration_milliseconds' => $inspection->durationMilliseconds,
                    'inspection_outcome' => $inspection->outcome,
                    'inspector_name' => $inspection->inspectorName,
                    'inspector_policy_version' => $inspection->policyVersion,
                    'malware_outcome' => $malware->outcome,
                    'object_hash' => $objectHash,
                    'scanner_engine_version' => $malware->engineVersion,
                    'scanner_name' => $malware->scannerName,
                    'scanner_policy_version' => $malware->policyVersion,
                ]);
                DB::table('media_security_scans')->insert([
                    'id' => $scanId,
                    'organization_id' => $context->organizationId,
                    'media_upload_id' => $uploadId,
                    'media_asset_id' => null,
                    'asset_kind' => $this->string($upload, 'asset_kind'),
                    'object_hash' => $objectHash,
                    'byte_size' => $expectedBytes,
                    'inspection_outcome' => $inspection->outcome,
                    'malware_outcome' => $malware->outcome,
                    'detected_mime_type' => $inspection->mimeType,
                    'duration_milliseconds' => $inspection->durationMilliseconds,
                    'inspector_name' => $inspection->inspectorName,
                    'inspector_policy_version' => $inspection->policyVersion,
                    'scanner_name' => $malware->scannerName,
                    'scanner_engine_version' => $malware->engineVersion,
                    'scanner_policy_version' => $malware->policyVersion,
                    'technical_metadata_hash' => $technicalHash,
                    'inspected_at' => $now,
                    'scanned_at' => $now,
                    'created_at' => $now,
                ]);
                [$state, $eventType, $reasonKey] = $this->scanTransition(
                    $inspection,
                    $malware,
                );
                $scanVersion = $finalizedVersion + 1;
                $updated = DB::table('media_uploads')
                    ->where('organization_id', $context->organizationId)
                    ->where('id', $uploadId)
                    ->where('version', $finalizedVersion)
                    ->where('state', 'finalized')
                    ->update([
                        'state' => $state,
                        'version' => $scanVersion,
                        'quarantine_object_key' => $objectKey,
                        'selected_scan_id' => $scanId,
                        'review_expires_at' => $state === 'scan_clean'
                            ? $now->addDays($this->cleanReviewExpiryDays())
                            : null,
                        'decision_reason_key' => $reasonKey,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_UPLOAD,
                    );
                }

                $this->uploadEvent(
                    organizationId: $context->organizationId,
                    uploadId: $uploadId,
                    eventType: $eventType,
                    uploadVersion: $scanVersion,
                    actorMembershipId: $this->string($membership, 'id'),
                    at: $now,
                    scanId: $scanId,
                    reasonKey: $reasonKey,
                );

                if ($state !== 'scan_clean') {
                    $this->queueQuarantineCleanup(
                        $context->organizationId,
                        $uploadId,
                        $objectHash,
                        [$objectKey, ...$partKeys],
                        'media_scan_not_clean',
                        $now,
                    );
                }

                $this->idempotency->complete(
                    $claim,
                    'media_security_scan',
                    $scanId,
                    $now,
                );
                $this->recordEvidence(
                    'media.upload.scanned',
                    'media_upload',
                    $uploadId,
                    [
                        'media_upload_id' => $uploadId,
                        'media_security_scan_id' => $scanId,
                    ],
                );

                return new MediaLibraryCommandResult(
                    'media_security_scan',
                    $scanId,
                    false,
                    $this->scanSummary(
                        $context->organizationId,
                        $uploadId,
                        $scanId,
                    ),
                );
            }, 3);
        } catch (MediaObjectStoreFailure $exception) {
            throw new MediaLibraryConflict(
                MediaLibraryConflict::STORAGE,
                $exception,
            );
        }
    }

    public function accept(
        User $actor,
        string $uploadId,
        int $expectedUploadVersion,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        try {
            return DB::transaction(function () use (
                $actor,
                $uploadId,
                $expectedUploadVersion,
                $idempotencyKey,
            ): MediaLibraryCommandResult {
                $this->authorization->assert(
                    $actor,
                    'media.quarantine.review',
                );
                $context = $this->authorization->context($actor);
                $membership = $this->authorization->actorMembership($actor);
                $operation = 'media.quarantine.accept';
                $fingerprint = $this->idempotency->fingerprint(
                    $operation,
                    'media_upload',
                    $uploadId,
                    ['expected_upload_version' => $expectedUploadVersion],
                );
                $claim = $this->idempotency->claim(
                    $actor,
                    $idempotencyKey,
                    $operation,
                    'media_upload',
                    $uploadId,
                    $fingerprint,
                );

                if ($claim->replayed) {
                    $assetId = $claim->resultId
                        ?? throw new MediaLibraryConflict(
                            MediaLibraryConflict::IDEMPOTENCY,
                        );

                    return new MediaLibraryCommandResult(
                        'media_asset',
                        $assetId,
                        true,
                        [
                            'media_asset_id' => $assetId,
                            'upload_id' => $uploadId,
                            'state' => 'accepted',
                        ],
                    );
                }

                $upload = $this->lockedUpload(
                    $context->organizationId,
                    $uploadId,
                );
                $this->assertUploadState(
                    $upload,
                    'scan_clean',
                    $expectedUploadVersion,
                );
                $scanId = $this->string($upload, 'selected_scan_id');
                $scan = $this->cleanScan(
                    $context->organizationId,
                    $uploadId,
                    $scanId,
                    $this->string($upload, 'whole_object_hash'),
                );
                $quarantineKey = $this->string(
                    $upload,
                    'quarantine_object_key',
                );
                $acceptedKey = $this->string($upload, 'accepted_object_key');
                $assetKind = $this->string($upload, 'asset_kind');
                $assetHash = $this->string($upload, 'whole_object_hash');
                $byteSize = $this->integer($upload, 'total_bytes');
                $this->objects->promote(
                    $assetKind,
                    $quarantineKey,
                    $acceptedKey,
                    $byteSize,
                    $assetHash,
                );
                $assetId = $this->string($upload, 'candidate_asset_id');
                $actorMembershipId = $this->string($membership, 'id');
                $now = CarbonImmutable::now();
                DB::table('media_assets')->insert([
                    'id' => $assetId,
                    'organization_id' => $context->organizationId,
                    'key' => 'managed-'.$assetId,
                    'asset_kind' => $assetKind,
                    'storage_object_key' => $acceptedKey,
                    'mime_type' => $this->string($scan, 'detected_mime_type'),
                    'byte_size' => $byteSize,
                    'asset_hash' => $assetHash,
                    'uploaded_by_membership_id' => $this->string(
                        $upload,
                        'created_by_membership_id',
                    ),
                    'uploader_attribution' => 'organization-member-upload',
                    'uploaded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('media_asset_lifecycles')->insert([
                    'organization_id' => $context->organizationId,
                    'media_asset_id' => $assetId,
                    'asset_kind' => $assetKind,
                    'state' => 'active',
                    'version' => 1,
                    'selected_scan_id' => $scanId,
                    'predecessor_media_asset_id' => null,
                    'managed_by_membership_id' => $actorMembershipId,
                    'retired_by_membership_id' => null,
                    'retirement_reason_key' => null,
                    'managed_at' => $now,
                    'retired_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->assetEvent(
                    $context->organizationId,
                    $assetId,
                    'asset.accepted',
                    1,
                    $actorMembershipId,
                    $now,
                    $scanId,
                    uploadId: $uploadId,
                );
                $acceptedVersion = $expectedUploadVersion + 1;
                $updated = DB::table('media_uploads')
                    ->where('organization_id', $context->organizationId)
                    ->where('id', $uploadId)
                    ->where('version', $expectedUploadVersion)
                    ->where('state', 'scan_clean')
                    ->update([
                        'state' => 'accepted',
                        'version' => $acceptedVersion,
                        'accepted_asset_id' => $assetId,
                        'decided_by_membership_id' => $actorMembershipId,
                        'decision_reason_key' => 'quarantine_review_accepted',
                        'decided_at' => $now,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new MediaLibraryConflict(
                        MediaLibraryConflict::STALE_UPLOAD,
                    );
                }

                $this->uploadEvent(
                    organizationId: $context->organizationId,
                    uploadId: $uploadId,
                    eventType: 'quarantine.accepted',
                    uploadVersion: $acceptedVersion,
                    actorMembershipId: $actorMembershipId,
                    at: $now,
                    scanId: $scanId,
                    assetId: $assetId,
                    reasonKey: 'quarantine_review_accepted',
                );
                $partKeys = DB::table('media_upload_parts')
                    ->where('organization_id', $context->organizationId)
                    ->where('media_upload_id', $uploadId)
                    ->pluck('storage_object_key')
                    ->filter(static fn (mixed $key): bool => is_string($key))
                    ->values()
                    ->all();
                $this->queueQuarantineCleanup(
                    $context->organizationId,
                    $uploadId,
                    $assetHash,
                    [$quarantineKey, ...$partKeys],
                    'media_quarantine_promoted',
                    $now,
                );
                $this->idempotency->complete(
                    $claim,
                    'media_asset',
                    $assetId,
                    $now,
                );
                $this->recordEvidence(
                    'media.asset.accepted',
                    'media_asset',
                    $assetId,
                    [
                        'media_asset_id' => $assetId,
                        'media_security_scan_id' => $scanId,
                        'media_upload_id' => $uploadId,
                    ],
                );

                return new MediaLibraryCommandResult(
                    'media_asset',
                    $assetId,
                    false,
                    [
                        'media_asset_id' => $assetId,
                        'upload_id' => $uploadId,
                        'state' => 'accepted',
                        'lifecycle_version' => 1,
                        'selected_scan_id' => $scanId,
                    ],
                );
            }, 3);
        } catch (MediaObjectStoreFailure $exception) {
            throw new MediaLibraryConflict(
                MediaLibraryConflict::STORAGE,
                $exception,
            );
        }
    }

    public function reject(
        User $actor,
        string $uploadId,
        int $expectedUploadVersion,
        string $reasonKey,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        return DB::transaction(function () use (
            $actor,
            $uploadId,
            $expectedUploadVersion,
            $reasonKey,
            $idempotencyKey,
        ): MediaLibraryCommandResult {
            $this->authorization->assert(
                $actor,
                'media.quarantine.review',
            );
            $context = $this->authorization->context($actor);
            $membership = $this->authorization->actorMembership($actor);
            $reasonKey = $this->safeKey($reasonKey);
            $operation = 'media.quarantine.reject';
            $fingerprint = $this->idempotency->fingerprint(
                $operation,
                'media_upload',
                $uploadId,
                [
                    'expected_upload_version' => $expectedUploadVersion,
                    'reason_key' => $reasonKey,
                ],
            );
            $claim = $this->idempotency->claim(
                $actor,
                $idempotencyKey,
                $operation,
                'media_upload',
                $uploadId,
                $fingerprint,
            );

            if ($claim->replayed) {
                return new MediaLibraryCommandResult(
                    'media_upload',
                    $uploadId,
                    true,
                    $this->uploadSummary($context->organizationId, $uploadId),
                );
            }

            $upload = $this->lockedUpload(
                $context->organizationId,
                $uploadId,
            );
            $state = $this->string($upload, 'state');

            if (
                ! in_array(
                    $state,
                    ['scan_clean', 'inspection_failed', 'scan_failed', 'promotion_failed'],
                    true,
                )
                || $this->integer($upload, 'version') !== $expectedUploadVersion
            ) {
                throw new MediaLibraryConflict(
                    MediaLibraryConflict::STALE_UPLOAD,
                );
            }

            $now = CarbonImmutable::now();
            $nextVersion = $expectedUploadVersion + 1;
            $actorMembershipId = $this->string($membership, 'id');
            DB::table('media_uploads')
                ->where('organization_id', $context->organizationId)
                ->where('id', $uploadId)
                ->where('version', $expectedUploadVersion)
                ->update([
                    'state' => 'rejected',
                    'version' => $nextVersion,
                    'decided_by_membership_id' => $actorMembershipId,
                    'decision_reason_key' => $reasonKey,
                    'decided_at' => $now,
                    'updated_at' => $now,
                ]);
            $this->uploadEvent(
                organizationId: $context->organizationId,
                uploadId: $uploadId,
                eventType: 'quarantine.rejected',
                uploadVersion: $nextVersion,
                actorMembershipId: $actorMembershipId,
                at: $now,
                scanId: $this->nullableString($upload, 'selected_scan_id'),
                reasonKey: $reasonKey,
            );
            $storedKeys = DB::table('media_upload_parts')
                ->where('organization_id', $context->organizationId)
                ->where('media_upload_id', $uploadId)
                ->pluck('storage_object_key');
            $keys = [];

            foreach ($storedKeys as $key) {
                if (is_string($key)) {
                    $keys[] = $key;
                }
            }
            $assembled = $this->nullableString(
                $upload,
                'quarantine_object_key',
            );

            if ($assembled !== null) {
                array_unshift($keys, $assembled);
            }

            $this->queueQuarantineCleanup(
                $context->organizationId,
                $uploadId,
                $this->string($upload, 'whole_object_hash'),
                $keys,
                'media_quarantine_rejected',
                $now,
            );
            $this->idempotency->complete(
                $claim,
                'media_upload',
                $uploadId,
                $now,
            );
            $this->recordEvidence(
                'media.quarantine.rejected',
                'media_upload',
                $uploadId,
                ['media_upload_id' => $uploadId],
            );

            return new MediaLibraryCommandResult(
                'media_upload',
                $uploadId,
                false,
                $this->uploadSummary($context->organizationId, $uploadId),
            );
        }, 3);
    }

    public function adopt(
        User $actor,
        string $assetId,
        string $selectedAssetHash,
        string $idempotencyKey,
    ): MediaLibraryCommandResult {
        try {
            return DB::transaction(function () use (
                $actor,
                $assetId,
                $selectedAssetHash,
                $idempotencyKey,
            ): MediaLibraryCommandResult {
                $this->authorization->assert(
                    $actor,
                    'media.quarantine.review',
                );
                $context = $this->authorization->context($actor);
                $membership = $this->authorization->actorMembership($actor);
                $operation = 'media.asset.adopt';
                $fingerprint = $this->idempotency->fingerprint(
                    $operation,
                    'media_asset',
                    $assetId,
                    ['selected_asset_hash' => $selectedAssetHash],
                );
                $claim = $this->idempotency->claim(
                    $actor,
                    $idempotencyKey,
                    $operation,
                    'media_asset',
                    $assetId,
                    $fingerprint,
                );

                if ($claim->replayed) {
                    $resultId = $claim->resultId
                        ?? throw new MediaLibraryConflict(
                            MediaLibraryConflict::IDEMPOTENCY,
                        );

                    return new MediaLibraryCommandResult(
                        $claim->resultType ?? 'media_security_scan',
                        $resultId,
                        true,
                        ['media_asset_id' => $assetId],
                    );
                }

                $asset = DB::table('media_assets')
                    ->where('organization_id', $context->organizationId)
                    ->where('id', $assetId)
                    ->where('asset_hash', $selectedAssetHash)
                    ->whereIn('asset_kind', MediaKindPolicy::SUPPORTED)
                    ->lockForUpdate()
                    ->first([
                        'asset_kind',
                        'storage_object_key',
                        'mime_type',
                        'byte_size',
                        'asset_hash',
                    ]);

                if (
                    ! $asset instanceof stdClass
                    || DB::table('media_asset_lifecycles')
                        ->where('organization_id', $context->organizationId)
                        ->where('media_asset_id', $assetId)
                        ->exists()
                ) {
                    throw new MediaLibraryRejected;
                }

                $assetKind = $this->string($asset, 'asset_kind');
                $byteSize = $this->integer($asset, 'byte_size');
                $assetHash = $this->string($asset, 'asset_hash');
                $storageKey = $this->string($asset, 'storage_object_key');
                $inspectionStream = $this->objects->openAccepted(
                    $assetKind,
                    $storageKey,
                );

                try {
                    $inspection = $this->inspector->inspect(
                        $inspectionStream,
                        $assetKind,
                        $byteSize,
                        $assetHash,
                    );
                } finally {
                    fclose($inspectionStream);
                }

                $scanStream = $this->objects->openAccepted(
                    $assetKind,
                    $storageKey,
                );

                try {
                    $malware = $this->scanner->scan(
                        $scanStream,
                        $byteSize,
                        $assetHash,
                    );
                } finally {
                    fclose($scanStream);
                }

                if (
                    $inspection->passed()
                    && ! hash_equals(
                        $this->string($asset, 'mime_type'),
                        $inspection->mimeType ?? '',
                    )
                ) {
                    $inspection = new MediaInspection(
                        outcome: 'failed',
                        mimeType: $inspection->mimeType,
                        durationMilliseconds: $inspection->durationMilliseconds,
                        inspectorName: $inspection->inspectorName,
                        policyVersion: $inspection->policyVersion,
                    );
                }

                $this->assertInspectionPolicy($inspection, $assetKind);
                $now = CarbonImmutable::now();
                $scanId = (string) Str::ulid();
                DB::table('media_security_scans')->insert([
                    'id' => $scanId,
                    'organization_id' => $context->organizationId,
                    'media_upload_id' => null,
                    'media_asset_id' => $assetId,
                    'asset_kind' => $assetKind,
                    'object_hash' => $assetHash,
                    'byte_size' => $byteSize,
                    'inspection_outcome' => $inspection->outcome,
                    'malware_outcome' => $malware->outcome,
                    'detected_mime_type' => $inspection->mimeType,
                    'duration_milliseconds' => $inspection->durationMilliseconds,
                    'inspector_name' => $inspection->inspectorName,
                    'inspector_policy_version' => $inspection->policyVersion,
                    'scanner_name' => $malware->scannerName,
                    'scanner_engine_version' => $malware->engineVersion,
                    'scanner_policy_version' => $malware->policyVersion,
                    'technical_metadata_hash' => CanonicalJson::sha256([
                        'asset_kind' => $assetKind,
                        'byte_size' => $byteSize,
                        'detected_mime_type' => $inspection->mimeType,
                        'duration_milliseconds' => $inspection->durationMilliseconds,
                        'inspection_outcome' => $inspection->outcome,
                        'inspector_name' => $inspection->inspectorName,
                        'inspector_policy_version' => $inspection->policyVersion,
                        'malware_outcome' => $malware->outcome,
                        'object_hash' => $assetHash,
                        'scanner_engine_version' => $malware->engineVersion,
                        'scanner_name' => $malware->scannerName,
                        'scanner_policy_version' => $malware->policyVersion,
                    ]),
                    'inspected_at' => $now,
                    'scanned_at' => $now,
                    'created_at' => $now,
                ]);
                $clean = $inspection->passed() && $malware->clean();
                $resultType = 'media_security_scan';
                $resultId = $scanId;

                if ($clean) {
                    $actorMembershipId = $this->string($membership, 'id');
                    DB::table('media_asset_lifecycles')->insert([
                        'organization_id' => $context->organizationId,
                        'media_asset_id' => $assetId,
                        'asset_kind' => $assetKind,
                        'state' => 'active',
                        'version' => 1,
                        'selected_scan_id' => $scanId,
                        'predecessor_media_asset_id' => null,
                        'managed_by_membership_id' => $actorMembershipId,
                        'retired_by_membership_id' => null,
                        'retirement_reason_key' => null,
                        'managed_at' => $now,
                        'retired_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $this->assetEvent(
                        $context->organizationId,
                        $assetId,
                        'asset.adopted',
                        1,
                        $actorMembershipId,
                        $now,
                        $scanId,
                    );
                    $resultType = 'media_asset_lifecycle';
                    $resultId = $assetId;
                    $this->recordEvidence(
                        'media.asset.adopted',
                        'media_asset',
                        $assetId,
                        [
                            'media_asset_id' => $assetId,
                            'media_security_scan_id' => $scanId,
                        ],
                    );
                } else {
                    $this->recordEvidence(
                        'media.asset.adoption_scan_failed',
                        'media_security_scan',
                        $scanId,
                        [
                            'media_asset_id' => $assetId,
                            'media_security_scan_id' => $scanId,
                        ],
                    );
                }

                $this->idempotency->complete(
                    $claim,
                    $resultType,
                    $resultId,
                    $now,
                );

                return new MediaLibraryCommandResult(
                    $resultType,
                    $resultId,
                    false,
                    [
                        'media_asset_id' => $assetId,
                        'media_security_scan_id' => $scanId,
                        'inspection_outcome' => $inspection->outcome,
                        'malware_outcome' => $malware->outcome,
                        'adopted' => $clean,
                    ],
                );
            }, 3);
        } catch (MediaObjectStoreFailure $exception) {
            throw new MediaLibraryConflict(
                MediaLibraryConflict::STORAGE,
                $exception,
            );
        }
    }

    private function lockedUpload(string $organizationId, string $uploadId): stdClass
    {
        $upload = DB::table('media_uploads')
            ->where('organization_id', $organizationId)
            ->where('id', $uploadId)
            ->lockForUpdate()
            ->first();

        if (! $upload instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        return $upload;
    }

    private function assertUploadState(
        stdClass $upload,
        string $state,
        int $expectedVersion,
    ): void {
        if (
            $this->string($upload, 'state') !== $state
            || $this->integer($upload, 'version') !== $expectedVersion
        ) {
            throw new MediaLibraryConflict(
                MediaLibraryConflict::STALE_UPLOAD,
            );
        }
    }

    private function expectedPartBytes(stdClass $upload, int $partNumber): int
    {
        $partSize = $this->integer($upload, 'part_size_bytes');
        $partCount = $this->integer($upload, 'expected_part_count');

        if ($partNumber < $partCount) {
            return $partSize;
        }

        return $this->integer($upload, 'total_bytes')
            - ($partSize * ($partCount - 1));
    }

    private function assertInspectionPolicy(
        MediaInspection $inspection,
        string $assetKind,
    ): void {
        if (! $inspection->passed()) {
            return;
        }

        $mimeTypes = config('media.kinds.'.$assetKind.'.mime_types');
        $minimum = config(
            'media.kinds.'.$assetKind.'.minimum_duration_milliseconds',
        );
        $maximum = config(
            'media.kinds.'.$assetKind.'.maximum_duration_milliseconds',
        );

        if (
            ! is_array($mimeTypes)
            || ! is_string($inspection->mimeType)
            || ! in_array($inspection->mimeType, $mimeTypes, true)
            || ! is_int($inspection->durationMilliseconds)
            || ! is_int($minimum)
            || ! is_int($maximum)
            || $inspection->durationMilliseconds < $minimum
            || $inspection->durationMilliseconds > $maximum
        ) {
            throw new MediaLibraryRejected;
        }
    }

    /** @return array{string, string, string|null} */
    private function scanTransition(
        MediaInspection $inspection,
        MediaMalwareScan $malware,
    ): array {
        if (! $inspection->passed()) {
            return [
                'inspection_failed',
                'inspection.failed',
                $inspection->outcome === 'unavailable'
                    ? 'media_inspector_unavailable'
                    : 'media_inspection_failed',
            ];
        }

        if (! $malware->clean()) {
            return [
                'scan_failed',
                'scan.failed',
                match ($malware->outcome) {
                    'infected' => 'media_malware_detected',
                    'unavailable' => 'media_scanner_unavailable',
                    default => 'media_scan_failed',
                },
            ];
        }

        return ['scan_clean', 'scan.clean', null];
    }

    private function cleanScan(
        string $organizationId,
        string $uploadId,
        string $scanId,
        string $objectHash,
    ): stdClass {
        $scan = DB::table('media_security_scans')
            ->where('organization_id', $organizationId)
            ->where('media_upload_id', $uploadId)
            ->where('id', $scanId)
            ->where('object_hash', $objectHash)
            ->where('inspection_outcome', 'passed')
            ->where('malware_outcome', 'clean')
            ->lockForUpdate()
            ->first([
                'detected_mime_type',
                'byte_size',
                'object_hash',
            ]);

        if (! $scan instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        return $scan;
    }

    private function uploadEvent(
        string $organizationId,
        string $uploadId,
        string $eventType,
        int $uploadVersion,
        string $actorMembershipId,
        CarbonInterface $at,
        ?string $scanId = null,
        ?string $assetId = null,
        ?string $reasonKey = null,
    ): void {
        DB::table('media_upload_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'media_upload_id' => $uploadId,
            'event_type' => $eventType,
            'upload_version' => $uploadVersion,
            'media_security_scan_id' => $scanId,
            'media_asset_id' => $assetId,
            'actor_membership_id' => $actorMembershipId,
            'reason_key' => $reasonKey,
            'occurred_at' => $at,
            'created_at' => $at,
        ]);
    }

    private function assetEvent(
        string $organizationId,
        string $assetId,
        string $eventType,
        int $lifecycleVersion,
        string $actorMembershipId,
        CarbonInterface $at,
        ?string $scanId = null,
        ?string $relatedAssetId = null,
        ?string $uploadId = null,
        ?string $reasonKey = null,
    ): void {
        DB::table('media_asset_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'media_asset_id' => $assetId,
            'event_type' => $eventType,
            'lifecycle_version' => $lifecycleVersion,
            'selected_scan_id' => $scanId,
            'related_media_asset_id' => $relatedAssetId,
            'media_upload_id' => $uploadId,
            'actor_membership_id' => $actorMembershipId,
            'reason_key' => $reasonKey,
            'occurred_at' => $at,
            'created_at' => $at,
        ]);
    }

    /** @param list<string> $objectKeys */
    private function queueQuarantineCleanup(
        string $organizationId,
        string $uploadId,
        string $objectHash,
        array $objectKeys,
        string $reasonKey,
        CarbonInterface $at,
    ): void {
        foreach (array_values(array_unique($objectKeys)) as $objectKey) {
            if ($objectKey === '') {
                continue;
            }

            DB::table('media_object_deletion_requests')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'media_upload_id' => $uploadId,
                'storage_object_key' => $objectKey,
                'object_hash' => $objectHash,
                'reason_key' => $reasonKey,
                'state' => 'pending',
                'attempt_count' => 0,
                'last_error_key' => null,
                'due_at' => CarbonImmutable::instance($at)
                    ->addMinutes($this->cleanupTargetMinutes()),
                'last_attempted_at' => null,
                'completed_at' => null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function uploadSummary(
        string $organizationId,
        string $uploadId,
    ): array {
        $upload = DB::table('media_uploads')
            ->where('organization_id', $organizationId)
            ->where('id', $uploadId)
            ->first([
                'id',
                'candidate_asset_id',
                'asset_kind',
                'safe_label',
                'state',
                'version',
                'total_bytes',
                'whole_object_hash',
                'part_size_bytes',
                'expected_part_count',
                'received_part_count',
                'selected_scan_id',
                'accepted_asset_id',
                'expires_at',
                'review_expires_at',
                'decision_reason_key',
                'decided_at',
            ]);

        if (! $upload instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        return $this->row($upload);
    }

    /** @return array<string, mixed> */
    private function partSummary(
        string $organizationId,
        string $uploadId,
        string $partId,
    ): array {
        $part = DB::table('media_upload_parts')
            ->where('organization_id', $organizationId)
            ->where('media_upload_id', $uploadId)
            ->where('id', $partId)
            ->first([
                'id',
                'media_upload_id',
                'part_number',
                'byte_size',
                'part_hash',
                'received_at',
            ]);
        $upload = DB::table('media_uploads')
            ->where('organization_id', $organizationId)
            ->where('id', $uploadId)
            ->first(['version', 'received_part_count']);

        if (! $part instanceof stdClass || ! $upload instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        return [
            ...$this->row($part),
            'upload_version' => $this->integer($upload, 'version'),
            'received_part_count' => $this->integer(
                $upload,
                'received_part_count',
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function scanSummary(
        string $organizationId,
        string $uploadId,
        string $scanId,
    ): array {
        $scan = DB::table('media_security_scans')
            ->where('organization_id', $organizationId)
            ->where('media_upload_id', $uploadId)
            ->where('id', $scanId)
            ->first([
                'id',
                'media_upload_id',
                'object_hash',
                'byte_size',
                'inspection_outcome',
                'malware_outcome',
                'detected_mime_type',
                'duration_milliseconds',
                'inspector_name',
                'inspector_policy_version',
                'scanner_name',
                'scanner_engine_version',
                'scanner_policy_version',
                'technical_metadata_hash',
                'scanned_at',
            ]);
        $upload = DB::table('media_uploads')
            ->where('organization_id', $organizationId)
            ->where('id', $uploadId)
            ->first(['state', 'version', 'review_expires_at']);

        if (! $scan instanceof stdClass || ! $upload instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        return [
            ...$this->row($scan),
            'upload_state' => $this->string($upload, 'state'),
            'upload_version' => $this->integer($upload, 'version'),
            'review_expires_at' => $this->value(
                $upload->review_expires_at ?? null,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function row(stdClass $row): array
    {
        $result = [];

        foreach ((array) $row as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $result[$key] = $this->value($value);
        }

        return $result;
    }

    private function value(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface
            ? $value->format(DATE_ATOM)
            : $value;
    }

    /** @param array<string, string> $payload */
    private function recordEvidence(
        string $eventType,
        string $subjectType,
        string $subjectId,
        array $payload,
    ): void {
        $this->audit->recordTenant($eventType, $subjectType, $subjectId);
        $this->outbox->recordTenant(
            $eventType,
            $subjectType,
            $subjectId,
            $payload,
        );
    }

    private function maximumBytes(string $assetKind): int
    {
        if (! in_array($assetKind, MediaKindPolicy::SUPPORTED, true)) {
            throw new MediaLibraryRejected;
        }

        $maximum = config('media.kinds.'.$assetKind.'.maximum_bytes');

        if (! is_int($maximum) || $maximum < 1) {
            throw new MediaLibraryRejected;
        }

        return $maximum;
    }

    private function partSize(): int
    {
        $partSize = config('media.part_size_bytes');

        if (! is_int($partSize) || $partSize < 65_536 || $partSize > 5_000_000) {
            throw new MediaLibraryRejected;
        }

        return $partSize;
    }

    private function uploadExpiryHours(): int
    {
        $hours = config('media.upload_expiry_hours');

        return is_int($hours) && $hours > 0 ? $hours : 24;
    }

    private function cleanReviewExpiryDays(): int
    {
        $days = config('media.clean_review_expiry_days');

        return is_int($days) && $days > 0 ? $days : 7;
    }

    private function cleanupTargetMinutes(): int
    {
        $minutes = config('media.quarantine_cleanup_target_minutes');

        return is_int($minutes) && $minutes > 0 ? $minutes : 15;
    }

    private function safeLabel(string $value): string
    {
        $value = trim($value);

        if (
            $value === ''
            || mb_strlen($value) > 160
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
            || preg_match('/<[^>]+>/u', $value) === 1
            || preg_match('/(?:javascript|data|https?):/iu', $value) === 1
        ) {
            throw new MediaLibraryRejected;
        }

        return $value;
    }

    private function safeKey(string $value): string
    {
        if (preg_match('/\A[a-z][a-z0-9_.-]{0,99}\z/', $value) !== 1) {
            throw new MediaLibraryRejected;
        }

        return $value;
    }

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new MediaLibraryRejected;
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integer(stdClass $row, string $property): int
    {
        $value = $row->{$property} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new MediaLibraryRejected;
    }
}
