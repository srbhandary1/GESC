<?php

declare(strict_types=1);

namespace Tests\Unit\Learning\Media;

use App\Learning\Media\VideoByteRange;
use App\Learning\Media\VideoRangeNotSatisfiable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VideoByteRangeTest extends TestCase
{
    private const string ETAG = '"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"';

    /**
     * @return iterable<string, array{?string, ?string, int, int, bool}>
     */
    public static function ranges(): iterable
    {
        yield 'no range' => [null, null, 0, 99, false];
        yield 'closed range' => ['bytes=10-19', null, 10, 19, true];
        yield 'open range' => ['bytes=90-', null, 90, 99, true];
        yield 'suffix range' => ['bytes=-10', null, 90, 99, true];
        yield 'large suffix' => ['bytes=-1000', null, 0, 99, true];
        yield 'large end is clamped' => [
            'bytes=95-999999999999999999999999',
            null,
            95,
            99,
            true,
        ];
        yield 'matching if-range' => [
            'bytes=1-2',
            self::ETAG,
            1,
            2,
            true,
        ];
        yield 'stale if-range returns full representation' => [
            'bytes=1-2',
            '"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"',
            0,
            99,
            false,
        ];
        yield 'weak if-range returns full representation' => [
            'bytes=1-2',
            'W/'.self::ETAG,
            0,
            99,
            false,
        ];
        yield 'multiple ranges are ignored' => [
            'bytes=0-1, 4-5',
            null,
            0,
            99,
            false,
        ];
        yield 'malformed reversed range is ignored' => [
            'bytes=9-4',
            null,
            0,
            99,
            false,
        ];
        yield 'unknown unit is ignored' => [
            'items=1-2',
            null,
            0,
            99,
            false,
        ];
    }

    #[DataProvider('ranges')]
    public function test_parses_one_bounded_range_or_ignores_unsupported_input(
        ?string $rangeHeader,
        ?string $ifRangeHeader,
        int $expectedStart,
        int $expectedEnd,
        bool $expectedPartial,
    ): void {
        $range = VideoByteRange::fromRequest(
            rangeHeader: $rangeHeader,
            ifRangeHeader: $ifRangeHeader,
            totalBytes: 100,
            etag: self::ETAG,
        );

        self::assertSame($expectedStart, $range->start);
        self::assertSame($expectedEnd, $range->end);
        self::assertSame($expectedPartial, $range->partial);
        self::assertSame($expectedEnd - $expectedStart + 1, $range->length());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsatisfiableRanges(): iterable
    {
        yield 'start equals length' => ['bytes=100-'];
        yield 'start exceeds length' => ['bytes=101-200'];
        yield 'huge start exceeds length' => [
            'bytes=999999999999999999999999-',
        ];
        yield 'zero suffix' => ['bytes=-0'];
    }

    #[DataProvider('unsatisfiableRanges')]
    public function test_rejects_a_valid_but_unsatisfiable_range(
        string $rangeHeader,
    ): void {
        $this->expectException(VideoRangeNotSatisfiable::class);

        VideoByteRange::fromRequest(
            rangeHeader: $rangeHeader,
            ifRangeHeader: null,
            totalBytes: 100,
            etag: self::ETAG,
        );
    }
}
