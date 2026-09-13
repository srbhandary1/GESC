<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice;

use App\Curriculum\CanonicalJson;

final class LessonPracticeBlueprintValidator
{
    /**
     * @param  array<string, mixed>  $blueprint
     * @return list<string>
     */
    public function validate(array $blueprint): array
    {
        $errors = [];
        $lesson = $blueprint['lesson'] ?? [];
        $parts = $blueprint['ordered_parts'] ?? [];
        $check = $blueprint['check'] ?? [];
        $levelCheckpoint = $blueprint['level_checkpoint'] ?? null;

        if (($blueprint['schema'] ?? null) !== 'global-english.lesson-practice-loop.v1') {
            $errors[] = 'schema';
        }
        if (! is_string($lesson['objective'] ?? null) || trim($lesson['objective']) === '') {
            $errors[] = 'objective';
        }
        $expected = ['introduction', 'practice', 'conversation', 'interaction'];
        if (array_map(fn (array $part): mixed => $part['part_id'] ?? null, $parts) !== $expected) {
            $errors[] = 'ordered_parts';
        }
        if (count($parts) !== 4 || count(array_unique(array_map(fn (array $part): mixed => $part['order'] ?? null, $parts))) !== 4) {
            $errors[] = 'part_order';
        }
        $items = $check['items'] ?? [];
        $weights = array_sum(array_map(fn (array $item): int => (int) ($item['weight'] ?? 0), $items));
        if (count($items) < 3 || count($items) > 7 || $weights !== 100) {
            $errors[] = 'check_weights';
        }
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['options'][$item['correct_option'] ?? -1])) {
                $errors[] = 'check_answer_index';
                break;
            }
        }
        if (is_array($levelCheckpoint)) {
            $checkpointItems = $levelCheckpoint['items'] ?? [];
            $checkpointWeights = 0;
            foreach ($checkpointItems as $checkpointItem) {
                if (is_array($checkpointItem)) {
                    $checkpointWeights += (int) ($checkpointItem['weight'] ?? 0);
                }
            }
            if (
                count($checkpointItems) < 3
                || count($checkpointItems) > 7
                || $checkpointWeights !== 100
            ) {
                $errors[] = 'level_checkpoint_weights';
            }
            foreach ($checkpointItems as $item) {
                if (! is_array($item) || ! isset($item['options'][$item['correct_option'] ?? -1])) {
                    $errors[] = 'level_checkpoint_answer_index';
                    break;
                }
            }
        }
        if (($blueprint['apply']['evidence_state'] ?? null) !== 'not_recorded') {
            $errors[] = 'apply_evidence_state';
        }
        if (preg_match('/<\/?script\b/i', CanonicalJson::encode($blueprint)) === 1) {
            $errors[] = 'executable_markup';
        }

        return array_values(array_unique($errors));
    }
}
