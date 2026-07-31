<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Benchmark;

use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\VoiceOperationRejected;
use RuntimeException;

final readonly class SyntheticAudioTransformer
{
    public function __construct(private PcmWavInspector $inspector) {}

    public function transform(
        string $wav,
        string $seed,
        string $condition,
    ): string {
        try {
            $metadata = $this->inspector->inspect($wav);
        } catch (VoiceOperationRejected) {
            throw new RuntimeException('Synthetic audio is malformed.');
        }

        if ($metadata->sampleRateHz !== 24000) {
            throw new RuntimeException('Synthetic audio has the wrong sample rate.');
        }

        $samples = $this->samples($wav);
        $normalized = $this->resample($samples, 24000, 16000);

        if ($condition === 'snr20') {
            $normalized = $this->addNoise($normalized, $seed, 20.0);
        } elseif ($condition === 'snr10_lowpass') {
            $normalized = $this->lowPass(
                $this->addNoise($normalized, $seed, 10.0),
                16000,
                3400.0,
            );
        } elseif ($condition !== 'clean') {
            throw new RuntimeException('Synthetic audio condition is invalid.');
        }

        return $this->encode($normalized, 16000);
    }

    /**
     * @return list<int>
     */
    private function samples(string $wav): array
    {
        $offset = 12;
        $length = strlen($wav);
        $data = null;

        while ($offset + 8 <= $length) {
            $id = substr($wav, $offset, 4);
            $sizeValue = unpack('Vsize', substr($wav, $offset + 4, 4));
            $size = is_array($sizeValue) ? ($sizeValue['size'] ?? null) : null;

            if (! is_int($size)) {
                throw new RuntimeException('Synthetic audio is malformed.');
            }

            if ($id === 'data') {
                $data = substr($wav, $offset + 8, $size);
            }

            $offset += 8 + $size + ($size % 2);
        }

        if (! is_string($data) || strlen($data) % 2 !== 0) {
            throw new RuntimeException('Synthetic audio is malformed.');
        }

        $unpacked = unpack('v*', $data);
        if (! is_array($unpacked)) {
            throw new RuntimeException('Synthetic audio is malformed.');
        }

        return array_values(array_map(
            static fn (int $value): int => $value > 32767
                ? $value - 65536
                : $value,
            $unpacked,
        ));
    }

    /**
     * @param  list<int>  $samples
     * @return list<int>
     */
    private function resample(
        array $samples,
        int $sourceRate,
        int $targetRate,
    ): array {
        $sourceCount = count($samples);
        $targetCount = (int) floor($sourceCount * $targetRate / $sourceRate);
        $output = [];

        for ($index = 0; $index < $targetCount; $index++) {
            $position = $index * $sourceRate / $targetRate;
            $left = (int) floor($position);
            $right = min($sourceCount - 1, $left + 1);
            $fraction = $position - $left;
            $output[] = $this->clip((int) round(
                $samples[$left] * (1.0 - $fraction)
                    + $samples[$right] * $fraction,
            ));
        }

        return $output;
    }

    /**
     * @param  list<int>  $samples
     * @return list<int>
     */
    private function addNoise(
        array $samples,
        string $seed,
        float $snrDecibels,
    ): array {
        $signalSquare = 0.0;
        foreach ($samples as $sample) {
            $signalSquare += $sample * $sample;
        }

        $signalRms = sqrt($signalSquare / max(1, count($samples)));
        if ($signalRms === 0.0) {
            return $samples;
        }

        $rawNoise = [];
        $noiseSquare = 0.0;
        foreach (array_keys($samples) as $index) {
            $bytes = hash('sha256', $seed.pack('N', $index), true);
            $value = unpack('vvalue', substr($bytes, 0, 2));
            $uniform = is_array($value)
                ? (((int) $value['value'] / 65535.0) * 2.0) - 1.0
                : 0.0;
            $rawNoise[] = $uniform;
            $noiseSquare += $uniform * $uniform;
        }

        $noiseRms = sqrt($noiseSquare / max(1, count($rawNoise)));
        $targetRms = $signalRms / (10 ** ($snrDecibels / 20.0));
        $scale = $noiseRms > 0.0 ? $targetRms / $noiseRms : 0.0;
        $output = [];

        foreach ($samples as $index => $sample) {
            $output[] = $this->clip(
                (int) round($sample + $rawNoise[$index] * $scale),
            );
        }

        return $output;
    }

    /**
     * @param  list<int>  $samples
     * @return list<int>
     */
    private function lowPass(
        array $samples,
        int $sampleRate,
        float $cutoff,
    ): array {
        $alpha = 1.0 - exp(-2.0 * M_PI * $cutoff / $sampleRate);
        $previous = 0.0;
        $output = [];

        foreach ($samples as $sample) {
            $previous += $alpha * ($sample - $previous);
            $output[] = $this->clip((int) round($previous));
        }

        return $output;
    }

    /**
     * @param  list<int>  $samples
     */
    private function encode(array $samples, int $sampleRate): string
    {
        $data = '';
        foreach ($samples as $sample) {
            $data .= pack('v', $sample < 0 ? $sample + 65536 : $sample);
        }

        $format = 'fmt '
            .pack(
                'VvvVVvv',
                16,
                1,
                1,
                $sampleRate,
                $sampleRate * 2,
                2,
                16,
            );
        $body = 'WAVE'.$format.'data'.pack('V', strlen($data)).$data;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }

    private function clip(int $sample): int
    {
        return max(-32768, min(32767, $sample));
    }
}
