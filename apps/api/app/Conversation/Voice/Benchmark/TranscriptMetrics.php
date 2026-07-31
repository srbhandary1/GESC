<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Benchmark;

use Normalizer;

final class TranscriptMetrics
{
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
        $normalized = preg_replace(
            "/[^\\p{L}\\p{N}'\\s]+/u",
            ' ',
            $normalized,
        );
        $normalized = preg_replace(
            "/(?<!\\p{L})'|'(?!\\p{L})/u",
            ' ',
            is_string($normalized) ? $normalized : '',
        );

        return array_values(array_filter(
            preg_split('/\s+/u', trim((string) $normalized)) ?: [],
            static fn (string $word): bool => $word !== '',
        ));
    }
}
