<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Avatar\AvatarVisemeTimelineEstimator;
use Tests\TestCase;

final class AvatarVisemeTimelineEstimatorTest extends TestCase
{
    public function test_builds_duration_aligned_frames_for_english_text(): void
    {
        $wav = self::wav(durationMs: 500);
        $timeline = (new AvatarVisemeTimelineEstimator)->estimate(
            'Hello Swathi',
            $wav,
        );

        $this->assertNotNull($timeline);
        $this->assertSame('text-duration-estimator-v1', $timeline->source);
        $this->assertSame(500, $timeline->durationMs);
        $this->assertNotEmpty($timeline->frames);
        $this->assertSame(0, $timeline->frames[0]['offset_ms']);
        $this->assertGreaterThanOrEqual(0, $timeline->frames[0]['viseme_id']);
        $this->assertLessThanOrEqual(21, $timeline->frames[0]['viseme_id']);
        $this->assertSame(
            0,
            $timeline->frames[array_key_last($timeline->frames)]['viseme_id'],
        );
    }

    public function test_returns_null_without_playable_audio(): void
    {
        $timeline = (new AvatarVisemeTimelineEstimator)->estimate('Hello', '');

        $this->assertNull($timeline);
    }

    private static function wav(int $durationMs = 100): string
    {
        $sampleRate = 16_000;
        $channels = 1;
        $bits = 16;
        $bytesPerSample = intdiv($bits, 8);
        $dataSize = (int) floor(
            $sampleRate * $channels * $bytesPerSample * $durationMs / 1000,
        );
        if ($dataSize % 2 !== 0) {
            $dataSize--;
        }
        $dataSize = max(2, $dataSize);
        $blockAlign = $channels * $bytesPerSample;
        $byteRate = $sampleRate * $blockAlign;
        $format = 'fmt '
            .pack('VvvVVvv', 16, 1, $channels, $sampleRate, $byteRate, $blockAlign, $bits);
        $data = 'data'.pack('V', $dataSize).str_repeat("\0", $dataSize);
        $body = 'WAVE'.$format.$data;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }
}
