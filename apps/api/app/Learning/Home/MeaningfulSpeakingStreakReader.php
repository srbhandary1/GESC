<?php

declare(strict_types=1);

namespace App\Learning\Home;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class MeaningfulSpeakingStreakReader
{
    public function __construct(
        private MeaningfulSpeakingDayProjector $projector,
        private MeaningfulSpeakingStreakCalculator $calculator,
    ) {}

    /**
     * @return array{count: int, as_of_date: string, timezone: string}
     */
    public function read(
        string $organizationId,
        string $membershipId,
        ?CarbonImmutable $now = null,
    ): array {
        $this->projector->reconcileLearner($organizationId, $membershipId);
        $timezone = DB::table('organizations')
            ->where('id', $organizationId)
            ->value('timezone');
        $timezone = is_string($timezone) && $timezone !== ''
            ? $timezone
            : 'Asia/Kolkata';
        $today = ($now ?? CarbonImmutable::now())->setTimezone($timezone)->startOfDay();
        $dates = DB::table('meaningful_speaking_days')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->orderByDesc('speaking_date')
            ->pluck('speaking_date')
            ->map(static fn (mixed $date): string => (string) $date)
            ->all();

        return [
            'count' => $this->calculator->count(
                $dates,
                $now ?? CarbonImmutable::now(),
                $timezone,
            ),
            'as_of_date' => $today->toDateString(),
            'timezone' => $timezone,
        ];
    }
}
