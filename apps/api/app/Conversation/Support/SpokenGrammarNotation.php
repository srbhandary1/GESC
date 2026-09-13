<?php

declare(strict_types=1);

namespace App\Conversation\Support;

/**
 * Turns written grammar formulas into speech-friendly English.
 *
 * Lesson cards keep "+" / "/". Ask AI Tutor TTS must not say "plus" or
 * "slash". Free-speech rooms leave those characters alone so a mathematical
 * "plus" still sounds like plus.
 */
final class SpokenGrammarNotation
{
    public static function forSpeech(string $text, bool $grammarDrill = false): string
    {
        if (! $grammarDrill) {
            return $text;
        }

        $spoken = preg_replace(
            '/\s+[\/\x{2044}\x{2215}\x{FF0F}]\s+/u',
            ' Or ',
            $text,
        ) ?? $text;

        return preg_replace('/\s+[+\x{FF0B}]\s+/u', ' and ', $spoken) ?? $spoken;
    }
}
