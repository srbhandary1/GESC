<?php

declare(strict_types=1);

namespace App\Learning\Offline;

use App\Curriculum\CanonicalJson;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Learning\LevelSessions\LevelSessionDeliveryService;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * LPL-06b: signed level offline bundles without plaintext answers or rationales.
 */
final readonly class OfflineLevelBundleService
{
    public function __construct(
        private LearnerAuthority $authority,
        private LevelSessionDeliveryService $sessions,
        private OfflineLeaseSigner $leases,
    ) {}

    /**
     * @return array{manifest: array<string, mixed>, lease: string}
     */
    public function issue(User $learner, int $levelNumber): array
    {
        if ($levelNumber < 1) {
            throw new LearningOperationRejected;
        }

        $context = $this->authority->require($learner);
        $checkpoint = $this->sessions->checkpointDetail(
            $context,
            $learner,
            $levelNumber,
        );
        $practice = $this->sessions->sessionDetail(
            $context,
            $learner,
            $levelNumber,
            'practice',
        );
        $introduction = $this->sessions->sessionDetail(
            $context,
            $learner,
            $levelNumber,
            'introduction',
        );

        $leaseMinutes = (int) config('offline.lease_minutes', 0);
        if ($leaseMinutes < 1 || $leaseMinutes > 1440) {
            throw new LearningOperationRejected;
        }

        $issuedAt = Date::now()->toImmutable();
        $expiresAt = $issuedAt->addMinutes($leaseMinutes);
        $bundleId = (string) Str::ulid();
        $leaseId = (string) Str::ulid();
        $manifest = [
            'schema' => (string) config('offline.level_bundle_schema'),
            'bundle_id' => $bundleId,
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'binding' => [
                'user_id' => (string) $learner->getKey(),
                'organization_id' => $context->organizationId,
                'membership_id' => (string) $context->membershipId,
            ],
            'level' => [
                'level_number' => $levelNumber,
                'concept_key' => $checkpoint['concept_key'],
                'blueprint_id' => $checkpoint['blueprint_id'],
                'blueprint_content_hash' => $checkpoint['blueprint_content_hash'],
                'checkpoint_rule_key' => $checkpoint['rule_key'],
                'checkpoint_rule_hash' => $checkpoint['rule_hash'],
            ],
            'checkpoint' => [
                'pass_threshold' => $checkpoint['pass_threshold'],
                'max_attempts' => $checkpoint['max_attempts'],
                'attempts_remaining' => $checkpoint['attempts_remaining'],
                'items' => $checkpoint['items'],
            ],
            'practice_check' => $practice['check'] ?? null,
            'introduction' => [
                'media_asset_id' => $introduction['media_asset_id'] ?? null,
                'objective' => $introduction['objective'] ?? null,
                'kannada_guidance' => $introduction['kannada_guidance'] ?? null,
                'talk' => $introduction['talk'] ?? null,
            ],
        ];
        $bundleHash = CanonicalJson::sha256($manifest);
        $claims = [
            'purpose' => 'offline-level-checkpoint',
            'schema' => (string) config('offline.level_bundle_schema'),
            'lease_id' => $leaseId,
            'bundle_id' => $bundleId,
            'bundle_hash' => $bundleHash,
            'user_id' => (string) $learner->getKey(),
            'organization_id' => $context->organizationId,
            'membership_id' => (string) $context->membershipId,
            'level_number' => $levelNumber,
            'blueprint_id' => (string) $checkpoint['blueprint_id'],
            'blueprint_content_hash' => (string) $checkpoint['blueprint_content_hash'],
            'checkpoint_rule_hash' => (string) $checkpoint['rule_hash'],
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
