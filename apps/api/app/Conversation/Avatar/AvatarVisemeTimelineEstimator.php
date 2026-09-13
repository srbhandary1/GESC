<?php

declare(strict_types=1);

namespace App\Conversation\Avatar;

use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\VoiceOperationRejected;

final class AvatarVisemeTimelineEstimator
{
    public function __construct(
        private PcmWavInspector $wavInspector = new PcmWavInspector,
    ) {}

    public function estimate(string $text, string $audioBytes): ?AvatarVisemeTimeline
    {
        if ($audioBytes === '') {
            return null;
        }

        try {
            $metadata = $this->wavInspector->inspect($audioBytes);
        } catch (VoiceOperationRejected) {
            return null;
        }

        $segments = $this->segments($text);
        if ($segments === []) {
            return null;
        }

        $totalWeight = array_sum(array_column($segments, 'weight'));
        if ($totalWeight <= 0.0) {
            return null;
        }

        $durationMs = $metadata->durationMs;
        $cursor = 0;
        $frames = [];

        foreach ($segments as $index => $segment) {
            if ($index === 0) {
                $frames[] = [
                    'offset_ms' => 0,
                    'viseme_id' => $segment['viseme_id'],
                ];
            } else {
                $frames[] = [
                    'offset_ms' => min($cursor, max(0, $durationMs - 1)),
                    'viseme_id' => $segment['viseme_id'],
                ];
            }

            $cursor += (int) round(
                ($segment['weight'] / $totalWeight) * $durationMs,
            );
        }

        $lastOffset = $frames[array_key_last($frames)]['offset_ms'];
        if ($lastOffset < max(0, $durationMs - 1)) {
            $frames[] = [
                'offset_ms' => max(0, $durationMs - 1),
                'viseme_id' => 0,
            ];
        }

        return new AvatarVisemeTimeline(
            source: 'text-duration-estimator-v1',
            durationMs: $durationMs,
            frames: $frames,
        );
    }

    /**
     * @return list<array{viseme_id: int, weight: float}>
     */
    private function segments(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '') {
            return [];
        }

        $letters = preg_replace('/[^a-z]+/', '', $normalized) ?? '';
        if ($letters === '') {
            return [];
        }

        $segments = [];
        $length = strlen($letters);
        $index = 0;

        while ($index < $length) {
            $pair = $index + 1 < $length
                ? substr($letters, $index, 2)
                : '';
            $char = $letters[$index];

            if ($pair !== '' && isset($this->digraphVisemes()[$pair])) {
                $segments[] = [
                    'viseme_id' => $this->digraphVisemes()[$pair],
                    'weight' => $this->weightForViseme(
                        $this->digraphVisemes()[$pair],
                    ),
                ];
                $index += 2;

                continue;
            }

            $segments[] = [
                'viseme_id' => $this->visemeForChar($char),
                'weight' => $this->weightForViseme($this->visemeForChar($char)),
            ];
            $index += 1;
        }

        return $segments;
    }

    private function visemeForChar(string $char): int
    {
        return match ($char) {
            'a' => 2,
            'e' => 4,
            'i', 'y' => 6,
            'o' => 8,
            'u' => 7,
            'b', 'p', 'm' => 21,
            'f', 'v' => 18,
            'w' => 7,
            'h' => 12,
            'r' => 13,
            'l' => 14,
            's', 'z' => 15,
            'c' => 20,
            'j' => 16,
            'd', 't', 'n' => 19,
            'k', 'g', 'q', 'x' => 20,
            default => 0,
        };
    }

    /**
     * @return array<string, int>
     */
    private function digraphVisemes(): array
    {
        return [
            'th' => 17,
            'sh' => 16,
            'ch' => 16,
            'ow' => 8,
            'aw' => 9,
            'oy' => 10,
            'ay' => 11,
            'ee' => 6,
            'oo' => 7,
            'ar' => 2,
            'or' => 8,
            'er' => 5,
        ];
    }

    private function weightForViseme(int $visemeId): float
    {
        if ($visemeId === 0) {
            return 0.5;
        }

        return in_array($visemeId, [2, 4, 6, 7, 8, 9, 10, 11, 5], true)
            ? 2.0
            : 1.0;
    }
}
