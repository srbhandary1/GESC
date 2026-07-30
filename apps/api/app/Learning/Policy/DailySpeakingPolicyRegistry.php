<?php

declare(strict_types=1);

namespace App\Learning\Policy;

use App\Learning\Exceptions\LearningOperationRejected;

final class DailySpeakingPolicyRegistry
{
    public function default(): DailySpeakingPolicy
    {
        $version = config('learning.default_rule_version');

        if (! is_string($version) || $version === '') {
            throw new LearningOperationRejected;
        }

        return $this->forVersion($version);
    }

    public function forVersion(string $version): DailySpeakingPolicy
    {
        return match ($version) {
            'daily-speaking-v1' => new DailySpeakingPolicy(
                version: 'daily-speaking-v1',
                reviewHourLocal: 9,
            ),
            default => throw new LearningOperationRejected,
        };
    }
}
