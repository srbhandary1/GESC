<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

final class PcmWavInspector
{
    public function inspect(string $bytes): WavMetadata
    {
        $length = strlen($bytes);
        $maximum = (int) config('voice.maximum_bytes', 10 * 1024 * 1024);

        if (
            $length < 44
            || $length > $maximum
            || substr($bytes, 0, 4) !== 'RIFF'
            || substr($bytes, 8, 4) !== 'WAVE'
            || $this->u32($bytes, 4) !== $length - 8
        ) {
            throw new VoiceOperationRejected;
        }

        $offset = 12;
        $format = null;
        $dataSize = null;

        while ($offset + 8 <= $length) {
            $id = substr($bytes, $offset, 4);
            $size = $this->u32($bytes, $offset + 4);
            $start = $offset + 8;
            $end = $start + $size;

            if ($size < 0 || $end > $length) {
                throw new VoiceOperationRejected;
            }

            if ($id === 'fmt ') {
                if ($format !== null || $size < 16) {
                    throw new VoiceOperationRejected;
                }

                $format = [
                    'audio_format' => $this->u16($bytes, $start),
                    'channels' => $this->u16($bytes, $start + 2),
                    'sample_rate' => $this->u32($bytes, $start + 4),
                    'byte_rate' => $this->u32($bytes, $start + 8),
                    'block_align' => $this->u16($bytes, $start + 12),
                    'bits' => $this->u16($bytes, $start + 14),
                ];
            } elseif ($id === 'data') {
                if ($dataSize !== null || $size === 0) {
                    throw new VoiceOperationRejected;
                }

                $dataSize = $size;
            } elseif (! in_array($id, ['JUNK', 'LIST'], true)) {
                throw new VoiceOperationRejected;
            }

            $offset = $end + ($size % 2);
        }

        if (
            $offset !== $length
            || ! is_array($format)
            || ! is_int($dataSize)
            || $format['audio_format'] !== 1
            || $format['channels'] !== 1
            || $format['bits'] !== 16
            || $format['sample_rate'] < 8000
            || $format['sample_rate'] > 48000
            || $format['block_align'] !== 2
            || $format['byte_rate'] !== $format['sample_rate'] * 2
            || $dataSize % 2 !== 0
        ) {
            throw new VoiceOperationRejected;
        }

        $durationMs = (int) round(
            ($dataSize / $format['byte_rate']) * 1000,
        );

        if (
            $durationMs < 1
            || $durationMs > (int) config(
                'voice.maximum_duration_ms',
                120_000,
            )
        ) {
            throw new VoiceOperationRejected;
        }

        return new WavMetadata(
            byteSize: $length,
            durationMs: $durationMs,
            sampleRateHz: $format['sample_rate'],
            channels: $format['channels'],
        );
    }

    private function u16(string $bytes, int $offset): int
    {
        $value = unpack('vvalue', substr($bytes, $offset, 2));

        if (! is_array($value) || ! is_int($value['value'] ?? null)) {
            throw new VoiceOperationRejected;
        }

        return $value['value'];
    }

    private function u32(string $bytes, int $offset): int
    {
        $value = unpack('Vvalue', substr($bytes, $offset, 4));

        if (! is_array($value) || ! is_int($value['value'] ?? null)) {
            throw new VoiceOperationRejected;
        }

        return $value['value'];
    }
}
