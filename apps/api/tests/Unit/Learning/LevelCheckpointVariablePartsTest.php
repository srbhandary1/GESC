<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Learning\LevelSessions\LevelCheckpointService;
use App\Learning\LevelSessions\LevelSessionDeliveryService;
use App\Learning\LevelSessions\LevelSessionProgressService;
use App\Models\Membership;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\TenancyIntegrationTestCase;

final class LevelCheckpointVariablePartsTest extends TenancyIntegrationTestCase
{
    protected function tearDown(): void
    {
        config()->set('level_sessions.session_part_ids', [
            'introduction',
            'practice',
            'conversation',
            'interaction',
        ]);

        parent::tearDown();
    }

    public function test_checkpoint_ready_follows_configured_part_count_not_four(): void
    {
        config()->set('level_sessions.session_part_ids', [
            'introduction',
            'practice',
        ]);

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

        $checkpoints = app(LevelCheckpointService::class);
        $progress = app(LevelSessionProgressService::class);

        $this->insertCompletion(
            $organizationId,
            $userId,
            $membershipId,
            1,
            'introduction',
        );

        $notReady = $this->contexts()->run(
            $learnerContext,
            fn (): array => $checkpoints->projection(
                $learnerContext,
                $userId,
                1,
                'available',
            ),
        );
        self::assertSame('not_ready', $notReady['checkpoint_status']);
        self::assertFalse(
            $this->contexts()->run(
                $learnerContext,
                fn (): bool => $progress->isLevelFullyComplete(
                    $organizationId,
                    $userId,
                    1,
                ),
            ),
        );

        $this->insertCompletion(
            $organizationId,
            $userId,
            $membershipId,
            1,
            'practice',
        );

        $ready = $this->contexts()->run(
            $learnerContext,
            fn (): array => $checkpoints->projection(
                $learnerContext,
                $userId,
                1,
                'available',
            ),
        );
        self::assertSame('ready', $ready['checkpoint_status']);
        self::assertTrue(
            $this->contexts()->run(
                $learnerContext,
                fn (): bool => $progress->isLevelFullyComplete(
                    $organizationId,
                    $userId,
                    1,
                ),
            ),
        );
    }

    public function test_list_levels_exposes_configured_session_part_ids(): void
    {
        config()->set('level_sessions.session_part_ids', [
            'introduction',
            'practice',
        ]);

        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $learner = $this->createUser();
        [, $learnerContext] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );

        $levels = $this->contexts()->run(
            $learnerContext,
            fn (): array => app(LevelSessionDeliveryService::class)->listLevels(
                $learnerContext,
                $learner,
            ),
        );
        $levelOne = collect($levels)->firstWhere('level_number', 1);
        self::assertIsArray($levelOne);
        self::assertSame(
            ['introduction', 'practice'],
            $levelOne['session_part_ids'],
        );
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
