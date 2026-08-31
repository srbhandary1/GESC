<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers\Azure;

/**
 * Extracts viseme data from Azure Speech Synthesis WAV stream RIFF chunks.
 *
 * Azure Speech TTS can embed synchronization metadata (including viseme events)
 * directly in the WAV file as additional RIFF chunks when configured.
 * This allows viseme-driven animation without WebSocket overhead for
 * typical short-form Level speech (< 60 seconds).
 *
 * RIFF format:
 *   - Standard WAV RIFF header + audio data
 *   - Optional custom chunks after audio:
 *     * "visx" chunk: Viseme synchronization data
 *     * "pbks" chunk: Phoneme breaks
 */
final readonly class AzureWavVisemeExtractor
{
    private const int RIFF_CHUNK_HEADER_SIZE = 8;

    private const int RIFF_FILE_HEADER_SIZE = 12;

    /**
     * Extract viseme events from WAV RIFF chunks.
     *
     * @return list<array{mouth_shape: string, timestamp_ms: int}>
     */
    public function extractFromWavStream(string $wavData): array
    {
        if (strlen($wavData) < self::RIFF_FILE_HEADER_SIZE) {
            return [];
        }

        $visemeChunk = $this->findRiffChunk($wavData, 'visx');
        if ($visemeChunk !== null) {
            $visemes = $this->parseVisemeChunk($visemeChunk);
            if ($visemes !== []) {
                return $visemes;
            }
        }

        return $this->extractAudioDrivenMouthCues($wavData);
    }

    /**
     * Locate a RIFF subchunk by ID (4-byte FourCC code).
     *
     * Standard RIFF structure:
     *   0-3:   "RIFF"
     *   4-7:   file size (little-endian)
     *   8-11:  "WAVE"
     *   12+:   subchunks (id, size, data)
     */
    private function findRiffChunk(string $wavData, string $chunkId): ?string
    {
        if (strlen($chunkId) !== 4) {
            return null;
        }

        // Verify RIFF header
        if (substr($wavData, 0, 4) !== 'RIFF' || substr($wavData, 8, 4) !== 'WAVE') {
            return null;
        }

        // Scan for target chunk
        $offset = 12;
        $fileSize = strlen($wavData);

        while ($offset + self::RIFF_CHUNK_HEADER_SIZE <= $fileSize) {
            $id = substr($wavData, $offset, 4);
            $sizeBytes = substr($wavData, $offset + 4, 4);

            if (strlen($sizeBytes) < 4) {
                break;
            }

            $size = unpack('V', $sizeBytes)[1] ?? 0;
            $offset += self::RIFF_CHUNK_HEADER_SIZE;

            if ($id === $chunkId) {
                $end = $offset + $size;
                if ($end <= $fileSize) {
                    return substr($wavData, $offset, $size);
                }

                return null;
            }

            // Advance to next chunk (chunks are 2-byte aligned)
            $offset += $size;
            if ($size % 2 === 1) {
                $offset += 1;
            }
        }

        return null;
    }

    /**
     * Parse viseme chunk data.
     *
     * Azure visx chunk format (hypothetical; verify with vendor docs):
     * - Version (1 byte)
     * - Reserved (1 byte)
     * - Count (2 bytes, little-endian)
     * - Viseme records (each):
     *   * VisemeId (1 byte)
     *   * Offset (4 bytes, little-endian, milliseconds)
     *
     * @return list<array{mouth_shape: string, timestamp_ms: int}>
     */
    private function parseVisemeChunk(string $chunkData): array
    {
        if (strlen($chunkData) < 4) {
            return [];
        }

        $version = ord($chunkData[0]);
        if ($version !== 1) {
            return [];
        }

        $countBytes = substr($chunkData, 2, 2);
        if (strlen($countBytes) < 2) {
            return [];
        }

        $count = unpack('v', $countBytes)[1] ?? 0;
        if ($count === 0 || $count > 1000) {
            return [];
        }

        $offset = 4;
        $visemes = [];
        $ipaMap = $this->getIpaVisemeMap();

        for ($i = 0; $i < $count; $i++) {
            if ($offset + 5 > strlen($chunkData)) {
                break;
            }

            $visemeId = ord($chunkData[$offset]);
            $offsetBytes = substr($chunkData, $offset + 1, 4);

            if (strlen($offsetBytes) < 4) {
                break;
            }

            $timestampMs = unpack('V', $offsetBytes)[1] ?? 0;
            $offset += 5;

            $mouthShape = $ipaMap[$visemeId] ?? 'rest';
            $visemes[] = [
                'mouth_shape' => $mouthShape,
                'timestamp_ms' => max(0, $timestampMs),
            ];
        }

        // Ensure sorted by timestamp
        usort($visemes, static function (array $a, array $b): int {
            return $a['timestamp_ms'] <=> $b['timestamp_ms'];
        });

        return $visemes;
    }

    /**
     * Azure's REST TTS endpoint returns ordinary PCM WAV without synthesis
     * events. Derive bounded, content-free mouth openness cues from the audio
     * envelope so the client can still synchronize the local rig to playback.
     *
     * @return list<array{mouth_shape: string, timestamp_ms: int}>
     */
    private function extractAudioDrivenMouthCues(string $wavData): array
    {
        $format = $this->findRiffChunk($wavData, 'fmt ');
        $audio = $this->findRiffChunk($wavData, 'data');

        if ($format === null || strlen($format) < 16 || $audio === null) {
            return [];
        }

        $metadata = unpack(
            'vaudio_format/vchannels/Vsample_rate',
            substr($format, 0, 8),
        );
        $bitsPerSample = unpack('v', substr($format, 14, 2))[1] ?? 0;
        $sampleRate = $metadata['sample_rate'] ?? 0;

        if (
            ($metadata['audio_format'] ?? 0) !== 1
            || ($metadata['channels'] ?? 0) !== 1
            || $bitsPerSample !== 16
            || $sampleRate < 8000
            || $sampleRate > 48000
            || strlen($audio) < 2
        ) {
            return [];
        }

        $windowSamples = max(1, (int) round($sampleRate * 0.08));
        $totalSamples = intdiv(strlen($audio), 2);
        $windows = [];

        for ($start = 0; $start < $totalSamples; $start += $windowSamples) {
            $end = min($totalSamples, $start + $windowSamples);
            $sum = 0;
            $count = 0;

            // Sampling every fourth PCM frame is sufficient for the envelope
            // and keeps extraction bounded for the maximum accepted WAV.
            for ($sample = $start; $sample < $end; $sample += 4) {
                $unsigned = unpack('v', substr($audio, $sample * 2, 2))[1] ?? 0;
                $signed = $unsigned >= 32768 ? $unsigned - 65536 : $unsigned;
                $sum += abs($signed);
                $count++;
            }

            $windows[] = [
                'timestamp_ms' => (int) round($start * 1000 / $sampleRate),
                'energy' => $count === 0 ? 0 : (int) round($sum / $count),
            ];
        }

        $energies = array_column($windows, 'energy');
        if ($energies === []) {
            return [];
        }

        sort($energies);
        $noiseFloor = $energies[(int) floor((count($energies) - 1) * 0.15)] ?? 0;
        $peak = $energies[array_key_last($energies)] ?? 0;
        $range = $peak - $noiseFloor;

        if ($range < 48) {
            return [];
        }

        $cues = [];
        $previous = null;
        foreach ($windows as $window) {
            $normalized = max(
                0.0,
                min(1.0, ($window['energy'] - $noiseFloor) / $range),
            );
            $shape = match (true) {
                $normalized <= 0.08 => 'rest',
                $normalized <= 0.28 => 'slightly_open',
                $normalized <= 0.62 => 'open',
                default => 'wide',
            };

            if ($shape === $previous) {
                continue;
            }

            $cues[] = [
                'mouth_shape' => $shape,
                'timestamp_ms' => $window['timestamp_ms'],
            ];
            $previous = $shape;
        }

        $durationMs = (int) round($totalSamples * 1000 / $sampleRate);
        if (($cues[array_key_last($cues)]['mouth_shape'] ?? null) !== 'rest') {
            $cues[] = [
                'mouth_shape' => 'rest',
                'timestamp_ms' => $durationMs,
            ];
        }

        return $cues;
    }

    /**
     * IPA viseme ID to mouth shape mapping (Azure standard).
     *
     * @return array<int, string>
     */
    private function getIpaVisemeMap(): array
    {
        return [
            0 => 'rest',      1 => 'ae',        2 => 'eh',        3 => 'ih',
            4 => 'oh',        5 => 'ooh',       6 => 'er',        7 => 'ah',
            8 => 'ee',        9 => 'ow',        10 => 'ou',       11 => 'y',
            12 => 'ss',       13 => 'z',        14 => 'sh',       15 => 'zh',
            16 => 'ch',       17 => 'jh',       18 => 'th',       19 => 'dh',
            20 => 'nn',       21 => 'll',       22 => 'kk',       23 => 'gg',
            24 => 'ff',       25 => 'vv',       26 => 'th',       27 => 'dd',
            28 => 'pp',       29 => 'bb',       30 => 'mm',       31 => 'rr',
        ];
    }
}
