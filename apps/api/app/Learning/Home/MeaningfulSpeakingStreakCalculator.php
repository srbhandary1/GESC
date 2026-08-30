<?php

declare(strict_types=1);

namespace App\Learning\Home;

use Carbon\CarbonImmutable;

final class MeaningfulSpeakingStreakCalculator
{
    /**
     * @param  list<string>  $dates
     */
    public function count(
        array $dates,
        CarbonImmutable $now,
        string $timezone,
    ): int {
        $today = $now->setTimezone($timezone)->startOfDay();
        $set = array_fill_keys($dates, true);
        $cursor = isset($set[$today->toDateString()])
            ? $today
            : $today->subDay();
        $count = 0;

        while (isset($set[$cursor->toDateString()])) {
            $count++;
            $cursor = $cursor->subDay();
        }

        return $count;
    }
}
