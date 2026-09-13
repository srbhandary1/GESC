<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LevelSessions\LevelSessionProgressService;
use App\Models\Membership;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\TenancyIntegrationTestCase;

final class LevelSessionProgressServiceTest extends TenancyIntegrationTestCase
{
    public function test_level_two_stays_locked_until_level_one_is_fully_complete(): void
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
        $progress = app(LevelSessionProgressService::class);

        $initial = $this->contexts()->run(
            $learnerContext,
            fn (): array => $progress->projection(
                $learnerContext,
                (string) $learner->getKey(),
                2,
                'available',
            ),
        );
        self::assertSame('locked', $initial['progress_access']);
        self::assertSame(1, $initial['unlock_after_level_number']);

        $this->insertCompletion(
            (string) $organization->getKey(),
            (string) $learner->getKey(),
            (string) $learnerContext->membershipId,
            1,
            'introduction',
        );
        $this->insertCompletion(
            (string) $organization->getKey(),
            (string) $learner->getKey(),
            (string) $learnerContext->membershipId,
            1,
            'practice',
        );
        $this->insertCompletion(
            (string) $organization->getKey(),
            (string) $learner->getKey(),
            (string) $learnerContext->membershipId,
            1,
            'conversation',
        );

        $stillLocked = $this->contexts()->run(
            $learnerContext,
            fn (): array => $progress->projection(
                $learnerContext,
                (string) $learner->getKey(),
                2,
                'available',
            ),
        );
        self::assertSame('locked', $stillLocked['progress_access']);

        $this->insertCompletion(
            (string) $organization->getKey(),
            (string) $learner->getKey(),
            (string) $learnerContext->membershipId,
            1,
            'interaction',
        );

        $sessionsOnly = $this->contexts()->run(
            $learnerContext,
            fn (): array => $progress->projection(
                $learnerContext,
                (string) $learner->getKey(),
                2,
                'available',
            ),
        );
        self::assertSame('locked', $sessionsOnly['progress_access']);

        $this->insertCheckpointPass(
            (string) $organization->getKey(),
            (string) $learner->getKey(),
            (string) $learnerContext->membershipId,
            1,
        );

        $unlocked = $this->contexts()->run(
            $learnerContext,
            fn (): array => $progress->projection(
                $learnerContext,
                (string) $learner->getKey(),
                2,
                'available',
            ),
        );
        self::assertSame('allowed', $unlocked['progress_access']);
        self::assertNull($unlocked['unlock_after_level_number']);

        $recorded = $this->contexts()->run(
            $learnerContext,
            fn (): ?array => $progress->recordUnlockAfterLevelCompletion(
                $learnerContext,
                (string) $learner->getKey(),
                1,
            ),
        );
        self::assertNotNull($recorded);
        self::assertSame(2, $recorded['level_number']);
        self::assertFalse($recorded['replayed']);

        $replayed = $this->contexts()->run(
            $learnerContext,
            fn (): ?array => $progress->recordUnlockAfterLevelCompletion(
                $learnerContext,
                (string) $learner->getKey(),
                1,
            ),
        );
        self::assertNotNull($replayed);
        self::assertTrue($replayed['replayed']);
    }

    public function test_assert_unlocked_rejects_a_progress_locked_level(): void
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
        $progress = app(LevelSessionProgressService::class);

        try {
            $this->contexts()->run(
                $learnerContext,
                static function () use ($progress, $learnerContext, $learner): void {
                    $progress->assertUnlocked(
                        $learnerContext,
                        (string) $learner->getKey(),
                        3,
                    );
                },
            );
            self::fail('level three should remain progress locked');
        } catch (LearningOperationRejected $exception) {
            self::assertSame('level_progress_locked', $exception->getMessage());
        }
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
