<?php

declare(strict_types=1);

namespace Tests\Unit\Operator;

use App\Models\Membership;
use App\Models\Organization;
use App\Operator\OperatorLearningMetricsQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\TenancyIntegrationTestCase;

final class OperatorLearningMetricsQueryTest extends TenancyIntegrationTestCase
{
    public function test_returns_content_free_learning_metrics(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $learner = $this->createUser();
        [$membership] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );
        $organizationId = (string) $organization->getKey();
        $membershipId = (string) $membership->getKey();
        $now = now();

        DB::table('level_session_completions')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'user_id' => $learner->getKey(),
            'membership_id' => $membershipId,
            'level_number' => 1,
            'part_id' => 'introduction',
            'blueprint_id' => (string) Str::ulid(),
            'blueprint_content_hash' => hash('sha256', 'bp'),
            'completion_kind' => 'participation',
            'result_json' => null,
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $metrics = app(OperatorLearningMetricsQuery::class)
            ->summarize($organizationId);

        self::assertSame(1, $metrics['stage_completion_count']);
        self::assertArrayNotHasKey('transcript', $metrics);
        self::assertArrayNotHasKey('learner_membership_id', $metrics);
    }
}
