<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Learning\LevelSessions\LevelCheckpointService;
use App\Learning\LevelSessions\LevelSessionProgressService;
use App\Learning\Review\ReviewDueBlockingService;
use App\Models\Membership;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\TenancyIntegrationTestCase;

final class LevelCheckpointReviewDueTest extends TenancyIntegrationTestCase
{
    public function test_due_review_blocks_checkpoint_projection_and_unlock(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $learner = $this->createUser();
        [, $learnerContext] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );
        $userId = (string) $learner->getKey();
        $organizationId = (string) $organization->getKey();
        $membershipId = (string) $learnerContext->membershipId;

        foreach (['introduction', 'practice', 'conversation', 'interaction'] as $partId) {
            $this->insertCompletion($organizationId, $userId, $membershipId, 1, $partId);
        }

        $reviewDue = $this->createMock(ReviewDueBlockingService::class);
        $reviewDue->method('enabled')->willReturn(true);
        $reviewDue->method('hasDueIncompleteReview')->willReturn(true);
        $reviewDue->method('firstDueIncompleteReview')
            ->willReturn([
                'id' => '01M2D900000000000000000001',
                'enrollment_id' => '01M2D900000000000000000010',
                'due_at' => CarbonImmutable::now()->subHour()->toIso8601String(),
                'learning_objective_id' => '01M2D900000000000000000002',
                'curriculum_mission_id' => '01M2D900000000000000000003',
                'interval_days' => 3,
                'timezone' => 'Asia/Kolkata',
            ]);
        $this->app->instance(ReviewDueBlockingService::class, $reviewDue);

        $checkpoints = app(LevelCheckpointService::class);
        $projection = $this->contexts()->run(
            $learnerContext,
            fn (): array => $checkpoints->projection(
                $learnerContext,
                $userId,
                1,
                'available',
            ),
        );
        self::assertSame('review_due_blocked', $projection['checkpoint_status']);
        self::assertTrue($projection['review_due_blocking']);
        self::assertSame(3, $projection['checkpoint_attempts_remaining']);

        $this->insertCheckpointPass($organizationId, $userId, $membershipId, 1);
        $progress = app(LevelSessionProgressService::class);
        $unlock = $this->contexts()->run(
            $learnerContext,
            fn (): ?array => $progress->recordUnlockAfterLevelCompletion(
                $learnerContext,
                $userId,
                1,
            ),
        );
        self::assertNull($unlock);
    }

    public function test_cleared_review_restores_checkpoint_ready_and_unlock(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $learner = $this->createUser();
        [, $learnerContext] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );
        $userId = (string) $learner->getKey();
        $organizationId = (string) $organization->getKey();
        $membershipId = (string) $learnerContext->membershipId;

        foreach (['introduction', 'practice', 'conversation', 'interaction'] as $partId) {
            $this->insertCompletion($organizationId, $userId, $membershipId, 1, $partId);
        }

        $reviewDue = $this->createMock(ReviewDueBlockingService::class);
        $reviewDue->method('enabled')->willReturn(true);
        $reviewDue->method('hasDueIncompleteReview')->willReturn(false);
        $reviewDue->method('firstDueIncompleteReview')->willReturn(null);
        $this->app->instance(ReviewDueBlockingService::class, $reviewDue);

        $checkpoints = app(LevelCheckpointService::class);
        $projection = $this->contexts()->run(
            $learnerContext,
            fn (): array => $checkpoints->projection(
                $learnerContext,
                $userId,
                1,
                'available',
            ),
        );
        self::assertSame('ready', $projection['checkpoint_status']);
        self::assertFalse($projection['review_due_blocking']);

        $this->insertCheckpointPass($organizationId, $userId, $membershipId, 1);
        $progress = app(LevelSessionProgressService::class);
        $unlock = $this->contexts()->run(
            $learnerContext,
            fn (): ?array => $progress->recordUnlockAfterLevelCompletion(
                $learnerContext,
                $userId,
                1,
            ),
        );
        self::assertNotNull($unlock);
        self::assertSame(2, $unlock['level_number']);
    }

    public function test_later_due_review_does_not_revoke_an_issued_unlock(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $learner = $this->createUser();
        [, $learnerContext] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );
        $userId = (string) $learner->getKey();
        $organizationId = (string) $organization->getKey();
        $membershipId = (string) $learnerContext->membershipId;

        foreach (['introduction', 'practice', 'conversation', 'interaction'] as $partId) {
            $this->insertCompletion($organizationId, $userId, $membershipId, 1, $partId);
        }
        $this->insertCheckpointPass($organizationId, $userId, $membershipId, 1);

        $reviewDue = $this->createMock(ReviewDueBlockingService::class);
        $reviewDue->method('enabled')->willReturn(true);
        $reviewDue->method('firstDueIncompleteReview')->willReturn(null);
        $this->app->instance(ReviewDueBlockingService::class, $reviewDue);

        $progress = app(LevelSessionProgressService::class);
        $unlock = $this->contexts()->run(
            $learnerContext,
            fn (): ?array => $progress->recordUnlockAfterLevelCompletion(
                $learnerContext,
                $userId,
                1,
            ),
        );
        self::assertNotNull($unlock);

        $reviewDueAfterUnlock = $this->createMock(ReviewDueBlockingService::class);
        $reviewDueAfterUnlock->method('enabled')->willReturn(true);
        $reviewDueAfterUnlock->method('hasDueIncompleteReview')->willReturn(true);
        $this->app->instance(ReviewDueBlockingService::class, $reviewDueAfterUnlock);

        $levelTwo = $this->contexts()->run(
            $learnerContext,
            fn (): array => $progress->projection(
                $learnerContext,
                $userId,
                2,
                'available',
            ),
        );
        self::assertSame('allowed', $levelTwo['progress_access']);
    }

    private function insertCheckpointPass(
        string $organizationId,
        string $userId,
        string $membershipId,
        int $levelNumber,
    ): void {
        $now = CarbonImmutable::now();
        DB::table('level_checkpoint_attempts')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'membership_id' => $membershipId,
            'level_number' => $levelNumber,
            'blueprint_id' => (string) Str::ulid(),
            'blueprint_content_hash' => hash('sha256', 'checkpoint-test'),
            'rule_key' => 'level_checkpoint_v1',
            'rule_hash' => hash('sha256', 'checkpoint-rule'),
            'attempt_number' => 1,
            'score' => 100,
            'pass_threshold' => 80,
            'passed' => true,
            'item_results_json' => '[]',
            'idempotency_key' => (string) Str::ulid(),
            'created_at' => $now,
        ]);
    }

    private function insertCompletion(
        string $organizationId,
        string $userId,
        string $membershipId,
        int $levelNumber,
        string $partId,
    ): void {
        $now = CarbonImmutable::now();
        DB::table('level_session_completions')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'membership_id' => $membershipId,
            'level_number' => $levelNumber,
            'part_id' => $partId,
            'blueprint_id' => null,
            'blueprint_content_hash' => null,
            'completion_kind' => 'participation',
            'result_json' => null,
            'idempotency_key' => (string) Str::ulid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
