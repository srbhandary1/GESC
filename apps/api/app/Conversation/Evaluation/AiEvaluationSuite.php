<?php

declare(strict_types=1);

namespace App\Conversation\Evaluation;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Curriculum\CanonicalJson;
use Illuminate\Support\Facades\File;

final class AiEvaluationSuite
{
    /**
     * @return array{
     *   suite_key: string,
     *   suite_version: string,
     *   suite_hash: string,
     *   locale: string,
     *   modes: list<string>,
     *   cases: list<array{
     *     id: string,
     *     coaching_mode: string,
     *     transcript: string,
     *     expected_error_category: string,
     *     expected_safety_state: string
     *   }>
     * }
     */
    public function load(): array
    {
        $path = resource_path('ai-evaluation/v1/suite.json');

        if (! File::isFile($path)) {
            throw new ConversationOperationRejected;
        }

        $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new ConversationOperationRejected;
        }

        $suiteKey = $decoded['suite_key'] ?? null;
        $suiteVersion = $decoded['suite_version'] ?? null;
        $locale = $decoded['locale'] ?? null;
        $modes = $decoded['modes'] ?? null;
        $cases = $decoded['cases'] ?? null;

        if (
            ! is_string($suiteKey)
            || ! is_string($suiteVersion)
            || ! is_string($locale)
            || ! is_array($modes)
            || ! is_array($cases)
            || $cases === []
        ) {
            throw new ConversationOperationRejected;
        }

        $normalizedCases = [];

        foreach ($cases as $case) {
            if (! is_array($case)) {
                throw new ConversationOperationRejected;
            }

            $id = $case['id'] ?? null;
            $mode = $case['coaching_mode'] ?? null;
            $transcript = $case['transcript'] ?? null;
            $category = $case['expected_error_category'] ?? null;
            $safety = $case['expected_safety_state'] ?? null;

            if (
                ! is_string($id)
                || ! is_string($mode)
                || ! is_string($transcript)
                || ! is_string($category)
                || ! is_string($safety)
            ) {
                throw new ConversationOperationRejected;
            }

            $normalizedCases[] = [
                'id' => $id,
                'coaching_mode' => $mode,
                'transcript' => $transcript,
                'expected_error_category' => $category,
                'expected_safety_state' => $safety,
            ];
        }

        $payload = [
            'suite_key' => $suiteKey,
            'suite_version' => $suiteVersion,
            'locale' => $locale,
            'modes' => array_values(array_filter(
                $modes,
                static fn (mixed $mode): bool => is_string($mode),
            )),
            'cases' => $normalizedCases,
        ];

        return [
            ...$payload,
            'suite_hash' => CanonicalJson::sha256($payload),
        ];
    }
}
