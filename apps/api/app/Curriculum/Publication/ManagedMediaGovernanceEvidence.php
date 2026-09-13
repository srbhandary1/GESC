<?php

declare(strict_types=1);

namespace App\Curriculum\Publication;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class ManagedMediaGovernanceEvidence
{
    /**
     * @return array<string, mixed>
     */
    public function selectCurrent(
        string $organizationId,
        string $programId,
        string $curriculumVersionId,
        string $sourceRevisionId,
        string $mediaAssetId,
        string $assetHash,
        string $usageKey,
        string $rightsRevisionId,
        string $rightsEvidenceHash,
        CarbonInterface $evaluatedAt,
    ): array {
        $evidence = $this->loadImmutableEvidence(
            organizationId: $organizationId,
            programId: $programId,
            curriculumVersionId: $curriculumVersionId,
            sourceRevisionId: $sourceRevisionId,
            mediaAssetId: $mediaAssetId,
            assetHash: $assetHash,
            usageKey: $usageKey,
            rightsRevisionId: $rightsRevisionId,
            rightsEvidenceHash: $rightsEvidenceHash,
            expected: null,
            evaluatedAt: $evaluatedAt,
            validateCurrentAuthority: true,
        );
        $this->assertCurrentSelection(
            organizationId: $organizationId,
            programId: $programId,
            mediaAssetId: $mediaAssetId,
            evidence: $evidence,
            evaluatedAt: $evaluatedAt,
        );

        return $evidence;
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    public function assertSnapshot(
        string $organizationId,
        string $programId,
        string $curriculumVersionId,
        string $sourceRevisionId,
        string $mediaAssetId,
        string $assetHash,
        string $usageKey,
        string $rightsRevisionId,
        string $rightsEvidenceHash,
        array $expected,
        CarbonInterface $evaluatedAt,
        bool $requireCurrentSelection,
        bool $validateCurrentAuthority,
    ): void {
        $actual = $this->loadImmutableEvidence(
            organizationId: $organizationId,
            programId: $programId,
            curriculumVersionId: $curriculumVersionId,
            sourceRevisionId: $sourceRevisionId,
            mediaAssetId: $mediaAssetId,
            assetHash: $assetHash,
            usageKey: $usageKey,
            rightsRevisionId: $rightsRevisionId,
            rightsEvidenceHash: $rightsEvidenceHash,
            expected: $expected,
            evaluatedAt: $evaluatedAt,
            validateCurrentAuthority: $validateCurrentAuthority,
        );

        if (! hash_equals(
            CanonicalJson::sha256($actual),
            CanonicalJson::sha256($expected),
        )) {
            throw new CurriculumOperationRejected;
        }

        if ($requireCurrentSelection) {
            $this->assertCurrentSelection(
                organizationId: $organizationId,
                programId: $programId,
                mediaAssetId: $mediaAssetId,
                evidence: $actual,
                evaluatedAt: $evaluatedAt,
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $expected
     * @return array<string, mixed>
     */
    private function loadImmutableEvidence(
        string $organizationId,
        string $programId,
        string $curriculumVersionId,
        string $sourceRevisionId,
        string $mediaAssetId,
        string $assetHash,
        string $usageKey,
        string $rightsRevisionId,
        string $rightsEvidenceHash,
        ?array $expected,
        CarbonInterface $evaluatedAt,
        bool $validateCurrentAuthority,
    ): array {
        $attachment = DB::table('media_attachment_events')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('source_revision_id', $sourceRevisionId)
            ->where('media_asset_id', $mediaAssetId)
            ->where('usage_key', $usageKey)
            ->first([
                'selected_scan_id',
                'metadata_revision_id',
                'english_review_id',
                'metadata_variant_id',
                'kannada_review_id',
                'media_rights_revision_id',
                'attached_at',
            ]);

        if (
            ! $attachment instanceof stdClass
            || $this->date($attachment, 'attached_at')->greaterThan($evaluatedAt)
            || $this->string($attachment, 'media_rights_revision_id')
                !== $rightsRevisionId
            || ! DB::table('content_revision_media_assets')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->where('content_revision_id', $sourceRevisionId)
                ->where('media_asset_id', $mediaAssetId)
                ->where('usage_key', $usageKey)
                ->exists()
        ) {
            throw new CurriculumOperationRejected;
        }

        $asset = DB::table('media_assets')
            ->where('organization_id', $organizationId)
            ->where('id', $mediaAssetId)
            ->where('asset_kind', $usageKey)
            ->where('asset_hash', $assetHash)
            ->first(['byte_size']);

        if (! $asset instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $scanId = $this->string($attachment, 'selected_scan_id');
        $scan = DB::table('media_security_scans')
            ->where('organization_id', $organizationId)
            ->where('id', $scanId)
            ->where('asset_kind', $usageKey)
            ->where('object_hash', $assetHash)
            ->where('inspection_outcome', 'passed')
            ->where('malware_outcome', 'clean')
            ->first([
                'id',
                'media_upload_id',
                'media_asset_id',
                'asset_kind',
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

        if (
            ! $scan instanceof stdClass
            || $this->integer($scan, 'byte_size')
                !== $this->integer($asset, 'byte_size')
            || $this->date($scan, 'scanned_at')->greaterThan($evaluatedAt)
            || ! $this->scanBelongsToAsset(
                $organizationId,
                $mediaAssetId,
                $scan,
            )
            || ! hash_equals(
                $this->sha256($scan, 'technical_metadata_hash'),
                CanonicalJson::sha256([
                    'asset_kind' => $this->string($scan, 'asset_kind'),
                    'byte_size' => $this->integer($scan, 'byte_size'),
                    'detected_mime_type' => $this->nullableString(
                        $scan,
                        'detected_mime_type',
                    ),
                    'duration_milliseconds' => $this->nullableInteger(
                        $scan,
                        'duration_milliseconds',
                    ),
                    'inspection_outcome' => $this->string(
                        $scan,
                        'inspection_outcome',
                    ),
                    'inspector_name' => $this->string(
                        $scan,
                        'inspector_name',
                    ),
                    'inspector_policy_version' => $this->string(
                        $scan,
                        'inspector_policy_version',
                    ),
                    'malware_outcome' => $this->string(
                        $scan,
                        'malware_outcome',
                    ),
                    'object_hash' => $this->sha256($scan, 'object_hash'),
                    'scanner_engine_version' => $this->nullableString(
                        $scan,
                        'scanner_engine_version',
                    ),
                    'scanner_name' => $this->string($scan, 'scanner_name'),
                    'scanner_policy_version' => $this->string(
                        $scan,
                        'scanner_policy_version',
                    ),
                ]),
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $metadataRevisionId = $this->string(
            $attachment,
            'metadata_revision_id',
        );
        $metadata = DB::table('media_asset_metadata_revisions')
            ->where('organization_id', $organizationId)
            ->where('media_asset_id', $mediaAssetId)
            ->where('id', $metadataRevisionId)
            ->where('asset_kind', $usageKey)
            ->first([
                'id',
                'asset_kind',
                'operator_label',
                'transcript',
                'text_alternative',
                'content_hash',
                'authored_by_membership_id',
                'authored_at',
            ]);

        if (
            ! $metadata instanceof stdClass
            || $this->date($metadata, 'authored_at')->greaterThan($evaluatedAt)
            || ! hash_equals(
                $this->sha256($metadata, 'content_hash'),
                CanonicalJson::sha256([
                    'asset_kind' => $this->string($metadata, 'asset_kind'),
                    'locale' => 'en-IN',
                    'operator_label' => $this->string(
                        $metadata,
                        'operator_label',
                    ),
                    'text_alternative' => $this->nullableString(
                        $metadata,
                        'text_alternative',
                    ),
                    'transcript' => $this->string($metadata, 'transcript'),
                ]),
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $variantId = $this->string($attachment, 'metadata_variant_id');
        $variant = DB::table('media_asset_metadata_variants')
            ->where('organization_id', $organizationId)
            ->where('media_asset_id', $mediaAssetId)
            ->where('metadata_revision_id', $metadataRevisionId)
            ->where('id', $variantId)
            ->where('asset_kind', $usageKey)
            ->where('locale', 'kn-IN')
            ->where('source_content_hash', $this->sha256($metadata, 'content_hash'))
            ->first([
                'id',
                'asset_kind',
                'source_content_hash',
                'transcript',
                'text_alternative',
                'content_hash',
                'translated_by_membership_id',
                'translated_at',
            ]);

        if (
            ! $variant instanceof stdClass
            || $this->date($variant, 'translated_at')->greaterThan($evaluatedAt)
            || ! hash_equals(
                $this->sha256($variant, 'content_hash'),
                CanonicalJson::sha256([
                    'asset_kind' => $this->string($variant, 'asset_kind'),
                    'locale' => 'kn-IN',
                    'source_content_hash' => $this->sha256(
                        $variant,
                        'source_content_hash',
                    ),
                    'text_alternative' => $this->nullableString(
                        $variant,
                        'text_alternative',
                    ),
                    'transcript' => $this->string($variant, 'transcript'),
                ]),
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $englishReview = $this->approvedReview(
            organizationId: $organizationId,
            programId: $programId,
            metadataRevisionId: $metadataRevisionId,
            sourceHash: $this->sha256($metadata, 'content_hash'),
            reviewKind: 'academic_en',
            variantId: null,
            variantHash: null,
            reviewId: $this->string($attachment, 'english_review_id'),
            sourceAuthors: [
                $this->string($metadata, 'authored_by_membership_id'),
                $this->string($variant, 'translated_by_membership_id'),
            ],
            evaluatedAt: $evaluatedAt,
            validateCurrentAuthority: $validateCurrentAuthority,
        );
        $kannadaReview = $this->approvedReview(
            organizationId: $organizationId,
            programId: $programId,
            metadataRevisionId: $metadataRevisionId,
            sourceHash: $this->sha256($metadata, 'content_hash'),
            reviewKind: 'kannada',
            variantId: $variantId,
            variantHash: $this->sha256($variant, 'content_hash'),
            reviewId: $this->string($attachment, 'kannada_review_id'),
            sourceAuthors: [
                $this->string($metadata, 'authored_by_membership_id'),
                $this->string($variant, 'translated_by_membership_id'),
            ],
            evaluatedAt: $evaluatedAt,
            validateCurrentAuthority: $validateCurrentAuthority,
        );
        $rights = DB::table('media_rights_revisions')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('media_asset_id', $mediaAssetId)
            ->where('id', $rightsRevisionId)
            ->where('evidence_hash', $rightsEvidenceHash)
            ->where('selected_asset_hash', $assetHash)
            ->where('evidence_status', 'approved')
            ->first(['id']);

        if (! $rights instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $result = [
            'selected_scan' => [
                'id' => $scanId,
                'object_hash' => $assetHash,
                'inspector_policy_version' => $this->string(
                    $scan,
                    'inspector_policy_version',
                ),
                'scanner_policy_version' => $this->string(
                    $scan,
                    'scanner_policy_version',
                ),
            ],
            'metadata' => [
                'revision_id' => $metadataRevisionId,
                'content_hash' => $this->sha256($metadata, 'content_hash'),
                'english_review' => $this->reviewReference($englishReview),
                'kannada_variant' => [
                    'id' => $variantId,
                    'content_hash' => $this->sha256($variant, 'content_hash'),
                    'review' => $this->reviewReference($kannadaReview),
                ],
            ],
            'rights' => [
                'revision_id' => $rightsRevisionId,
                'evidence_hash' => $rightsEvidenceHash,
            ],
        ];

        if ($expected !== null && ! $this->hasExactShape($expected)) {
            throw new CurriculumOperationRejected;
        }

        return $result;
    }

    /** @param array<string, mixed> $evidence */
    private function assertCurrentSelection(
        string $organizationId,
        string $programId,
        string $mediaAssetId,
        array $evidence,
        CarbonInterface $evaluatedAt,
    ): void {
        $scan = $this->array($evidence, 'selected_scan');
        $metadata = $this->array($evidence, 'metadata');
        $variant = $this->array($metadata, 'kannada_variant');
        $englishReview = $this->array($metadata, 'english_review');
        $kannadaReview = $this->array($variant, 'review');
        $rights = $this->array($evidence, 'rights');
        $metadataRevisionId = $this->arrayString($metadata, 'revision_id');
        $variantId = $this->arrayString($variant, 'id');

        $currentLifecycle = DB::table('media_asset_lifecycles')
            ->where('organization_id', $organizationId)
            ->where('media_asset_id', $mediaAssetId)
            ->where('state', 'active')
            ->where('selected_scan_id', $this->arrayString($scan, 'id'))
            ->lockForUpdate()
            ->first(['media_asset_id']);

        if (
            ! $currentLifecycle instanceof stdClass
            || ! DB::table('media_asset_metadata_heads')
                ->where('organization_id', $organizationId)
                ->where('media_asset_id', $mediaAssetId)
                ->where('metadata_revision_id', $metadataRevisionId)
                ->exists()
            || ! DB::table('media_asset_metadata_variant_heads')
                ->where('organization_id', $organizationId)
                ->where('metadata_revision_id', $metadataRevisionId)
                ->where('metadata_variant_id', $variantId)
                ->exists()
            || ! $this->reviewIsLatest(
                $organizationId,
                $programId,
                $metadataRevisionId,
                'academic_en',
                null,
                $this->arrayString($englishReview, 'id'),
                $evaluatedAt,
            )
            || ! $this->reviewIsLatest(
                $organizationId,
                $programId,
                $metadataRevisionId,
                'kannada',
                $variantId,
                $this->arrayString($kannadaReview, 'id'),
                $evaluatedAt,
            )
            || ! DB::table('media_rights_revisions')
                ->where('organization_id', $organizationId)
                ->where('program_id', $programId)
                ->where('media_asset_id', $mediaAssetId)
                ->where('id', $this->arrayString($rights, 'revision_id'))
                ->whereNotExists(function ($query) use (
                    $organizationId,
                    $programId,
                    $mediaAssetId,
                    $evaluatedAt,
                ): void {
                    $query
                        ->select(DB::raw('1'))
                        ->from('media_rights_revisions as later_rights')
                        ->where('later_rights.organization_id', $organizationId)
                        ->where('later_rights.program_id', $programId)
                        ->where('later_rights.media_asset_id', $mediaAssetId)
                        ->where('later_rights.effective_at', '<=', $evaluatedAt)
                        ->whereRaw(
                            'later_rights.revision_number > media_rights_revisions.revision_number',
                        );
                })
                ->exists()
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    /**
     * @param  list<string>  $sourceAuthors
     */
    private function approvedReview(
        string $organizationId,
        string $programId,
        string $metadataRevisionId,
        string $sourceHash,
        string $reviewKind,
        ?string $variantId,
        ?string $variantHash,
        string $reviewId,
        array $sourceAuthors,
        CarbonInterface $evaluatedAt,
        bool $validateCurrentAuthority,
    ): stdClass {
        $query = DB::table('media_asset_metadata_reviews')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('metadata_revision_id', $metadataRevisionId)
            ->where('source_content_hash', $sourceHash)
            ->where('review_kind', $reviewKind)
            ->where('id', $reviewId)
            ->where('decision', 'approved');

        if ($variantId === null) {
            $query->whereNull('metadata_variant_id')
                ->whereNull('variant_content_hash');
        } else {
            $query->where('metadata_variant_id', $variantId)
                ->where('variant_content_hash', $variantHash);
        }

        $review = $query->first([
            'id',
            'authority_appointment_id',
            'authority_appointment_event_id',
            'reviewer_membership_id',
            'rationale_key',
            'reviewed_at',
        ]);

        if (
            ! $review instanceof stdClass
            || $this->date($review, 'reviewed_at')->greaterThan($evaluatedAt)
        ) {
            throw new CurriculumOperationRejected;
        }

        $reviewer = $this->string($review, 'reviewer_membership_id');

        foreach (array_values(array_unique($sourceAuthors)) as $author) {
            $this->assertDistinctHumans($organizationId, $author, $reviewer);
        }

        $this->assertAppointmentEvidence(
            organizationId: $organizationId,
            programId: $programId,
            appointmentId: $this->string(
                $review,
                'authority_appointment_id',
            ),
            appointmentEventId: $this->string(
                $review,
                'authority_appointment_event_id',
            ),
            appointeeMembershipId: $reviewer,
            authorityKind: $reviewKind === 'academic_en'
                ? 'english_academic_reviewer'
                : 'kannada_reviewer',
            evidenceTime: $this->date($review, 'reviewed_at'),
            evaluatedAt: $evaluatedAt,
            validateCurrentAuthority: $validateCurrentAuthority,
        );

        return $review;
    }

    private function assertAppointmentEvidence(
        string $organizationId,
        string $programId,
        string $appointmentId,
        string $appointmentEventId,
        string $appointeeMembershipId,
        string $authorityKind,
        CarbonInterface $evidenceTime,
        CarbonInterface $evaluatedAt,
        bool $validateCurrentAuthority,
    ): void {
        $appointment = DB::table('authority_appointments')
            ->where('organization_id', $organizationId)
            ->where('id', $appointmentId)
            ->where('appointee_membership_id', $appointeeMembershipId)
            ->where('authority_kind', $authorityKind)
            ->first(['scope_type', 'program_id', 'valid_from', 'valid_until']);

        if (! $appointment instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        $scope = $this->string($appointment, 'scope_type');
        $scopeValid = ($scope === 'organization'
                && $this->nullableString($appointment, 'program_id') === null)
            || ($scope === 'program'
                && $this->nullableString($appointment, 'program_id') === $programId);
        $validUntil = $this->nullableDate($appointment, 'valid_until');
        $accepted = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where('id', $appointmentEventId)
            ->where('event_type', 'accepted')
            ->first(['effective_at']);
        $terminatedAtEvidence = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->whereIn(
                'event_type',
                ['surrendered', 'revoked', 'expired', 'revoked_for_cause'],
            )
            ->where('effective_at', '<=', $evidenceTime)
            ->exists();
        $invalidated = $validateCurrentAuthority
            && DB::table('authority_appointment_events')
                ->where('organization_id', $organizationId)
                ->where('authority_appointment_id', $appointmentId)
                ->where('event_type', 'revoked_for_cause')
                ->where('invalidates_prior_approvals', true)
                ->where('effective_at', '<=', $evaluatedAt)
                ->exists();

        if (
            ! $scopeValid
            || $evidenceTime->lessThan($this->date($appointment, 'valid_from'))
            || ($validUntil instanceof CarbonInterface
                && $evidenceTime->greaterThanOrEqualTo($validUntil))
            || ! $accepted instanceof stdClass
            || $this->date($accepted, 'effective_at')->greaterThan($evidenceTime)
            || $terminatedAtEvidence
            || $invalidated
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function assertDistinctHumans(
        string $organizationId,
        string $authorMembershipId,
        string $reviewerMembershipId,
    ): void {
        $userIds = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->whereIn('id', [$authorMembershipId, $reviewerMembershipId])
            ->pluck('user_id', 'id');
        $author = $userIds->get($authorMembershipId);
        $reviewer = $userIds->get($reviewerMembershipId);

        if (
            ! is_string($author)
            || ! is_string($reviewer)
            || hash_equals($author, $reviewer)
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function scanBelongsToAsset(
        string $organizationId,
        string $mediaAssetId,
        stdClass $scan,
    ): bool {
        $scannedAsset = $this->nullableString($scan, 'media_asset_id');

        if ($scannedAsset !== null) {
            return hash_equals($scannedAsset, $mediaAssetId);
        }

        $uploadId = $this->nullableString($scan, 'media_upload_id');

        return $uploadId !== null
            && DB::table('media_uploads')
                ->where('organization_id', $organizationId)
                ->where('id', $uploadId)
                ->where('accepted_asset_id', $mediaAssetId)
                ->where('state', 'accepted')
                ->exists();
    }

    private function reviewIsLatest(
        string $organizationId,
        string $programId,
        string $metadataRevisionId,
        string $reviewKind,
        ?string $variantId,
        string $reviewId,
        CarbonInterface $evaluatedAt,
    ): bool {
        $query = DB::table('media_asset_metadata_reviews')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('metadata_revision_id', $metadataRevisionId)
            ->where('review_kind', $reviewKind)
            ->where('reviewed_at', '<=', $evaluatedAt);

        if ($variantId === null) {
            $query->whereNull('metadata_variant_id');
        } else {
            $query->where('metadata_variant_id', $variantId);
        }

        $latest = $query
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first(['id', 'decision']);

        return $latest instanceof stdClass
            && $this->string($latest, 'id') === $reviewId
            && $this->string($latest, 'decision') === 'approved';
    }

    /** @return array<string, string|null> */
    private function reviewReference(stdClass $review): array
    {
        return [
            'id' => $this->string($review, 'id'),
            'appointment_id' => $this->string(
                $review,
                'authority_appointment_id',
            ),
            'appointment_event_id' => $this->string(
                $review,
                'authority_appointment_event_id',
            ),
            'reviewer_membership_id' => $this->string(
                $review,
                'reviewer_membership_id',
            ),
            'rationale_key' => $this->nullableString($review, 'rationale_key'),
        ];
    }

    /** @param array<string, mixed> $value */
    private function hasExactShape(array $value): bool
    {
        return $this->hasExactKeys(
            $value,
            ['selected_scan', 'metadata', 'rights'],
        )
            && $this->hasExactKeys($this->array($value, 'selected_scan'), [
                'id',
                'object_hash',
                'inspector_policy_version',
                'scanner_policy_version',
            ])
            && $this->hasExactKeys($this->array($value, 'metadata'), [
                'revision_id',
                'content_hash',
                'english_review',
                'kannada_variant',
            ])
            && $this->hasExactKeys($this->array($value, 'rights'), [
                'revision_id',
                'evidence_hash',
            ]);
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $expected
     */
    private function hasExactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff(array_keys($value), $expected) === [];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function array(array $value, string $field): array
    {
        $nested = $value[$field] ?? null;

        if (! is_array($nested) || array_is_list($nested)) {
            throw new CurriculumOperationRejected;
        }

        return $nested;
    }

    /** @param array<string, mixed> $value */
    private function arrayString(array $value, string $field): string
    {
        $item = $value[$field] ?? null;

        if (! is_string($item) || $item === '') {
            throw new CurriculumOperationRejected;
        }

        return $item;
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new CurriculumOperationRejected;
    }

    private function nullableInteger(stdClass $row, string $field): ?int
    {
        return ($row->{$field} ?? null) === null
            ? null
            : $this->integer($row, $field);
    }

    private function sha256(stdClass $row, string $field): string
    {
        $value = $this->string($row, $field);

        if (preg_match('/\A[0-9a-f]{64}\z/', $value) !== 1) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function date(stdClass $row, string $field): CarbonImmutable
    {
        $value = $row->{$field} ?? null;

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return CarbonImmutable::parse($value);
    }

    private function nullableDate(
        stdClass $row,
        string $field,
    ): ?CarbonImmutable {
        return ($row->{$field} ?? null) === null
            ? null
            : $this->date($row, $field);
    }
}
