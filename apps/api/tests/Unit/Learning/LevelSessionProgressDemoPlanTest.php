<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Entitlements\DirectPlanDefaults;
use App\Learning\LevelSessions\LevelSessionProgressService;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextSource;
use Tests\TestCase;

final class LevelSessionProgressDemoPlanTest extends TestCase
{
    public function test_demo_plan_opens_levels_one_and_two_without_prior_completion(): void
    {
        $progress = $this->app->make(LevelSessionProgressService::class);
        $context = new OrganizationContext(
            organizationId: '01J00000000000000000000001',
            actorUserId: '01J00000000000000000000002',
            membershipId: '01J00000000000000000000003',
            source: OrganizationContextSource::Membership,
        );
        $snapshot = [
            'is_demo' => true,
            'max_level_number' => DirectPlanDefaults::DEMO_MAX_LEVEL,
            'allowed_session_parts' => DirectPlanDefaults::DEMO_SESSION_PARTS,
        ];

        $levelOne = $progress->projection(
            $context,
            '01J00000000000000000000004',
            1,
            'available',
            $snapshot,
        );
        $levelTwo = $progress->projection(
            $context,
            '01J00000000000000000000004',
            2,
            'available',
            $snapshot,
        );

        self::assertSame('allowed', $levelOne['progress_access']);
        self::assertSame('allowed', $levelTwo['progress_access']);
        self::assertNull($levelTwo['unlock_after_level_number']);

        $progress->assertUnlocked(
            $context,
            '01J00000000000000000000004',
            2,
            $snapshot,
        );
        $this->addToAssertionCount(1);
    }
}
