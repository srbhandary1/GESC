<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Learning\Review\ReviewDueBlockingService;
use App\Models\Membership;
use App\Models\Organization;
use Tests\Integration\TenancyIntegrationTestCase;

final class ReviewDueBlockingServiceTest extends TenancyIntegrationTestCase
{
    public function test_returns_null_when_blocking_is_disabled(): void
    {
        config()->set('level_sessions.review_due_blocking.enabled', false);

        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $learner = $this->createUser();
        [, $context] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );

        $service = app(ReviewDueBlockingService::class);
        self::assertNull($service->firstDueIncompleteReview($context));
        self::assertFalse($service->hasDueIncompleteReview($context));
    }

    public function test_rule_key_comes_from_configuration(): void
    {
        config()->set(
            'level_sessions.review_due_blocking.rule_key',
            'review_due_blocking_test',
        );

        $service = app(ReviewDueBlockingService::class);
        self::assertSame('review_due_blocking_test', $service->ruleKey());
    }
}
