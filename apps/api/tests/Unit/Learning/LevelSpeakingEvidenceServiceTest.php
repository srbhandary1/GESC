<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Learning\LevelSessions\LevelSpeakingEvidenceService;
use App\Models\Membership;
use App\Models\Organization;
use Tests\Integration\TenancyIntegrationTestCase;

final class LevelSpeakingEvidenceServiceTest extends TenancyIntegrationTestCase
{
    public function test_projection_is_not_recorded_without_accepted_recordings(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $learner = $this->createUser();
        [, $context] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );

        $service = app(LevelSpeakingEvidenceService::class);
        $projection = $this->contexts()->run(
            $context,
            fn (): array => $service->projection($context, 1),
        );

        self::assertSame('not_recorded', $projection['evidence_state']);
        self::assertSame('unassessed', $projection['spoken_state']);
    }

    public function test_disabled_projection_stays_not_recorded(): void
    {
        config()->set('level_sessions.speaking_evidence.enabled', false);

        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $learner = $this->createUser();
        [, $context] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );

        $service = app(LevelSpeakingEvidenceService::class);
        $projection = $this->contexts()->run(
            $context,
            fn (): array => $service->projection($context, 1),
        );

        self::assertSame('not_recorded', $projection['evidence_state']);
    }
}
