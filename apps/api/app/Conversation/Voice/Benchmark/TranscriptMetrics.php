<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Benchmark;

use Normalizer;

final class TranscriptMetrics
{
    /**
     * @var array<string, int>
     */
    private const array NUMBER_WORDS = [
        'zero' => 0,
        'oh' => 0,
        'one' => 1,
        'two' => 2,
        'three' => 3,
        'four' => 4,
        'five' => 5,
        'six' => 6,
        'seven' => 7,
        'eight' => 8,
        'nine' => 9,
        'ten' => 10,
        'eleven' => 11,
        'twelve' => 12,
        'thirteen' => 13,
        'fourteen' => 14,
        'fifteen' => 15,
        'sixteen' => 16,
        'seventeen' => 17,
        'eighteen' => 18,
        'nineteen' => 19,
        'twenty' => 20,
        'thirty' => 30,
        'forty' => 40,
        'fifty' => 50,
        'sixty' => 60,
        'seventy' => 70,
        'eighty' => 80,
        'ninety' => 90,
        'hundred' => 100,
    ];

    /**
     * @return array{
     *   reference_words: int,
     *   edits: int,
     *   insertions: int,
     *   deletions: int,
     *   substitutions: int,
     *   exact: bool,
     *   material_hallucination: bool
     * }
     */
    public function compare(string $reference, string $hypothesis): array
    {
        $referenceWords = $this->words($reference);
        $hypothesisWords = $this->words($hypothesis);
        $rows = count($referenceWords);
        $columns = count($hypothesisWords);
        $matrix = array_fill(0, $rows + 1, []);
        $operations = array_fill(0, $rows + 1, []);

        for ($row = 0; $row <= $rows; $row++) {
            $matrix[$row][0] = $row;
            $operations[$row][0] = $row === 0 ? 'match' : 'delete';
        }
        for ($column = 0; $column <= $columns; $column++) {
            $matrix[0][$column] = $column;
            $operations[0][$column] = $column === 0 ? 'match' : 'insert';
        }

        for ($row = 1; $row <= $rows; $row++) {
            for ($column = 1; $column <= $columns; $column++) {
                if (
                    $referenceWords[$row - 1]
                    === $hypothesisWords[$column - 1]
                ) {
                    $matrix[$row][$column] = $matrix[$row - 1][$column - 1];
                    $operations[$row][$column] = 'match';

                    continue;
                }

                $choices = [
                    'substitute' => $matrix[$row - 1][$column - 1] + 1,
                    'delete' => $matrix[$row - 1][$column] + 1,
                    'insert' => $matrix[$row][$column - 1] + 1,
                ];
                $minimum = min($choices);
                $operation = array_search($minimum, $choices, true);
                $matrix[$row][$column] = $minimum;
                $operations[$row][$column] = is_string($operation)
                    ? $operation
                    : 'substitute';
            }
        }

        $counts = ['insertions' => 0, 'deletions' => 0, 'substitutions' => 0];
        $consecutiveInsertions = 0;
        $maximumConsecutiveInsertions = 0;
        $row = $rows;
        $column = $columns;

        while ($row > 0 || $column > 0) {
            $operation = $operations[$row][$column] ?? 'substitute';

            if ($operation === 'match' || $operation === 'substitute') {
                if ($operation === 'substitute') {
                    $counts['substitutions']++;
                }
                $row--;
                $column--;
                $consecutiveInsertions = 0;
            } elseif ($operation === 'delete') {
                $counts['deletions']++;
                $row--;
                $consecutiveInsertions = 0;
            } else {
                $counts['insertions']++;
                $column--;
                $consecutiveInsertions++;
                $maximumConsecutiveInsertions = max(
                    $maximumConsecutiveInsertions,
                    $consecutiveInsertions,
                );
            }
        }

        $inventedSensitiveToken = false;
        $referenceSet = array_fill_keys($referenceWords, true);
        foreach ($hypothesisWords as $word) {
            if (
                ! isset($referenceSet[$word])
                && (
                    preg_match('/\A\d{6,}\z/', $word) === 1
                    || str_contains($word, '@')
                    || preg_match('/\A(?:account|phone|email|address)\z/', $word)
                        === 1
                )
            ) {
                $inventedSensitiveToken = true;
            }
        }

        return [
            'reference_words' => count($referenceWords),
            'edits' => $matrix[$rows][$columns],
            ...$counts,
            'exact' => $matrix[$rows][$columns] === 0,
            'material_hallucination' => $maximumConsecutiveInsertions >= 3
                || $inventedSensitiveToken,
        ];
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        $normalized = Normalizer::normalize($text, Normalizer::FORM_KC);
        $normalized = mb_strtolower(
            is_string($normalized) ? $normalized : $text,
        );
        // Currency glyphs are not lexical tokens for WER scoring.
        $normalized = preg_replace('/[₹$€£¥]/u', ' ', $normalized);
        $normalized = preg_replace(
            "/[^\\p{L}\\p{N}'\\s]+/u",
            ' ',
            is_string($normalized) ? $normalized : '',
        );
        $normalized = preg_replace(
            "/(?<!\\p{L})'|'(?!\\p{L})/u",
            ' ',
            is_string($normalized) ? $normalized : '',
        );

        $raw = array_values(array_filter(
            preg_split('/\s+/u', trim((string) $normalized)) ?: [],
            static fn (string $word): bool => $word !== '',
        ));

        return $this->canonicalizeTokens($this->splitAlphaNumericRuns($raw));
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function splitAlphaNumericRuns(array $tokens): array
    {
        $split = [];
        foreach ($tokens as $token) {
            if (preg_match('/\A(?:\p{L}+|\p{N}+)+\z/u', $token) !== 1) {
                $split[] = $token;

                continue;
            }
            if (
                preg_match_all('/\p{L}+|\p{N}+/u', $token, $matches) === false
                || $matches[0] === []
            ) {
                $split[] = $token;

                continue;
            }
            foreach ($matches[0] as $part) {
                $split[] = $part;
            }
        }

        return $split;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function canonicalizeTokens(array $tokens): array
    {
        $canonical = [];
        $index = 0;
        $count = count($tokens);

        while ($index < $count) {
            $parsed = $this->parseNumberPhrase($tokens, $index);
            if ($parsed !== null) {
                foreach ($parsed['digits'] as $digit) {
                    $canonical[] = $digit;
                }
                $index += $parsed['consumed'];

                continue;
            }

            $token = $tokens[$index];
            // Money unit wording is equivalent to a leading currency glyph.
            if ($token === 'rupee' || $token === 'rupees') {
                $index++;

                continue;
            }
            if (preg_match('/\A\d+\z/', $token) === 1) {
                foreach (str_split($token) as $digit) {
                    $canonical[] = $digit;
                }
            } else {
                $canonical[] = $token;
            }
            $index++;
        }

        return $canonical;
    }

    /**
     * @param  list<string>  $tokens
     * @return array{digits: list<string>, consumed: int}|null
     */
    private function parseNumberPhrase(array $tokens, int $index): ?array
    {
        if (! isset($tokens[$index], self::NUMBER_WORDS[$tokens[$index]])) {
            return null;
        }

        // Consecutive unit words ("five eight three one") stay per-digit.
        if ($this->isUnitWord($tokens[$index])) {
            $next = $tokens[$index + 1] ?? null;
            if (
                $next !== null
                && (
                    $this->isUnitWord($next)
                    || preg_match('/\A\d+\z/', $next) === 1
                )
            ) {
                return [
                    'digits' => [(string) self::NUMBER_WORDS[$tokens[$index]]],
                    'consumed' => 1,
                ];
            }
        }

        $digits = [];
        $current = 0;
        $consumed = 0;
        $count = count($tokens);
        $flush = static function () use (&$digits, &$current): void {
            if ($current <= 0) {
                return;
            }
            foreach (str_split((string) $current) as $digit) {
                $digits[] = $digit;
            }
            $current = 0;
        };

        while ($index + $consumed < $count) {
            $token = $tokens[$index + $consumed];
            if ($token === 'and' && ($current > 0 || $digits !== [])) {
                $consumed++;

                continue;
            }
            if (! isset(self::NUMBER_WORDS[$token])) {
                break;
            }

            $number = self::NUMBER_WORDS[$token];
            if ($number === 100) {
                $current = ($current === 0 ? 1 : $current) * 100;
                $consumed++;

                continue;
            }

            // "nine thirty" / "6:45" style: bare unit then tens → separate groups.
            if (
                $number >= 20
                && $number < 100
                && $number % 10 === 0
                && $current >= 1
                && $current <= 9
            ) {
                $flush();
                $current = $number;
                $consumed++;

                continue;
            }

            if ($number >= 10) {
                $current += $number;
                $consumed++;

                continue;
            }

            $current += $number;
            $consumed++;
        }

        $flush();

        if ($consumed === 0 || $digits === []) {
            return null;
        }

        return [
            'digits' => $digits,
            'consumed' => $consumed,
        ];
    }

    private function isUnitWord(string $token): bool
    {
        return isset(self::NUMBER_WORDS[$token])
            && self::NUMBER_WORDS[$token] >= 0
            && self::NUMBER_WORDS[$token] <= 9;
    }
}
