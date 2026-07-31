<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\VoiceOperationRejected;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PcmWavInspectorTest extends TestCase
{
    public function test_accepts_bounded_mono_pcm_sixteen_bit_wav(): void
    {
        $metadata = (new PcmWavInspector)->inspect(self::wav());

        $this->assertSame('audio/wav', $metadata->mimeType);
        $this->assertSame('pcm_s16le', $metadata->codec);
        $this->assertSame(16_000, $metadata->sampleRateHz);
        $this->assertSame(1, $metadata->channels);
        $this->assertSame(100, $metadata->durationMs);
    }

    #[DataProvider('invalidWavProvider')]
    public function test_rejects_malformed_or_unreviewed_audio_shapes(
        string $bytes,
    ): void {
        $this->expectException(VoiceOperationRejected::class);

        (new PcmWavInspector)->inspect($bytes);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidWavProvider(): iterable
    {
        $valid = self::wav();

        yield 'not a RIFF container' => ['not-a-wave-file'];
        yield 'truncated container' => [substr($valid, 0, -1)];
        yield 'stereo is not accepted' => [self::wav(channels: 2)];
        yield 'eight-bit PCM is not accepted' => [self::wav(bits: 8)];
        yield 'sample rate is outside bounds' => [self::wav(sampleRate: 96_000)];
        yield 'unknown ancillary chunk is rejected' => [
            self::wav(extraChunk: 'fact'.pack('V', 2)."\0\0"),
        ];
    }

    private static function wav(
        int $sampleRate = 16_000,
        int $channels = 1,
        int $bits = 16,
        string $extraChunk = '',
    ): string {
        $bytesPerSample = intdiv($bits, 8);
        $dataSize = max(2, $sampleRate * $channels * $bytesPerSample / 10);
        $dataSize = (int) $dataSize;
        $blockAlign = $channels * $bytesPerSample;
        $byteRate = $sampleRate * $blockAlign;
        $format = 'fmt '
            .pack('VvvVVvv', 16, 1, $channels, $sampleRate, $byteRate, $blockAlign, $bits);
        $data = 'data'.pack('V', $dataSize).str_repeat("\0", $dataSize);
        $body = 'WAVE'.$format.$extraChunk.$data;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }
}
