<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Learning\Progress\LearnerProgressDashboardService;
use App\Learning\Review\ReviewDueBlockingService;
use App\Models\Membership;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Tests\Integration\TenancyIntegrationTestCase;

final class LearnerProgressDashboardInventoryTest extends TenancyIntegrationTestCase
{
    public function test_exposes_actionable_due_review_from_shared_inventory(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $learner = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['learning.participate'],
            'learner-progress-due-review',
        );

        $reviewDue = $this->createMock(ReviewDueBlockingService::class);
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

        $dashboard = $this->contexts()->run(
            $context,
            fn (): array => $this->app->make(LearnerProgressDashboardService::class)
                ->forLearner($learner),
        );

        self::assertNotNull($dashboard['actionable_due_review']);
        self::assertSame(
            '01M2D900000000000000000001',
            $dashboard['actionable_due_review']['id'],
        );
        self::assertSame(
            '01M2D900000000000000000010',
            $dashboard['actionable_due_review']['enrollment_id'],
        );
        self::assertSame(
            '01M2D900000000000000000003',
            $dashboard['actionable_due_review']['mission_id'],
        );
        self::assertSame(
            '01M2D900000000000000000002',
            $dashboard['actionable_due_review']['objective_id'],
        );
    }
}
