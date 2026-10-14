<?php

declare(strict_types=1);

namespace App\Learning\LevelSessions;

use App\Curriculum\CanonicalJson;
use App\Entitlements\DirectPlanDefaults;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Review\ReviewDueBlockingService;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LevelSessionProgressService
{
    public function __construct(
        private readonly ReviewDueBlockingService $reviewDue,
        private readonly LevelSpeakingEvidenceService $speakingEvidence,
    ) {}

    public function enabled(): bool
    {
        return config('level_sessions.sequential_unlock.enabled', true) === true;
    }

    public function ruleKey(): string
    {
        return (string) config(
            'level_sessions.sequential_unlock.rule_key',
            'sequential_four_session_v1',
        );
    }

    public function ruleHash(): string
    {
        $payload = [
            'rule_key' => $this->ruleKey(),
            'initial_open_levels' => config('level_sessions.sequential_unlock.initial_open_levels', []),
            'required_part_ids' => config('level_sessions.session_part_ids', []),
            'requires_level_checkpoint' => config(
                'level_sessions.level_checkpoint.enabled',
                true,
            ) === true,
            'requires_review_due_clear' => config(
                'level_sessions.review_due_blocking.enabled',
                true,
            ) === true,
        ];

        return CanonicalJson::sha256($payload);
    }

    /**
     * @param  array<string, mixed>|null  $entitlementSnapshot
     * @return array{
     *   progress_access: 'allowed'|'locked'|'not_applicable',
     *   unlock_after_level_number: int|null
     * }
     */
    public function projection(
        OrganizationContext $context,
        string $userId,
        int $levelNumber,
        string $availability,
        ?array $entitlementSnapshot = null,
    ): array {
        if ($availability !== 'available') {
            return [
                'progress_access' => 'not_applicable',
                'unlock_after_level_number' => null,
            ];
        }

        if (! $this->enabled() || $this->isInitiallyOpen($levelNumber)) {
            return [
                'progress_access' => 'allowed',
                'unlock_after_level_number' => null,
            ];
        }

        $prerequisiteLevel = $levelNumber - 1;
        if ($this->isLevelUnlockReady(
            $context->organizationId,
            $userId,
            $prerequisiteLevel,
            $entitlementSnapshot,
        )) {
            return [
                'progress_access' => 'allowed',
                'unlock_after_level_number' => null,
            ];
        }

        return [
            'progress_access' => 'locked',
            'unlock_after_level_number' => $prerequisiteLevel,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $entitlementSnapshot
     */
    public function assertUnlocked(
        OrganizationContext $context,
        string $userId,
        int $levelNumber,
        ?array $entitlementSnapshot = null,
    ): void {
        if (! $this->enabled() || $this->isInitiallyOpen($levelNumber)) {
            return;
        }

        $prerequisiteLevel = $levelNumber - 1;
        if ($this->isLevelUnlockReady(
            $context->organizationId,
            $userId,
            $prerequisiteLevel,
            $entitlementSnapshot,
        )) {
            return;
        }

        throw new LearningOperationRejected('level_progress_locked');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recordUnlockAfterLevelCompletion(
        OrganizationContext $context,
        string $userId,
        int $completedLevelNumber,
    ): ?array {
        if (! $this->enabled() || $this->isInitiallyOpen($completedLevelNumber + 1)) {
            return null;
        }

        if (! $this->isLevelUnlockReady(
            $context->organizationId,
            $userId,
            $completedLevelNumber,
        )) {
            return null;
        }

        $dueReview = $this->reviewDue->firstDueIncompleteReview($context);
        if ($dueReview !== null) {
            return null;
        }

        $unlockedLevel = $completedLevelNumber + 1;
        $maxLevel = (int) config('level_sessions.level_count', 17);
        if ($unlockedLevel > $maxLevel) {
            return null;
        }

        $idempotencyKey = sprintf(
            '%s:unlock:level:%d',
            $this->ruleKey(),
            $unlockedLevel,
        );

        return DB::transaction(function () use (
            $context,
            $userId,
            $completedLevelNumber,
            $unlockedLevel,
            $idempotencyKey,
        ): array {
            $existing = DB::table('level_unlock_decisions')
                ->where('organization_id', $context->organizationId)
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return [
                    'replayed' => true,
                    'unlock_decision_id' => $existing->id,
                    'level_number' => (int) $existing->level_number,
                ];
            }

            $checkpointAttempt = DB::table('level_checkpoint_attempts')
                ->where('organization_id', $context->organizationId)
                ->where('user_id', $userId)
                ->where('level_number', $completedLevelNumber)
                ->where('passed', true)
                ->orderByDesc('created_at')
                ->first();

            $evidence = [
                'completed_level_number' => $completedLevelNumber,
                'completed_part_ids' => $this->completedPartIds(
                    $context->organizationId,
                    $userId,
                    $completedLevelNumber,
                ),
                'checkpoint_passed' => $checkpointAttempt !== null
                    || config('level_sessions.level_checkpoint.enabled', true) !== true,
                'checkpoint_attempt_id' => $checkpointAttempt?->id,
                'checkpoint_score' => $checkpointAttempt !== null
                    ? (int) $checkpointAttempt->score
                    : null,
                'review_due_blocking' => false,
                'spoken_evidence' => $this->speakingEvidence->projection(
                    $context,
                    $completedLevelNumber,
                ),
            ];

            $id = (string) Str::ulid();
            $now = now();
            DB::table('level_unlock_decisions')->insert([
                'id' => $id,
                'organization_id' => $context->organizationId,
                'user_id' => $userId,
                'membership_id' => $context->membershipId,
                'level_number' => $unlockedLevel,
                'prerequisite_level_number' => $completedLevelNumber,
                'rule_key' => $this->ruleKey(),
                'rule_hash' => $this->ruleHash(),
                'reason_code' => 'prior_level_checkpoint_passed',
                'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
                'idempotency_key' => $idempotencyKey,
                'created_at' => $now,
            ]);

            return [
                'replayed' => false,
                'unlock_decision_id' => $id,
                'level_number' => $unlockedLevel,
            ];
        });
    }

    /**
     * @param  array<string, mixed>|null  $entitlementSnapshot
     */
    public function isLevelUnlockReady(
        string $organizationId,
        string $userId,
        int $levelNumber,
        ?array $entitlementSnapshot = null,
    ): bool {
        return $this->isLevelFullyComplete(
            $organizationId,
            $userId,
            $levelNumber,
            $entitlementSnapshot,
        ) && $this->hasPassedLevelCheckpoint($organizationId, $userId, $levelNumber);
    }

    /**
     * @param  array<string, mixed>|null  $entitlementSnapshot
     */
    public function isLevelFullyComplete(
        string $organizationId,
        string $userId,
        int $levelNumber,
        ?array $entitlementSnapshot = null,
    ): bool {
        $parts = $this->requiredSessionPartIds($entitlementSnapshot);
        if ($parts === []) {
            return false;
        }

        foreach ($parts as $partId) {
            $completed = DB::table('level_session_completions')
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->where('level_number', $levelNumber)
                ->where('part_id', $partId)
                ->exists();
            if (! $completed) {
                return false;
            }
        }

        return true;
    }

    public function hasPassedLevelCheckpoint(
        string $organizationId,
        string $userId,
        int $levelNumber,
    ): bool {
        if (config('level_sessions.level_checkpoint.enabled', true) !== true) {
            return true;
        }

        return DB::table('level_checkpoint_attempts')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('level_number', $levelNumber)
            ->where('passed', true)
            ->exists();
    }

    private function isInitiallyOpen(int $levelNumber): bool
    {
        $levels = config('level_sessions.sequential_unlock.initial_open_levels', []);

        return is_array($levels) && in_array($levelNumber, $levels, true);
    }

    /**
     * @param  array<string, mixed>|null  $entitlementSnapshot
     * @return list<string>
     */
    private function requiredSessionPartIds(?array $entitlementSnapshot): array
    {
        if (
            $entitlementSnapshot !== null
            && ($entitlementSnapshot['is_demo'] ?? false) === true
        ) {
            return DirectPlanDefaults::DEMO_SESSION_PARTS;
        }

        $configured = config('level_sessions.session_part_ids', []);
        if (! is_array($configured)) {
            return [];
        }

        $parts = [];
        foreach ($configured as $partId) {
            if (! is_string($partId) || $partId === '') {
                continue;
            }

            $parts[] = $partId;
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function completedPartIds(
        string $organizationId,
        string $userId,
        int $levelNumber,
    ): array {
        $partIds = DB::table('level_session_completions')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('level_number', $levelNumber)
            ->orderBy('part_id')
            ->pluck('part_id')
            ->map(static fn ($partId): string => (string) $partId)
            ->all();

        return array_values($partIds);
    }
}
