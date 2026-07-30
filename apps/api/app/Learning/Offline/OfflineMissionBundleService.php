<?php

declare(strict_types=1);

namespace App\Learning\Offline;

use App\Curriculum\CanonicalJson;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Learning\Missions\DailyMissionReader;
use App\Learning\Policy\DailySpeakingPolicyRegistry;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final readonly class OfflineMissionBundleService
{
    public function __construct(
        private LearnerAuthority $authority,
        private DailyMissionReader $missions,
        private DailySpeakingPolicyRegistry $policies,
        private OfflineLeaseSigner $leases,
    ) {}

    /**
     * @return array{manifest: array<string, mixed>, lease: string}
     */
    public function issue(
        User $learner,
        string $enrollmentId,
        string $missionId,
    ): array {
        if (! Str::isUlid($enrollmentId) || ! Str::isUlid($missionId)) {
            throw new LearningOperationRejected;
        }

        $context = $this->authority->require($learner);
        $mission = $this->missions->resolve(
            $learner,
            $enrollmentId,
            $missionId,
        );
        $leaseMinutes = (int) config('offline.lease_minutes', 0);

        if ($leaseMinutes < 1 || $leaseMinutes > 1440) {
            throw new LearningOperationRejected;
        }

        $issuedAt = Date::now()->toImmutable();
        $expiresAt = $issuedAt->addMinutes($leaseMinutes);
        $bundleId = (string) Str::ulid();
        $leaseId = (string) Str::ulid();
        $policy = $this->policies->default();
        $missionPayload = $mission->publicPayload();
        $manifest = [
            'schema' => (string) config('offline.bundle_schema'),
            'bundle_id' => $bundleId,
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'binding' => [
                'user_id' => (string) $learner->getKey(),
                'organization_id' => $context->organizationId,
                'membership_id' => (string) $context->membershipId,
                'enrollment_id' => $enrollmentId,
            ],
            'publication' => [
                'owner_organization_id' => $mission->ownerOrganizationId,
                'curriculum_version_id' => $mission->curriculumVersionId,
                'publication_id' => $mission->publicationId,
                'manifest_hash' => $mission->manifestHash,
                'mission_definition_hash' => $mission->definitionHash,
            ],
            'policy_version' => $policy->version,
            'mission' => [
                ...$missionPayload,
                'offline_check' => [
                    'correct_option_id' => $mission->correctOptionId,
                ],
                'model_audio' => [
                    ...$missionPayload['model_audio'],
                    'content_hash' => $mission->modelAudioContentHash,
                    'byte_size' => $mission->modelAudioByteSize,
                ],
            ],
        ];
        $bundleHash = CanonicalJson::sha256($manifest);
        $claims = [
            'purpose' => 'offline-mission-completion',
            'schema' => (string) config('offline.bundle_schema'),
            'lease_id' => $leaseId,
            'bundle_id' => $bundleId,
            'bundle_hash' => $bundleHash,
            'user_id' => (string) $learner->getKey(),
            'organization_id' => $context->organizationId,
            'membership_id' => (string) $context->membershipId,
            'enrollment_id' => $enrollmentId,
            'owner_organization_id' => $mission->ownerOrganizationId,
            'curriculum_version_id' => $mission->curriculumVersionId,
            'publication_id' => $mission->publicationId,
            'publication_manifest_hash' => $mission->manifestHash,
            'mission_id' => $mission->missionId,
            'mission_definition_hash' => $mission->definitionHash,
            'policy_version' => $policy->version,
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        return [
            'manifest' => [
                ...$manifest,
                'bundle_hash' => $bundleHash,
            ],
            'lease' => $this->leases->issue($claims),
        ];
    }
}
