<?php

declare(strict_types=1);

namespace App\Conversation\Support;

/**
 * Turns written target_patterns into one spoken model sentence at a time.
 * Introduction repeats the model. Practice asks for the next form.
 */
final class LessonDrillStepper
{
    /**
     * @param  list<string>  $patterns
     * @param  list<string>  $fallbackTurns
     */
    public static function opening(string $partId, array $patterns, array $fallbackTurns = []): string
    {
        $models = self::models($patterns, $fallbackTurns);
        if ($models === []) {
            return '';
        }

        $first = $models[0];
        if ($partId === 'practice') {
            $second = $models[1] ?? $first;

            return "Practice time. {$first} Now say: {$second}";
        }

        return "Listen. Say: {$first} Now you try.";
    }

    public static function normalizeForMatch(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    public static function utteranceMatchesModel(
        string $utterance,
        string $model,
    ): bool {
        return self::normalizeForMatch($utterance)
            === self::normalizeForMatch($model);
    }

    /**
     * @param  list<string>  $patterns
     * @param  list<string>  $fallbackTurns
     */
    public static function currentModel(
        array $patterns,
        int $acceptedPatternCount,
        array $fallbackTurns = [],
    ): ?string {
        $models = self::models($patterns, $fallbackTurns);

        return $models[$acceptedPatternCount] ?? null;
    }

    public static function retryReply(string $partId, string $currentModel): string
    {
        if ($partId === 'practice') {
            return "Good try. Now say: {$currentModel}";
        }

        return "Good try. Listen again. Say: {$currentModel} Your turn.";
    }

    /**
     * @param  list<string>  $patterns
     * @param  list<string>  $fallbackTurns
     */
    public static function reply(
        string $partId,
        array $patterns,
        int $completedTurnsBeforeThis,
        array $fallbackTurns = [],
    ): string {
        $models = self::models($patterns, $fallbackTurns);
        $count = count($models);
        if ($count === 0) {
            return '';
        }

        $nextIndex = max(0, $completedTurnsBeforeThis) + 1;
        if ($nextIndex >= $count) {
            return $partId === 'practice'
                ? 'Good. Finish the Check items on this page.'
                : 'Good. You practised each pattern. You can mark Introduction complete.';
        }

        $next = $models[$nextIndex];
        if ($partId === 'practice') {
            return "Good. Now say: {$next}";
        }

        return "Good. Now listen. Say: {$next} Your turn.";
    }

    public static function modelUtterance(string $pattern): string
    {
        $text = trim($pattern);
        $text = preg_replace('/\bShe\/He\b/u', 'She', $text) ?? $text;
        $text = preg_replace('/\bhe\/she\b/u', 'she', $text) ?? $text;
        $text = preg_replace('/\s*\+\s*base verb/iu', ' drink water', $text) ?? $text;
        $text = preg_replace('/\s*\+\s*-ing/iu', ' waiting', $text) ?? $text;
        $text = preg_replace('/\s*\+\s*past verb/iu', ' cooked', $text) ?? $text;
        if (preg_match('/\A(.+?)\s+\/\s+/u', $text, $parts) === 1) {
            $text = trim((string) $parts[1]);
            if (
                ! str_ends_with($text, '.')
                && ! str_ends_with($text, '?')
                && ! str_ends_with($text, '!')
            ) {
                $text .= '.';
            }
        }

        return trim($text);
    }

    /**
     * @param  list<string>  $patterns
     * @param  list<string>  $fallbackTurns
     * @return list<string>
     */
    public static function models(array $patterns, array $fallbackTurns = []): array
    {
        $models = [];
        foreach ($patterns as $index => $pattern) {
            $model = self::modelUtterance($pattern);
            $fallback = trim((string) ($fallbackTurns[$index] ?? ''));
            if ($fallback !== '' && self::modelLooksLikeTemplate($model)) {
                $model = $fallback;
            }
            if ($model === '') {
                continue;
            }
            $models[] = $model;
        }

        return $models;
    }

    private static function modelLooksLikeTemplate(string $model): bool
    {
        return preg_match('/\+|…|\.\.\./u', $model) === 1;
    }
}
