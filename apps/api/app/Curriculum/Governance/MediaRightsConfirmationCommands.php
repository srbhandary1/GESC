<?php

declare(strict_types=1);

namespace App\Curriculum\Governance;

use App\Audit\AuditEventRecorder;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use stdClass;

final readonly class MediaRightsConfirmationCommands
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private AuthorityEvidenceGate $authority,
        private GovernanceCommandIdempotency $idempotency,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    /**
     * @param  list<string>  $permittedReleaseChannels
     * @param  list<string>  $permittedTerritories
     *
     * @throws JsonException
     */
    public function confirm(
        User $actor,
        string $programId,
        string $mediaAssetId,
        string $selectedAssetHash,
        ?string $parentRevisionId,
        string $evidenceHash,
        string $rightsHolderReference,
        string $provenanceReference,
        string $rightsBasisKey,
        string $licenseOrContractReference,
        array $permittedReleaseChannels,
        array $permittedTerritories,
        bool $learnerDeliveryAllowed,
        bool $sharingAllowed,
        bool $derivativeUseAllowed,
        CarbonInterface $effectiveAt,
        ?CarbonInterface $expiresAt,
        string $retentionPolicyKey,
        string $deletionConstraintKey,
        string $authorityAppointmentId,
        string $idempotencyKey,
    ): string {
        GovernanceInput::ulid($programId);
        GovernanceInput::ulid($mediaAssetId);
        GovernanceInput::sha256($selectedAssetHash);

        if ($parentRevisionId !== null) {
            GovernanceInput::ulid($parentRevisionId);
        }

        GovernanceInput::sha256($evidenceHash);
        GovernanceInput::ulid($authorityAppointmentId);
        GovernanceInput::idempotencyKey($idempotencyKey);
        $rightsHolderReference = GovernanceInput::evidenceReference(
            $rightsHolderReference,
        );
        $provenanceReference = GovernanceInput::evidenceReference(
            $provenanceReference,
        );
        $rightsBasisKey = GovernanceInput::key($rightsBasisKey);
        $licenseOrContractReference = GovernanceInput::evidenceReference(
            $licenseOrContractReference,
        );
        $permittedReleaseChannels = GovernanceInput::stableKeyList(
            $permittedReleaseChannels,
        );
        $permittedTerritories = GovernanceInput::stableKeyList(
            $permittedTerritories,
        );
        $retentionPolicyKey = GovernanceInput::key($retentionPolicyKey);
        $deletionConstraintKey = GovernanceInput::key(
            $deletionConstraintKey,
        );

        if (
            $expiresAt !== null
            && $expiresAt->lessThanOrEqualTo($effectiveAt)
        ) {
            throw new CurriculumOperationRejected;
        }

        return DB::transaction(function () use (
            $actor,
            $programId,
            $mediaAssetId,
            $selectedAssetHash,
            $parentRevisionId,
            $evidenceHash,
            $rightsHolderReference,
            $provenanceReference,
            $rightsBasisKey,
            $licenseOrContractReference,
            $permittedReleaseChannels,
            $permittedTerritories,
            $learnerDeliveryAllowed,
            $sharingAllowed,
            $derivativeUseAllowed,
            $effectiveAt,
            $expiresAt,
            $retentionPolicyKey,
            $deletionConstraintKey,
            $authorityAppointmentId,
            $idempotencyKey,
        ): string {
            $this->assertPermission($actor);

            $context = $this->contexts->current();
            $program = DB::table('programs')
                ->where('organization_id', $context->organizationId)
                ->where('id', $programId)
                ->lockForUpdate()
                ->first(['id']);
            $asset = DB::table('media_assets')
                ->where('organization_id', $context->organizationId)
                ->where('id', $mediaAssetId)
                ->where('asset_hash', $selectedAssetHash)
                ->lockForUpdate()
                ->first(['id']);

            if (
                ! $program instanceof stdClass
                || ! $asset instanceof stdClass
            ) {
                throw new CurriculumOperationRejected;
            }

            $now = Date::now();
            $operation = 'curriculum.media_rights.confirm';
            $fingerprint = $this->idempotency->fingerprint([
                'authority_appointment_id' => $authorityAppointmentId,
                'deletion_constraint_key' => $deletionConstraintKey,
                'derivative_use_allowed' => $derivativeUseAllowed,
                'effective_at' => $this->instant($effectiveAt),
                'evidence_hash' => $evidenceHash,
                'expires_at' => $expiresAt === null
                    ? null
                    : $this->instant($expiresAt),
                'learner_delivery_allowed' => $learnerDeliveryAllowed,
                'license_or_contract_reference' => $licenseOrContractReference,
                'media_asset_id' => $mediaAssetId,
                'operation' => $operation,
                'parent_revision_id' => $parentRevisionId,
                'permitted_release_channels' => $permittedReleaseChannels,
                'permitted_territories' => $permittedTerritories,
                'program_id' => $programId,
                'provenance_reference' => $provenanceReference,
                'retention_policy_key' => $retentionPolicyKey,
                'rights_basis_key' => $rightsBasisKey,
                'rights_holder_reference' => $rightsHolderReference,
                'selected_asset_hash' => $selectedAssetHash,
                'sharing_allowed' => $sharingAllowed,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'media_asset',
                aggregateId: $mediaAssetId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            $accepted = $this->authority->acceptedForNewEvidence(
                actor: $actor,
                appointmentId: $authorityAppointmentId,
                authorityKind: AuthorityKind::RightsConfirmer,
                programId: $programId,
                at: $now,
            );
            $revisionNumber = $this->nextRevisionNumber(
                $programId,
                $mediaAssetId,
                $parentRevisionId,
            );

            $rightsRevisionId = (string) Str::ulid();

            $this->insertRevision(
                rightsRevisionId: $rightsRevisionId,
                programId: $programId,
                mediaAssetId: $mediaAssetId,
                parentRevisionId: $parentRevisionId,
                revisionNumber: $revisionNumber,
                evidenceStatus: 'approved',
                evidenceHash: $evidenceHash,
                selectedAssetHash: $selectedAssetHash,
                rightsHolderReference: $rightsHolderReference,
                provenanceReference: $provenanceReference,
                rightsBasisKey: $rightsBasisKey,
                licenseOrContractReference: $licenseOrContractReference,
                permittedReleaseChannels: json_encode(
                    $permittedReleaseChannels,
                    JSON_THROW_ON_ERROR,
                ),
                permittedTerritories: json_encode(
                    $permittedTerritories,
                    JSON_THROW_ON_ERROR,
                ),
                learnerDeliveryAllowed: $learnerDeliveryAllowed,
                sharingAllowed: $sharingAllowed,
                derivativeUseAllowed: $derivativeUseAllowed,
                effectiveAt: $effectiveAt,
                expiresAt: $expiresAt,
                retentionPolicyKey: $retentionPolicyKey,
                deletionConstraintKey: $deletionConstraintKey,
                appointment: $accepted,
                recordedAt: $now,
            );
            $this->recordEvidence(
                action: 'curriculum.media_rights.confirmed',
                eventType: 'curriculum.media_rights.confirmed',
                mediaAssetId: $mediaAssetId,
                rightsRevisionId: $rightsRevisionId,
                membershipId: $accepted->appointeeMembershipId,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'media_asset',
                aggregateId: $mediaAssetId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'media_rights_revision',
                resultId: $rightsRevisionId,
                completedAt: $now,
            );

            return $rightsRevisionId;
        }, 3);
    }

    public function reject(
        User $actor,
        string $programId,
        string $parentRevisionId,
        string $evidenceHash,
        string $licenseOrContractReference,
        string $authorityAppointmentId,
        string $idempotencyKey,
    ): string {
        return $this->recordNonApprovalSuccessor(
            actor: $actor,
            programId: $programId,
            parentRevisionId: $parentRevisionId,
            evidenceStatus: 'rejected',
            requiredParentStatus: 'pending',
            evidenceHash: $evidenceHash,
            licenseOrContractReference: $licenseOrContractReference,
            authorityAppointmentId: $authorityAppointmentId,
            idempotencyKey: $idempotencyKey,
        );
    }

    public function withdraw(
        User $actor,
        string $programId,
        string $parentRevisionId,
        string $evidenceHash,
        string $licenseOrContractReference,
        string $authorityAppointmentId,
        string $idempotencyKey,
    ): string {
        return $this->recordNonApprovalSuccessor(
            actor: $actor,
            programId: $programId,
            parentRevisionId: $parentRevisionId,
            evidenceStatus: 'withdrawn',
            requiredParentStatus: 'approved',
            evidenceHash: $evidenceHash,
            licenseOrContractReference: $licenseOrContractReference,
            authorityAppointmentId: $authorityAppointmentId,
            idempotencyKey: $idempotencyKey,
        );
    }

    private function recordNonApprovalSuccessor(
        User $actor,
        string $programId,
        string $parentRevisionId,
        string $evidenceStatus,
        string $requiredParentStatus,
        string $evidenceHash,
        string $licenseOrContractReference,
        string $authorityAppointmentId,
        string $idempotencyKey,
    ): string {
        GovernanceInput::ulid($programId);
        GovernanceInput::ulid($parentRevisionId);
        GovernanceInput::sha256($evidenceHash);
        $licenseOrContractReference = GovernanceInput::evidenceReference(
            $licenseOrContractReference,
        );
        GovernanceInput::ulid($authorityAppointmentId);
        GovernanceInput::idempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $programId,
            $parentRevisionId,
            $evidenceStatus,
            $requiredParentStatus,
            $evidenceHash,
            $licenseOrContractReference,
            $authorityAppointmentId,
            $idempotencyKey,
        ): string {
            $this->assertPermission($actor);
            $context = $this->contexts->current();
            $unlockedParent = DB::table('media_rights_revisions')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->where('id', $parentRevisionId)
                ->first(['media_asset_id', 'selected_asset_hash']);

            if (! $unlockedParent instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $mediaAssetId = $this->stringProperty(
                $unlockedParent,
                'media_asset_id',
            );
            $selectedAssetHash = $this->stringProperty(
                $unlockedParent,
                'selected_asset_hash',
            );
            $asset = DB::table('media_assets')
                ->where('organization_id', $context->organizationId)
                ->where('id', $mediaAssetId)
                ->where('asset_hash', $selectedAssetHash)
                ->lockForUpdate()
                ->first(['id']);
            $parent = DB::table('media_rights_revisions')
                ->where('organization_id', $context->organizationId)
                ->where('program_id', $programId)
                ->where('media_asset_id', $mediaAssetId)
                ->where('id', $parentRevisionId)
                ->where('selected_asset_hash', $selectedAssetHash)
                ->lockForUpdate()
                ->first([
                    'revision_number',
                    'evidence_status',
                    'rights_holder_reference',
                    'provenance_reference',
                    'rights_basis_key',
                    'permitted_release_channels',
                    'permitted_territories',
                    'learner_delivery_allowed',
                    'sharing_allowed',
                    'derivative_use_allowed',
                    'retention_policy_key',
                    'deletion_constraint_key',
                ]);

            if (
                ! $asset instanceof stdClass
                || ! $parent instanceof stdClass
            ) {
                throw new CurriculumOperationRejected;
            }

            $operation = 'curriculum.media_rights.'.$evidenceStatus;
            $fingerprint = $this->idempotency->fingerprint([
                'authority_appointment_id' => $authorityAppointmentId,
                'evidence_hash' => $evidenceHash,
                'evidence_status' => $evidenceStatus,
                'license_or_contract_reference' => $licenseOrContractReference,
                'operation' => $operation,
                'parent_revision_id' => $parentRevisionId,
                'program_id' => $programId,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'media_asset',
                aggregateId: $mediaAssetId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            if (
                $this->stringProperty($parent, 'evidence_status')
                    !== $requiredParentStatus
            ) {
                throw new CurriculumOperationRejected;
            }

            $now = Date::now();
            $accepted = $this->authority->acceptedForNewEvidence(
                actor: $actor,
                appointmentId: $authorityAppointmentId,
                authorityKind: AuthorityKind::RightsConfirmer,
                programId: $programId,
                at: $now,
            );
            $revisionNumber = $this->nextRevisionNumber(
                $programId,
                $mediaAssetId,
                $parentRevisionId,
            );
            $rightsRevisionId = (string) Str::ulid();

            $this->insertRevision(
                rightsRevisionId: $rightsRevisionId,
                programId: $programId,
                mediaAssetId: $mediaAssetId,
                parentRevisionId: $parentRevisionId,
                revisionNumber: $revisionNumber,
                evidenceStatus: $evidenceStatus,
                evidenceHash: $evidenceHash,
                selectedAssetHash: $selectedAssetHash,
                rightsHolderReference: $this->stringProperty(
                    $parent,
                    'rights_holder_reference',
                ),
                provenanceReference: $this->stringProperty(
                    $parent,
                    'provenance_reference',
                ),
                rightsBasisKey: $this->stringProperty(
                    $parent,
                    'rights_basis_key',
                ),
                licenseOrContractReference: $licenseOrContractReference,
                permittedReleaseChannels: $this->stringProperty(
                    $parent,
                    'permitted_release_channels',
                ),
                permittedTerritories: $this->stringProperty(
                    $parent,
                    'permitted_territories',
                ),
                learnerDeliveryAllowed: $this->booleanProperty(
                    $parent,
                    'learner_delivery_allowed',
                ),
                sharingAllowed: $this->booleanProperty(
                    $parent,
                    'sharing_allowed',
                ),
                derivativeUseAllowed: $this->booleanProperty(
                    $parent,
                    'derivative_use_allowed',
                ),
                effectiveAt: $now,
                expiresAt: null,
                retentionPolicyKey: $this->stringProperty(
                    $parent,
                    'retention_policy_key',
                ),
                deletionConstraintKey: $this->stringProperty(
                    $parent,
                    'deletion_constraint_key',
                ),
                appointment: $accepted,
                recordedAt: $now,
            );
            $eventType = 'curriculum.media_rights.'.$evidenceStatus;
            $this->recordEvidence(
                action: $eventType,
                eventType: $eventType,
                mediaAssetId: $mediaAssetId,
                rightsRevisionId: $rightsRevisionId,
                membershipId: $accepted->appointeeMembershipId,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'media_asset',
                aggregateId: $mediaAssetId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'media_rights_revision',
                resultId: $rightsRevisionId,
                completedAt: $now,
            );

            return $rightsRevisionId;
        }, 3);
    }

    private function assertPermission(User $actor): void
    {
        if (
            ! $this->authorization->allows(
                $actor,
                'media.rights.approve',
            )
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function nextRevisionNumber(
        string $programId,
        string $mediaAssetId,
        ?string $parentRevisionId,
    ): int {
        $latest = DB::table('media_rights_revisions')
            ->where(
                'organization_id',
                $this->contexts->current()->organizationId,
            )
            ->where('program_id', $programId)
            ->where('media_asset_id', $mediaAssetId)
            ->orderByDesc('revision_number')
            ->lockForUpdate()
            ->first(['id', 'revision_number']);

        if ($latest instanceof stdClass) {
            if (
                ! is_string($latest->id)
                || ! is_int($latest->revision_number)
                || $parentRevisionId === null
                || ! hash_equals($latest->id, $parentRevisionId)
            ) {
                throw new CurriculumOperationRejected;
            }

            return $latest->revision_number + 1;
        }

        if ($parentRevisionId !== null) {
            throw new CurriculumOperationRejected;
        }

        return 1;
    }

    private function insertRevision(
        string $rightsRevisionId,
        string $programId,
        string $mediaAssetId,
        ?string $parentRevisionId,
        int $revisionNumber,
        string $evidenceStatus,
        string $evidenceHash,
        string $selectedAssetHash,
        string $rightsHolderReference,
        string $provenanceReference,
        string $rightsBasisKey,
        string $licenseOrContractReference,
        string $permittedReleaseChannels,
        string $permittedTerritories,
        bool $learnerDeliveryAllowed,
        bool $sharingAllowed,
        bool $derivativeUseAllowed,
        CarbonInterface $effectiveAt,
        ?CarbonInterface $expiresAt,
        string $retentionPolicyKey,
        string $deletionConstraintKey,
        AcceptedAuthorityAppointment $appointment,
        CarbonInterface $recordedAt,
    ): void {
        DB::table('media_rights_revisions')->insert([
            'id' => $rightsRevisionId,
            'organization_id' => $this->contexts->current()->organizationId,
            'program_id' => $programId,
            'media_asset_id' => $mediaAssetId,
            'parent_revision_id' => $parentRevisionId,
            'revision_number' => $revisionNumber,
            'evidence_status' => $evidenceStatus,
            'evidence_hash' => $evidenceHash,
            'selected_asset_hash' => $selectedAssetHash,
            'rights_holder_reference' => $rightsHolderReference,
            'provenance_reference' => $provenanceReference,
            'rights_basis_key' => $rightsBasisKey,
            'license_or_contract_reference' => $licenseOrContractReference,
            'permitted_release_channels' => $permittedReleaseChannels,
            'permitted_territories' => $permittedTerritories,
            'learner_delivery_allowed' => $learnerDeliveryAllowed,
            'sharing_allowed' => $sharingAllowed,
            'derivative_use_allowed' => $derivativeUseAllowed,
            'effective_at' => $effectiveAt,
            'expires_at' => $expiresAt,
            'retention_policy_key' => $retentionPolicyKey,
            'deletion_constraint_key' => $deletionConstraintKey,
            'confirmed_by_membership_id' => $appointment->appointeeMembershipId,
            'rights_appointment_id' => $appointment->appointmentId,
            'rights_appointment_event_id' => $appointment->acceptanceEventId,
            'confirmed_at' => $recordedAt,
            'created_at' => $recordedAt,
            'updated_at' => $recordedAt,
        ]);
    }

    private function recordEvidence(
        string $action,
        string $eventType,
        string $mediaAssetId,
        string $rightsRevisionId,
        string $membershipId,
    ): void {
        $context = $this->contexts->current();

        $this->audit->recordTenant(
            $action,
            'media_rights_revision',
            $rightsRevisionId,
        );
        $this->outbox->recordTenant(
            $eventType,
            'media_rights_revision',
            $rightsRevisionId,
            [
                'media_asset_id' => $mediaAssetId,
                'media_rights_revision_id' => $rightsRevisionId,
                'membership_id' => $membershipId,
                'organization_id' => $context->organizationId,
            ],
        );
    }

    private function instant(CarbonInterface $value): string
    {
        return CarbonImmutable::instance($value)
            ->utc()
            ->format('Y-m-d\TH:i:s.u\Z');
    }

    private function stringProperty(
        stdClass $record,
        string $property,
    ): string {
        $value = $record->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function booleanProperty(
        stdClass $record,
        string $property,
    ): bool {
        $value = $record->{$property} ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1) {
            return (bool) $value;
        }

        throw new CurriculumOperationRejected;
    }
}
