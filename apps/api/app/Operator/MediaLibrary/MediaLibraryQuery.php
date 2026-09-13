<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary;

use App\Media\MediaKindPolicy;
use App\Models\User;
use App\Operator\Exceptions\InvalidOperatorCursor;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use App\Operator\OperatorCursorCodec;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class MediaLibraryQuery
{
    private const int MAX_PAGE_SIZE = 25;

    public function __construct(
        private MediaLibraryAuthorization $authorization,
        private OperatorCursorCodec $cursors,
    ) {}

    /** @return array<string, mixed> */
    public function manifest(User $actor): array
    {
        $this->authorization->assertRead($actor);
        $context = $this->authorization->context($actor);
        $partSize = config('media.part_size_bytes');
        $audioLimit = config('media.kinds.model_audio.maximum_bytes');
        $lessonAudioLimit = config('media.kinds.lesson_audio.maximum_bytes');
        $videoLimit = config('media.kinds.lesson_video.maximum_bytes');
        $attestationReference = config(
            'media.rights_attestation.approval_reference',
        );
        $attestationHash = config('media.rights_attestation.evidence_sha256');

        if (
            ! is_int($partSize)
            || ! is_int($audioLimit)
            || ! is_int($lessonAudioLimit)
            || ! is_int($videoLimit)
            || ! is_string($attestationReference)
            || ! is_string($attestationHash)
        ) {
            throw new MediaLibraryRejected;
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'organization_id' => $context->organizationId,
            'allowed_actions' => $this->allowedActions($actor),
            'limits' => [
                'part_size_bytes' => $partSize,
                'model_audio_maximum_bytes' => $audioLimit,
                'lesson_audio_maximum_bytes' => $lessonAudioLimit,
                'lesson_video_maximum_bytes' => $videoLimit,
                'upload_expiry_hours' => 24,
                'clean_review_expiry_days' => 7,
            ],
            'supported_kinds' => MediaKindPolicy::SUPPORTED,
            'rights_attestation' => [
                'approval_reference' => $attestationReference,
                'evidence_sha256' => $attestationHash,
                'creates_asset_approval' => false,
            ],
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function assets(
        User $actor,
        int $limit,
        ?string $cursor,
        ?string $kind,
        ?string $state,
    ): array {
        $this->authorization->assertRead($actor);
        $organizationId = $this->authorization->context($actor)->organizationId;
        $limit = $this->limit($limit);
        $query = DB::table('media_asset_lifecycles')
            ->join('media_assets', function ($join): void {
                $join
                    ->on(
                        'media_assets.organization_id',
                        '=',
                        'media_asset_lifecycles.organization_id',
                    )
                    ->on(
                        'media_assets.id',
                        '=',
                        'media_asset_lifecycles.media_asset_id',
                    );
            })
            ->leftJoin('media_asset_metadata_heads', function ($join): void {
                $join
                    ->on(
                        'media_asset_metadata_heads.organization_id',
                        '=',
                        'media_asset_lifecycles.organization_id',
                    )
                    ->on(
                        'media_asset_metadata_heads.media_asset_id',
                        '=',
                        'media_asset_lifecycles.media_asset_id',
                    );
            })
            ->leftJoin('media_asset_metadata_revisions', function ($join): void {
                $join
                    ->on(
                        'media_asset_metadata_revisions.organization_id',
                        '=',
                        'media_asset_metadata_heads.organization_id',
                    )
                    ->on(
                        'media_asset_metadata_revisions.id',
                        '=',
                        'media_asset_metadata_heads.metadata_revision_id',
                    );
            })
            ->where('media_asset_lifecycles.organization_id', $organizationId)
            ->when(
                $kind !== null,
                fn (Builder $builder) => $builder->where(
                    'media_asset_lifecycles.asset_kind',
                    $kind,
                ),
            )
            ->when(
                $state !== null,
                fn (Builder $builder) => $builder->where(
                    'media_asset_lifecycles.state',
                    $state,
                ),
            );
        $this->applyCursor(
            $query,
            $cursor,
            'media-assets',
            $organizationId,
            'media_asset_lifecycles.updated_at',
            'media_asset_lifecycles.media_asset_id',
        );
        $rows = $query
            ->orderByDesc('media_asset_lifecycles.updated_at')
            ->orderByDesc('media_asset_lifecycles.media_asset_id')
            ->limit($limit + 1)
            ->get([
                'media_assets.id as media_asset_id',
                'media_assets.asset_hash',
                'media_assets.mime_type',
                'media_assets.byte_size',
                'media_assets.uploaded_at',
                'media_asset_lifecycles.asset_kind',
                'media_asset_lifecycles.state',
                'media_asset_lifecycles.version as lifecycle_version',
                'media_asset_lifecycles.selected_scan_id',
                'media_asset_lifecycles.predecessor_media_asset_id',
                'media_asset_lifecycles.updated_at',
                'media_asset_metadata_heads.metadata_revision_id',
                'media_asset_metadata_heads.version as metadata_version',
                'media_asset_metadata_revisions.operator_label',
                'media_asset_metadata_revisions.content_hash as metadata_content_hash',
            ]);
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();
        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                'media_asset_id' => $this->string($row, 'media_asset_id'),
                'asset_kind' => $this->string($row, 'asset_kind'),
                'asset_hash' => $this->string($row, 'asset_hash'),
                'mime_type' => $this->string($row, 'mime_type'),
                'byte_size' => $this->integer($row, 'byte_size'),
                'state' => $this->string($row, 'state'),
                'lifecycle_version' => $this->integer(
                    $row,
                    'lifecycle_version',
                ),
                'selected_scan_id' => $this->string($row, 'selected_scan_id'),
                'predecessor_media_asset_id' => $this->nullableString(
                    $row,
                    'predecessor_media_asset_id',
                ),
                'operator_label' => $this->nullableString(
                    $row,
                    'operator_label',
                ),
                'metadata_revision_id' => $this->nullableString(
                    $row,
                    'metadata_revision_id',
                ),
                'metadata_version' => $this->nullableInteger(
                    $row,
                    'metadata_version',
                ),
                'metadata_content_hash' => $this->nullableString(
                    $row,
                    'metadata_content_hash',
                ),
                'uploaded_at' => $this->timestamp($row->uploaded_at ?? null),
                'updated_at' => $this->timestamp($row->updated_at ?? null),
            ];
        }

        return [
            'data' => $data,
            'next_cursor' => $hasMore
                ? $this->nextCursor(
                    $rows,
                    'media-assets',
                    $organizationId,
                    'media_asset_id',
                )
                : null,
            'allowed_actions' => $this->allowedActions($actor),
        ];
    }

    /** @return array{data: array<string, mixed>, allowed_actions: list<string>} */
    public function asset(User $actor, string $assetId): array
    {
        $this->authorization->assertRead($actor);
        $organizationId = $this->authorization->context($actor)->organizationId;
        $asset = DB::table('media_assets')
            ->join('media_asset_lifecycles', function ($join): void {
                $join
                    ->on(
                        'media_asset_lifecycles.organization_id',
                        '=',
                        'media_assets.organization_id',
                    )
                    ->on(
                        'media_asset_lifecycles.media_asset_id',
                        '=',
                        'media_assets.id',
                    );
            })
            ->where('media_assets.organization_id', $organizationId)
            ->where('media_assets.id', $assetId)
            ->first([
                'media_assets.id',
                'media_assets.asset_kind',
                'media_assets.mime_type',
                'media_assets.byte_size',
                'media_assets.asset_hash',
                'media_assets.uploaded_at',
                'media_asset_lifecycles.state',
                'media_asset_lifecycles.version as lifecycle_version',
                'media_asset_lifecycles.selected_scan_id',
                'media_asset_lifecycles.predecessor_media_asset_id',
                'media_asset_lifecycles.managed_at',
                'media_asset_lifecycles.retired_at',
                'media_asset_lifecycles.retirement_reason_key',
            ]);

        if (! $asset instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        $scan = DB::table('media_security_scans')
            ->where('organization_id', $organizationId)
            ->where('id', $this->string($asset, 'selected_scan_id'))
            ->first([
                'id',
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
        $metadata = DB::table('media_asset_metadata_heads')
            ->join('media_asset_metadata_revisions', function ($join): void {
                $join
                    ->on(
                        'media_asset_metadata_revisions.organization_id',
                        '=',
                        'media_asset_metadata_heads.organization_id',
                    )
                    ->on(
                        'media_asset_metadata_revisions.id',
                        '=',
                        'media_asset_metadata_heads.metadata_revision_id',
                    );
            })
            ->where('media_asset_metadata_heads.organization_id', $organizationId)
            ->where('media_asset_metadata_heads.media_asset_id', $assetId)
            ->first([
                'media_asset_metadata_heads.version as metadata_version',
                'media_asset_metadata_revisions.id',
                'media_asset_metadata_revisions.revision_number',
                'media_asset_metadata_revisions.operator_label',
                'media_asset_metadata_revisions.transcript',
                'media_asset_metadata_revisions.text_alternative',
                'media_asset_metadata_revisions.content_hash',
                'media_asset_metadata_revisions.authored_at',
            ]);
        $variant = $metadata instanceof stdClass
            ? $this->variant($organizationId, $this->string($metadata, 'id'))
            : null;
        $reviews = DB::table('media_asset_metadata_reviews')
            ->where('organization_id', $organizationId)
            ->where('media_asset_id', $assetId)
            ->orderBy('reviewed_at')
            ->orderBy('id')
            ->get([
                'id',
                'program_id',
                'metadata_revision_id',
                'review_kind',
                'metadata_variant_id',
                'decision',
                'rationale_key',
                'reviewer_membership_id',
                'authority_appointment_id',
                'reviewed_at',
            ]);
        $rights = DB::table('media_rights_revisions')
            ->where('organization_id', $organizationId)
            ->where('media_asset_id', $assetId)
            ->orderBy('program_id')
            ->orderBy('revision_number')
            ->get([
                'id',
                'program_id',
                'parent_revision_id',
                'revision_number',
                'evidence_status',
                'evidence_hash',
                'rights_holder_reference',
                'provenance_reference',
                'rights_basis_key',
                'license_or_contract_reference',
                'permitted_release_channels',
                'permitted_territories',
                'learner_delivery_allowed',
                'sharing_allowed',
                'derivative_use_allowed',
                'effective_at',
                'expires_at',
                'retention_policy_key',
                'deletion_constraint_key',
                'confirmed_at',
            ]);
        $usages = DB::table('content_revision_media_assets')
            ->where('organization_id', $organizationId)
            ->where('media_asset_id', $assetId)
            ->orderBy('curriculum_version_id')
            ->orderBy('content_revision_id')
            ->get([
                'curriculum_version_id',
                'content_revision_id',
                'usage_key',
                'sequence',
                'created_at',
            ]);
        $events = DB::table('media_asset_events')
            ->where('organization_id', $organizationId)
            ->where('media_asset_id', $assetId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get([
                'id',
                'event_type',
                'lifecycle_version',
                'selected_scan_id',
                'related_media_asset_id',
                'media_upload_id',
                'actor_membership_id',
                'reason_key',
                'occurred_at',
            ]);

        return [
            'data' => [
                'media_asset_id' => $assetId,
                'asset_kind' => $this->string($asset, 'asset_kind'),
                'mime_type' => $this->string($asset, 'mime_type'),
                'byte_size' => $this->integer($asset, 'byte_size'),
                'asset_hash' => $this->string($asset, 'asset_hash'),
                'uploaded_at' => $this->timestamp($asset->uploaded_at ?? null),
                'lifecycle' => [
                    'state' => $this->string($asset, 'state'),
                    'version' => $this->integer($asset, 'lifecycle_version'),
                    'selected_scan_id' => $this->string(
                        $asset,
                        'selected_scan_id',
                    ),
                    'predecessor_media_asset_id' => $this->nullableString(
                        $asset,
                        'predecessor_media_asset_id',
                    ),
                    'managed_at' => $this->timestamp(
                        $asset->managed_at ?? null,
                    ),
                    'retired_at' => $this->nullableTimestamp(
                        $asset->retired_at ?? null,
                    ),
                    'retirement_reason_key' => $this->nullableString(
                        $asset,
                        'retirement_reason_key',
                    ),
                ],
                'scan' => $scan instanceof stdClass
                    ? $this->safeRow($scan)
                    : null,
                'metadata' => $metadata instanceof stdClass
                    ? $this->safeRow($metadata)
                    : null,
                'kannada_variant' => $variant instanceof stdClass
                    ? $this->safeRow($variant)
                    : null,
                'metadata_reviews' => $this->safeRows($reviews),
                'rights_revisions' => $this->safeRows($rights),
                'usages' => $this->safeRows($usages),
                'events' => $this->safeRows($events),
            ],
            'allowed_actions' => $this->allowedActions($actor),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function uploads(
        User $actor,
        int $limit,
        ?string $cursor,
        ?string $state,
    ): array {
        $this->authorization->assertRead($actor);
        $organizationId = $this->authorization->context($actor)->organizationId;
        $limit = $this->limit($limit);
        $query = DB::table('media_uploads')
            ->where('organization_id', $organizationId)
            ->when(
                $state !== null,
                fn (Builder $builder) => $builder->where('state', $state),
            );
        $this->applyCursor(
            $query,
            $cursor,
            'media-uploads',
            $organizationId,
            'updated_at',
            'id',
        );
        $rows = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get([
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
                'updated_at',
            ]);
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();

        return [
            'data' => $this->safeRows($rows),
            'next_cursor' => $hasMore
                ? $this->nextCursor(
                    $rows,
                    'media-uploads',
                    $organizationId,
                    'id',
                )
                : null,
            'allowed_actions' => $this->allowedActions($actor),
        ];
    }

    /** @return array{data: array<string, mixed>, allowed_actions: list<string>} */
    public function upload(User $actor, string $uploadId): array
    {
        $this->authorization->assertRead($actor);
        $organizationId = $this->authorization->context($actor)->organizationId;
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
                'created_by_membership_id',
                'decided_by_membership_id',
                'decision_reason_key',
                'expires_at',
                'review_expires_at',
                'decided_at',
                'created_at',
                'updated_at',
            ]);

        if (! $upload instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        $parts = DB::table('media_upload_parts')
            ->where('organization_id', $organizationId)
            ->where('media_upload_id', $uploadId)
            ->orderBy('part_number')
            ->get([
                'id',
                'part_number',
                'byte_size',
                'part_hash',
                'received_by_membership_id',
                'received_at',
            ]);
        $scans = DB::table('media_security_scans')
            ->where('organization_id', $organizationId)
            ->where('media_upload_id', $uploadId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id',
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
        $events = DB::table('media_upload_events')
            ->where('organization_id', $organizationId)
            ->where('media_upload_id', $uploadId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get([
                'id',
                'event_type',
                'upload_version',
                'media_security_scan_id',
                'media_asset_id',
                'actor_membership_id',
                'reason_key',
                'occurred_at',
            ]);

        return [
            'data' => [
                ...$this->safeRow($upload),
                'parts' => $this->safeRows($parts),
                'scans' => $this->safeRows($scans),
                'events' => $this->safeRows($events),
            ],
            'allowed_actions' => $this->allowedActions($actor),
        ];
    }

    /** @return list<string> */
    private function allowedActions(User $actor): array
    {
        $actions = [];
        $mapping = [
            'media.upload' => 'start_upload',
            'media.quarantine.review' => 'review_quarantine',
            'media.metadata.manage' => 'manage_metadata',
            'media.replace' => 'record_replacement',
            'media.retire' => 'retire_asset',
        ];

        foreach ($mapping as $permission => $action) {
            if ($this->authorization->allows($actor, $permission)) {
                $actions[] = $action;
            }
        }

        if ($this->authorization->allows($actor, 'media.rights.approve')) {
            $actions[] = 'record_rights_decision';
        }

        if ($this->authorization->allows($actor, 'media.upload')) {
            $actions[] = 'adopt_asset';
        }

        if (
            $this->authorization->allows($actor, 'media.metadata.manage')
            && $this->authorization->allows($actor, 'curriculum.translate.kn')
        ) {
            $actions[] = 'translate_metadata';
        }

        if ($this->authorization->allows($actor, 'curriculum.review.en')) {
            $actions[] = 'review_metadata_en';
        }

        if ($this->authorization->allows($actor, 'curriculum.review.kn')) {
            $actions[] = 'review_metadata_kn';
        }

        if (
            $this->authorization->allows($actor, 'media.metadata.manage')
            && $this->authorization->allows($actor, 'curriculum.author')
        ) {
            $actions[] = 'attach_asset';
        }

        return $actions;
    }

    private function variant(
        string $organizationId,
        string $metadataRevisionId,
    ): ?stdClass {
        return DB::table('media_asset_metadata_variant_heads')
            ->join('media_asset_metadata_variants', function ($join): void {
                $join
                    ->on(
                        'media_asset_metadata_variants.organization_id',
                        '=',
                        'media_asset_metadata_variant_heads.organization_id',
                    )
                    ->on(
                        'media_asset_metadata_variants.id',
                        '=',
                        'media_asset_metadata_variant_heads.metadata_variant_id',
                    );
            })
            ->where(
                'media_asset_metadata_variant_heads.organization_id',
                $organizationId,
            )
            ->where(
                'media_asset_metadata_variant_heads.metadata_revision_id',
                $metadataRevisionId,
            )
            ->first([
                'media_asset_metadata_variant_heads.version as variant_version',
                'media_asset_metadata_variants.id',
                'media_asset_metadata_variants.revision_number',
                'media_asset_metadata_variants.locale',
                'media_asset_metadata_variants.source_content_hash',
                'media_asset_metadata_variants.transcript',
                'media_asset_metadata_variants.text_alternative',
                'media_asset_metadata_variants.content_hash',
                'media_asset_metadata_variants.translated_at',
            ]);
    }

    private function limit(int $limit): int
    {
        return max(1, min(self::MAX_PAGE_SIZE, $limit));
    }

    private function applyCursor(
        Builder $query,
        ?string $cursor,
        string $purpose,
        string $organizationId,
        string $timestampColumn,
        string $idColumn,
    ): void {
        if ($cursor === null) {
            return;
        }

        $position = $this->cursors->decode(
            $cursor,
            $purpose,
            $organizationId,
        );
        $timestamp = $position['updated_at'] ?? null;
        $id = $position['id'] ?? null;

        if (! is_string($timestamp) || ! is_string($id)) {
            throw new InvalidOperatorCursor;
        }

        $query->where(function (Builder $builder) use (
            $timestampColumn,
            $idColumn,
            $timestamp,
            $id,
        ): void {
            $builder
                ->where($timestampColumn, '<', $timestamp)
                ->orWhere(function (Builder $builder) use (
                    $timestampColumn,
                    $idColumn,
                    $timestamp,
                    $id,
                ): void {
                    $builder
                        ->where($timestampColumn, '=', $timestamp)
                        ->where($idColumn, '<', $id);
                });
        });
    }

    /** @param Collection<int, mixed> $rows */
    private function nextCursor(
        Collection $rows,
        string $purpose,
        string $organizationId,
        string $idProperty,
    ): ?string {
        $last = $rows->last();

        if (! $last instanceof stdClass) {
            return null;
        }

        return $this->cursors->encode(
            $purpose,
            $organizationId,
            [
                'updated_at' => $this->timestamp($last->updated_at ?? null),
                'id' => $this->string($last, $idProperty),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function safeRow(stdClass $row): array
    {
        $result = [];

        foreach ((array) $row as $key => $value) {
            if (
                ! is_string($key)
                || str_contains($key, 'storage_object_key')
                || str_contains($key, 'quarantine_object_key')
                || str_contains($key, 'accepted_object_key')
            ) {
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $result[$key] = $value->format(DATE_ATOM);
            } elseif (is_bool($value)) {
                $result[$key] = $value;
            } elseif (is_int($value) || is_float($value) || $value === null) {
                $result[$key] = $value;
            } elseif (is_string($value)) {
                $result[$key] = $this->decodeJson($value);
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function safeRows(Collection $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            if ($row instanceof stdClass) {
                $result[] = $this->safeRow($row);
            }
        }

        return $result;
    }

    private function decodeJson(string $value): mixed
    {
        if (! str_starts_with($value, '[') && ! str_starts_with($value, '{')) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
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

    private function nullableInteger(stdClass $row, string $property): ?int
    {
        $value = $row->{$property} ?? null;

        if ($value === null) {
            return null;
        }

        return $this->integer($row, $property);
    }

    private function timestamp(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw new MediaLibraryRejected;
    }

    private function nullableTimestamp(mixed $value): ?string
    {
        return $value === null ? null : $this->timestamp($value);
    }
}
