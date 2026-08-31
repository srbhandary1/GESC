<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation\Voice\Providers\Azure;

use App\Conversation\Voice\Providers\Azure\AzureWavVisemeExtractor;
use PHPUnit\Framework\TestCase;

final class AzureWavVisemeExtractorTest extends TestCase
{
    private AzureWavVisemeExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new AzureWavVisemeExtractor;
    }

    public function test_extract_from_invalid_wav_stream_returns_empty(): void
    {
        $this->assertSame([], $this->extractor->extractFromWavStream(''));
        $this->assertSame([], $this->extractor->extractFromWavStream('invalid'));
        $this->assertSame([], $this->extractor->extractFromWavStream('RIFF'));
    }

    public function test_extract_from_wav_without_visx_chunk_returns_empty(): void
    {
        // Minimal valid WAV with no visx chunk
        $wav = 'RIFF'.pack('V', 36).'WAVE'.
                'fmt '.pack('V', 16).pack('VvvVvv', 1, 1, 16000, 32000, 2, 16).
                'data'.pack('V', 0);

        $result = $this->extractor->extractFromWavStream($wav);
        $this->assertSame([], $result);
    }

    public function test_derives_bounded_mouth_cues_from_pcm_audio_without_provider_visemes(): void
    {
        $sampleRate = 24000;
        $samples = [];
        foreach ([0, 1800, 8500, 1800, 0] as $amplitude) {
            for ($sample = 0; $sample < 1920; $sample++) {
                $samples[] = $amplitude;
            }
        }

        $pcm = pack('v*', ...$samples);
        $wav = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVE'.
            'fmt '.pack('V', 16).pack(
                'vvVVvv',
                1,
                1,
                $sampleRate,
                $sampleRate * 2,
                2,
                16,
            ).
            'data'.pack('V', strlen($pcm)).$pcm;

        $result = $this->extractor->extractFromWavStream($wav);

        $this->assertSame([
            ['mouth_shape' => 'rest', 'timestamp_ms' => 0],
            ['mouth_shape' => 'slightly_open', 'timestamp_ms' => 80],
            ['mouth_shape' => 'wide', 'timestamp_ms' => 160],
            ['mouth_shape' => 'slightly_open', 'timestamp_ms' => 240],
            ['mouth_shape' => 'rest', 'timestamp_ms' => 320],
        ], $result);
    }

    public function test_extract_viseme_chunk_with_valid_data(): void
    {
        // Build visx chunk: version=1, reserved=0, count=2
        $visxData = chr(1).chr(0).pack('v', 2).
                    // Viseme 1 @ 100ms
                    chr(1).pack('V', 100).
                    // Viseme 7 @ 200ms
                    chr(7).pack('V', 200);

        $wav = 'RIFF'.pack('V', 12 + 8 + strlen($visxData) + 100).'WAVE'.
               'visx'.pack('V', strlen($visxData)).$visxData.
               'data'.pack('V', 100).str_repeat("\x00", 100);

        $result = $this->extractor->extractFromWavStream($wav);

        $this->assertCount(2, $result);
        $this->assertSame('ae', $result[0]['mouth_shape']);
        $this->assertSame(100, $result[0]['timestamp_ms']);
        $this->assertSame('ah', $result[1]['mouth_shape']);
        $this->assertSame(200, $result[1]['timestamp_ms']);
    }

    public function test_extract_sorts_visemes_by_timestamp(): void
    {
        // Build visx chunk with out-of-order visemes
        $visxData = chr(1).chr(0).pack('v', 3).
                    chr(5).pack('V', 500).
                    chr(2).pack('V', 100).
                    chr(3).pack('V', 300);

        $wav = 'RIFF'.pack('V', 12 + 8 + strlen($visxData) + 50).'WAVE'.
               'visx'.pack('V', strlen($visxData)).$visxData.
               'data'.pack('V', 50).str_repeat("\x00", 50);

        $result = $this->extractor->extractFromWavStream($wav);

        $this->assertCount(3, $result);
        $this->assertSame(100, $result[0]['timestamp_ms']);
        $this->assertSame(300, $result[1]['timestamp_ms']);
        $this->assertSame(500, $result[2]['timestamp_ms']);
    }

    public function test_extract_handles_all_viseme_ids(): void
    {
        // Build visx chunk with representative viseme IDs
        $visxData = chr(1).chr(0).pack('v', 3).
                    chr(0).pack('V', 0).    // silence/rest
                    chr(15).pack('V', 50).  // zh
                    chr(31).pack('V', 100);  // rr

        $wav = 'RIFF'.pack('V', 12 + 8 + strlen($visxData) + 50).'WAVE'.
               'visx'.pack('V', strlen($visxData)).$visxData.
               'data'.pack('V', 50).str_repeat("\x00", 50);

        $result = $this->extractor->extractFromWavStream($wav);

        $this->assertCount(3, $result);
        $this->assertSame('rest', $result[0]['mouth_shape']);
        $this->assertSame('zh', $result[1]['mouth_shape']);
        $this->assertSame('rr', $result[2]['mouth_shape']);
    }

    public function test_extract_unknown_viseme_id_defaults_to_rest(): void
    {
        $visxData = chr(1).chr(0).pack('v', 1).
                    chr(99).pack('V', 50);  // Unknown ID

        $wav = 'RIFF'.pack('V', 12 + 8 + strlen($visxData) + 50).'WAVE'.
               'visx'.pack('V', strlen($visxData)).$visxData.
               'data'.pack('V', 50).str_repeat("\x00", 50);

        $result = $this->extractor->extractFromWavStream($wav);

        $this->assertCount(1, $result);
        $this->assertSame('rest', $result[0]['mouth_shape']);
    }

    public function test_extract_invalid_visx_version_returns_empty(): void
    {
        // visx chunk with version 2 (unsupported)
        $visxData = chr(2).chr(0).pack('v', 1).
                    chr(1).pack('V', 100);

        $wav = 'RIFF'.pack('V', 12 + 8 + strlen($visxData) + 50).'WAVE'.
               'visx'.pack('V', strlen($visxData)).$visxData.
               'data'.pack('V', 50).str_repeat("\x00", 50);

        $result = $this->extractor->extractFromWavStream($wav);
        $this->assertSame([], $result);
    }
}
