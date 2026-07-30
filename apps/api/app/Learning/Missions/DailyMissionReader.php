<?php

declare(strict_types=1);

namespace App\Learning\Missions;

use App\Models\User;

interface DailyMissionReader
{
    public function resolve(
        User $learner,
        string $enrollmentId,
        string $missionId,
    ): DailyMissionDefinition;

    public function first(
        User $learner,
        string $enrollmentId,
    ): ?DailyMissionDefinition;
}
