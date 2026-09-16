<?php

declare(strict_types=1);

namespace App\Learning\Review;

use Carbon\CarbonImmutable;

final readonly class DelayedReviewAttemptWindow
{
    public const string NOT_ELIGIBLE = 'not_eligible';

    public const string REVIEWED_ON_TIME = 'reviewed_on_time';

    public const string REVIEWED_LATE = 'reviewed_late';

    public function classify(
        CarbonImmutable $dueAt,
        CarbonImmutable $attemptedAt,
        string $occurrenceTimezone,
    ): string {
        if ($attemptedAt->lt($dueAt)) {
            return self::NOT_ELIGIBLE;
        }

        $dueDate = $dueAt->setTimezone($occurrenceTimezone)->toDateString();
        $attemptedDate = $attemptedAt
            ->setTimezone($occurrenceTimezone)
            ->toDateString();

        return $attemptedDate === $dueDate
            ? self::REVIEWED_ON_TIME
            : self::REVIEWED_LATE;
    }
}
