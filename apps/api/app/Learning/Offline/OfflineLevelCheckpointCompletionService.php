<?php

declare(strict_types=1);

namespace App\Learning\Offline;

use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Learning\LevelSessions\LevelSessionDeliveryService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * LPL-06b: verify a signed level bundle lease before authoritative checkpoint scoring.
 */
final readonly class OfflineLevelCheckpointCompletionService
{
    public function __construct(
        private LearnerAuthority $authority,
        private LevelSessionDeliveryService $sessions,
        private OfflineLeaseSigner $leases,
    ) {}

    /**
     * @param  array<string, int>  $answers
     * @return array<string, mixed>
     */
    public function complete(
        User $learner,
        string $bundleId,
        string $lease,
        int $levelNumber,
        array $answers,
        string $idempotencyKey,
    ): array {
        if (! Str::isUlid($bundleId) || $levelNumber < 1) {
            throw new LearningOperationRejected;
        }

        $claims = $this->leases->verify($lease);
        $context = $this->authority->require($learner);
        $this->assertClaim($claims, 'purpose', 'offline-level-checkpoint');
        $this->assertClaim(
            $claims,
            'schema',
            (string) config('offline.level_bundle_schema'),
        );
        $this->assertClaim($claims, 'bundle_id', $bundleId);
        $this->assertClaim($claims, 'user_id', (string) $learner->getKey());
        $this->assertClaim($claims, 'organization_id', $context->organizationId);
        $this->assertClaim($claims, 'membership_id', (string) $context->membershipId);
        $this->assertClaim($claims, 'level_number', $levelNumber);

        $expiresAt = $this->claimDate($claims, 'expires_at');
        $issuedAt = $this->claimDate($claims, 'issued_at');
        if (
            $expiresAt->lessThanOrEqualTo(Date::now())
            || $expiresAt->lessThanOrEqualTo($issuedAt)
            || $expiresAt->diffInMinutes($issuedAt, true) > 1440
        ) {
            throw new LearningOperationRejected;
        }

        $detail = $this->sessions->checkpointDetail(
            $context,
            $learner,
            $levelNumber,
        );
        $this->assertClaim(
            $claims,
            'blueprint_id',
            (string) $detail['blueprint_id'],
        );
        $this->assertClaim(
            $claims,
            'blueprint_content_hash',
            (string) $detail['blueprint_content_hash'],
        );
        $this->assertClaim(
            $claims,
            'checkpoint_rule_hash',
            (string) $detail['rule_hash'],
        );

        return $this->sessions->submitCheckpoint(
            $context,
            $learner,
            $levelNumber,
            $answers,
            $idempotencyKey,
        );
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertClaim(array $claims, string $key, mixed $expected): void
    {
        if (! array_key_exists($key, $claims) || $claims[$key] !== $expected) {
            throw new LearningOperationRejected;
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function claimDate(array $claims, string $key): CarbonImmutable
    {
        $value = $claims[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new LearningOperationRejected;
        }

        return CarbonImmutable::parse($value);
    }
}
