<?php

declare(strict_types=1);

namespace App\Learning\Policy;

final readonly class DailySpeakingPolicy
{
    public function __construct(
        public string $version,
        public int $reviewHourLocal,
    ) {}

    public function knowledgeScore(bool $correct): int
    {
        return $correct ? 100 : 0;
    }

    public function knowledgeState(bool $correct): string
    {
        return $correct ? 'mastered' : 'not_mastered';
    }

    public function resultKey(bool $correct): string
    {
        return $correct ? 'mission.passed' : 'mission.retry';
    }

    public function scheduleKind(bool $correct): string
    {
        return $correct ? 'delayed_review' : 'immediate_retry';
    }
}
