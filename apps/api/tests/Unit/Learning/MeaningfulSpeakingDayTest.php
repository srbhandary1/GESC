<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Learning\Home\MeaningfulSpeakingDayPredicate;
use App\Learning\Home\MeaningfulSpeakingStreakCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class MeaningfulSpeakingDayTest extends TestCase
{
    public function test_completed_turn_with_reply_and_no_failure_qualifies(): void
    {
        self::assertTrue((new MeaningfulSpeakingDayPredicate)->qualifies(
            $this->turn('2026-08-30T18:45:00Z', str_repeat('a', 64), null),
        ));
    }

    /**
     * @return iterable<string, array{stdClass}>
     */
    public static function excludedActivities(): iterable
    {
        yield 'sign-in only' => [self::row(null, null, null)];
        yield 'application open only' => [self::row(null, null, null)];
        yield 'lesson-audio playback only' => [self::row(null, null, null)];
        yield 'offline-bundle download only' => [self::row(null, null, null)];
        yield 'MCQ/check submission only' => [self::row(null, null, null)];
        yield 'turn failed before reply' => [
            self::row('2026-08-30T18:45:00Z', null, 'model'),
        ];
    }

    #[DataProvider('excludedActivities')]
    public function test_excluded_activity_does_not_qualify(stdClass $row): void
    {
        self::assertFalse((new MeaningfulSpeakingDayPredicate)->qualifies($row));
    }

    public function test_streak_crosses_the_organization_timezone_boundary(): void
    {
        $calculator = new MeaningfulSpeakingStreakCalculator;
        $now = CarbonImmutable::parse('2026-08-30T18:45:00Z');

        self::assertSame(2, $calculator->count(
            ['2026-08-31', '2026-08-30'],
            $now,
            'Asia/Kolkata',
        ));
        self::assertSame(1, $calculator->count(
            ['2026-08-30'],
            $now,
            'UTC',
        ));
    }

    public function test_late_completion_uses_historical_completed_time_not_sync_time(): void
    {
        $completed = CarbonImmutable::parse('2026-08-29T19:00:00Z');
        $synced = CarbonImmutable::parse('2026-08-31T08:00:00Z');

        self::assertSame('2026-08-30', $completed
            ->setTimezone('Asia/Kolkata')
            ->toDateString());
        self::assertNotSame(
            $synced->setTimezone('Asia/Kolkata')->toDateString(),
            $completed->setTimezone('Asia/Kolkata')->toDateString(),
        );
    }

    private function turn(
        ?string $completedAt,
        ?string $replyDigest,
        ?string $failedStage,
    ): stdClass {
        return self::row($completedAt, $replyDigest, $failedStage);
    }

    private static function row(
        ?string $completedAt,
        ?string $replyDigest,
        ?string $failedStage,
    ): stdClass {
        return (object) [
            'completed_at' => $completedAt,
            'assistant_reply_digest' => $replyDigest,
            'failed_stage' => $failedStage,
        ];
    }
}
