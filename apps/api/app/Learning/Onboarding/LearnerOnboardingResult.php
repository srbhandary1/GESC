<?php

declare(strict_types=1);

namespace App\Learning\Onboarding;

final readonly class LearnerOnboardingResult
{
    public function __construct(
        public string $profileId,
        public string $goalId,
        public string $goalKey,
        public bool $replayed,
    ) {}
}
