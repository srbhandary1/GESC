<?php

declare(strict_types=1);

namespace App\Learning\LevelSessions;

use App\Curriculum\CanonicalJson;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Review\ReviewDueBlockingService;
use App\Models\LessonPracticeBlueprint;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class LevelCheckpointService
{
    public function __construct(
        private readonly LevelSessionProgressService $progress,
        private readonly ReviewDueBlockingService $reviewDue,
        private readonly LevelSpeakingEvidenceService $speakingEvidence,
    ) {}

    public function enabled(): bool
    {
        return config('level_sessions.level_checkpoint.enabled', true) === true;
    }

    public function ruleKey(): string
    {
        return (string) config(
            'level_sessions.level_checkpoint.rule_key',
            'level_checkpoint_v1',
        );
    }

    public function ruleHash(): string
    {
        $payload = [
            'rule_key' => $this->ruleKey(),
            'pass_threshold' => $this->passThreshold(),
            'max_attempts' => $this->maxAttempts(),
        ];

        return CanonicalJson::sha256($payload);
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('level_sessions.level_checkpoint.max_attempts', 3));
    }

    public function passThreshold(): int
    {
        return max(1, (int) config('level_sessions.level_checkpoint.pass_threshold', 80));
    }

    /**
     * @return array{
     *   checkpoint_status: 'not_applicable'|'not_ready'|'ready'|'passed'|'attempts_exhausted'|'review_due_blocked',
     *   checkpoint_attempts_remaining: int|null,
     *   review_due_blocking: bool
     * }
     */
    /**
     * @param  array<string, mixed>|null  $entitlementSnapshot
     */
    public function projection(
        OrganizationContext $context,
        string $userId,
        int $levelNumber,
        string $availability,
        ?array $entitlementSnapshot = null,
    ): array {
        if ($availability !== 'available' || ! $this->enabled()) {
            return $this->projectionPayload(
                'not_applicable',
                null,
                false,
            );
        }

        if ($this->progress->hasPassedLevelCheckpoint(
            $context->organizationId,
            $userId,
            $levelNumber,
        )) {
            return $this->projectionPayload(
                'passed',
                null,
                false,
            );
        }

        if (! $this->progress->isLevelFullyComplete(
            $context->organizationId,
            $userId,
            $levelNumber,
            $entitlementSnapshot,
        )) {
            return $this->projectionPayload(
                'not_ready',
                null,
                false,
            );
        }

        $remaining = $this->attemptsRemaining(
            $context->organizationId,
            $userId,
            $levelNumber,
        );
        if ($remaining <= 0) {
            return $this->projectionPayload(
                'attempts_exhausted',
                0,
                false,
            );
        }

        if ($this->reviewDue->hasDueIncompleteReview($context)) {
            return $this->projectionPayload(
                'review_due_blocked',
                $remaining,
                true,
            );
        }

        return $this->projectionPayload(
            'ready',
            $remaining,
            false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>|null  $entitlementSnapshot
     */
    public function checkpointDetail(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
        LessonPracticeBlueprint $blueprint,
        ?array $entitlementSnapshot = null,
    ): array {
        $userId = (string) $learner->getKey();
        $status = $this->projection(
            $context,
            $userId,
            $levelNumber,
            'available',
            $entitlementSnapshot,
        );
        if ($status['checkpoint_status'] === 'review_due_blocked') {
            throw new LearningOperationRejected('review_due_blocking');
        }
        if ($status['checkpoint_status'] !== 'ready') {
            throw new LearningOperationRejected('checkpoint_not_ready');
        }

        $definition = $this->checkpointDefinition($blueprint->canonical_json);
        $items = [];
        foreach ($definition['items'] as $item) {
            $items[] = [
                'id' => (string) ($item['id'] ?? ''),
                'weight' => (int) ($item['weight'] ?? 0),
                'prompt' => (string) ($item['prompt'] ?? ''),
                'options' => array_values(
                    is_array($item['options'] ?? null) ? $item['options'] : [],
                ),
            ];
        }

        return [
            'level_number' => $levelNumber,
            'concept_key' => $blueprint->concept_key,
            'blueprint_id' => $blueprint->getKey(),
            'blueprint_content_hash' => $blueprint->content_hash,
            'rule_key' => $this->ruleKey(),
            'rule_hash' => $this->ruleHash(),
            'pass_threshold' => $definition['pass_threshold'],
            'max_attempts' => $this->maxAttempts(),
            'attempts_remaining' => $status['checkpoint_attempts_remaining'],
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, int>  $answers
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>|null  $entitlementSnapshot
     */
    public function submitCheckpoint(
        OrganizationContext $context,
        User $learner,
        int $levelNumber,
        LessonPracticeBlueprint $blueprint,
        array $answers,
        string $idempotencyKey,
        ?array $entitlementSnapshot = null,
    ): array {
        $userId = (string) $learner->getKey();
        $existing = DB::table('level_checkpoint_attempts')
            ->where('organization_id', $context->organizationId)
            ->where('user_id', $userId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing instanceof stdClass) {
            return $this->attemptPayload($context, $userId, $existing, true);
        }

        $status = $this->projection(
            $context,
            $userId,
            $levelNumber,
            'available',
            $entitlementSnapshot,
        );
        if ($status['checkpoint_status'] === 'passed') {
            throw new LearningOperationRejected('checkpoint_already_passed');
        }
        if ($status['checkpoint_status'] === 'review_due_blocked') {
            throw new LearningOperationRejected('review_due_blocking');
        }
        if ($status['checkpoint_status'] !== 'ready') {
            throw new LearningOperationRejected('checkpoint_not_ready');
        }

        $definition = $this->checkpointDefinition($blueprint->canonical_json);
        $items = $definition['items'];
        $threshold = $definition['pass_threshold'];

        return DB::transaction(function () use (
            $context,
            $levelNumber,
            $blueprint,
            $answers,
            $idempotencyKey,
            $items,
            $threshold,
            $userId,
        ): array {
            $existing = DB::table('level_checkpoint_attempts')
                ->where('organization_id', $context->organizationId)
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof stdClass) {
                return $this->attemptPayload($context, $userId, $existing, true);
            }

            $attemptNumber = DB::table('level_checkpoint_attempts')
                ->where('organization_id', $context->organizationId)
                ->where('user_id', $userId)
                ->where('level_number', $levelNumber)
                ->count() + 1;

            $earned = 0;
            $total = 0;
            $results = [];
            foreach ($items as $item) {
                $id = (string) ($item['id'] ?? '');
                $weight = (int) ($item['weight'] ?? 0);
                $correct = (int) ($item['correct_option'] ?? -1);
                $selected = array_key_exists($id, $answers)
                    ? (int) $answers[$id]
                    : -1;
                $isCorrect = $selected === $correct;
                if ($isCorrect) {
                    $earned += $weight;
                }
                $total += $weight;
                $results[] = [
                    'id' => $id,
                    'selected_option' => $selected,
                    'correct' => $isCorrect,
                    'rationale' => $item['rationale'] ?? null,
                ];
            }

            $score = $total > 0 ? (int) round(($earned / $total) * 100) : 0;
            $passed = $score >= $threshold;
            $spokenEvidence = $this->speakingEvidence->projection(
                $context,
                $levelNumber,
            );
            $attemptId = (string) Str::ulid();
            DB::table('level_checkpoint_attempts')->insert([
                'id' => $attemptId,
                'organization_id' => $context->organizationId,
                'user_id' => $userId,
                'membership_id' => $context->membershipId,
                'level_number' => $levelNumber,
                'blueprint_id' => $blueprint->getKey(),
                'blueprint_content_hash' => $blueprint->content_hash,
                'rule_key' => $this->ruleKey(),
                'rule_hash' => $this->ruleHash(),
                'attempt_number' => $attemptNumber,
                'score' => $score,
                'pass_threshold' => $threshold,
                'passed' => $passed,
                'item_results_json' => json_encode($results, JSON_THROW_ON_ERROR),
                'spoken_evidence_json' => json_encode(
                    $spokenEvidence,
                    JSON_THROW_ON_ERROR,
                ),
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            $unlock = null;
            if ($passed && ! $this->reviewDue->hasDueIncompleteReview($context)) {
                $unlock = $this->progress->recordUnlockAfterLevelCompletion(
                    $context,
                    $userId,
                    $levelNumber,
                );
            }

            $remaining = $passed
                ? null
                : max(0, $this->maxAttempts() - $attemptNumber);

            return [
                'replayed' => false,
                'attempt_id' => $attemptId,
                'attempt_number' => $attemptNumber,
                'score' => $score,
                'pass_threshold' => $threshold,
                'passed' => $passed,
                'attempts_remaining' => $remaining,
                'item_results' => $results,
                'spoken_evidence' => $spokenEvidence,
                'level_unlock' => $unlock,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $canonical
     * @return array{pass_threshold: int, items: list<array<string, mixed>>}
     */
    public function checkpointDefinition(array $canonical): array
    {
        $checkpoint = $canonical['level_checkpoint'] ?? null;
        if (is_array($checkpoint) && is_array($checkpoint['items'] ?? null)) {
            return [
                'pass_threshold' => (int) ($checkpoint['pass_threshold'] ?? $this->passThreshold()),
                'items' => array_values($checkpoint['items']),
            ];
        }

        $check = is_array($canonical['check'] ?? null) ? $canonical['check'] : [];
        $items = is_array($check['items'] ?? null) ? $check['items'] : [];
        $derived = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $derived[] = [
                ...$item,
                'id' => 'checkpoint-'.(string) ($item['id'] ?? ''),
            ];
        }

        return [
            'pass_threshold' => (int) ($check['pass_threshold'] ?? $this->passThreshold()),
            'items' => $derived,
        ];
    }

    /**
     * @param  'not_applicable'|'not_ready'|'ready'|'passed'|'attempts_exhausted'|'review_due_blocked'  $status
     * @return array{
     *   checkpoint_status: 'not_applicable'|'not_ready'|'ready'|'passed'|'attempts_exhausted'|'review_due_blocked',
     *   checkpoint_attempts_remaining: int|null,
     *   review_due_blocking: bool
     * }
     */
    private function projectionPayload(
        string $status,
        ?int $remaining,
        bool $reviewDueBlocking,
    ): array {
        return [
            'checkpoint_status' => $status,
            'checkpoint_attempts_remaining' => $remaining,
            'review_due_blocking' => $reviewDueBlocking,
        ];
    }

    /**
     * @return array{
     *   evidence_state: string,
     *   spoken_state: 'unassessed',
     *   spoken_progress_claim: false
     * }
     */
    private function decodeSpokenEvidence(stdClass $row): array
    {
        $stored = json_decode((string) ($row->spoken_evidence_json ?? ''), true);
        if (
            is_array($stored)
            && is_string($stored['evidence_state'] ?? null)
            && ($stored['spoken_state'] ?? null) === 'unassessed'
            && ($stored['spoken_progress_claim'] ?? null) === false
        ) {
            return [
                'evidence_state' => $stored['evidence_state'],
                'spoken_state' => 'unassessed',
                'spoken_progress_claim' => false,
            ];
        }

        return [
            'evidence_state' => 'not_recorded',
            'spoken_state' => 'unassessed',
            'spoken_progress_claim' => false,
        ];
    }

    private function attemptsRemaining(
        string $organizationId,
        string $userId,
        int $levelNumber,
    ): int {
        $used = DB::table('level_checkpoint_attempts')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('level_number', $levelNumber)
            ->count();

        return max(0, $this->maxAttempts() - $used);
    }

    /**
     * @return array<string, mixed>
     */
    private function attemptPayload(
        OrganizationContext $context,
        string $userId,
        stdClass $row,
        bool $replayed,
    ): array {
        $passed = (bool) $row->passed;
        $unlock = $passed
            ? $this->progress->recordUnlockAfterLevelCompletion(
                $context,
                $userId,
                (int) $row->level_number,
            )
            : null;

        return [
            'replayed' => $replayed,
            'attempt_id' => $row->id,
            'attempt_number' => (int) $row->attempt_number,
            'score' => (int) $row->score,
            'pass_threshold' => (int) $row->pass_threshold,
            'passed' => $passed,
            'attempts_remaining' => $passed
                ? null
                : max(
                    0,
                    $this->maxAttempts() - (int) $row->attempt_number,
                ),
            'item_results' => json_decode(
                (string) $row->item_results_json,
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
            'spoken_evidence' => $this->decodeSpokenEvidence($row),
            'level_unlock' => $unlock,
        ];
    }
}
