<?php

declare(strict_types=1);

namespace App\Conversation\Evaluation;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Support\ConversationUiLocales;
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
     *   ui_locales: list<string>,
     *   modes: list<string>,
     *   cases: list<array{
     *     id: string,
     *     coaching_mode: string,
     *     ui_locale: string,
     *     transcript: string,
     *     expected_error_category: string,
     *     expected_safety_state: string,
     *     requires_explanation_l1: bool,
     *     expects_shame_rejection: bool
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
        $uiLocales = $decoded['ui_locales'] ?? null;
        $modes = $decoded['modes'] ?? null;
        $cases = $decoded['cases'] ?? null;

        if (
            ! is_string($suiteKey)
            || ! is_string($suiteVersion)
            || ! is_string($locale)
            || ! is_array($uiLocales)
            || ! is_array($modes)
            || ! is_array($cases)
            || $cases === []
        ) {
            throw new ConversationOperationRejected;
        }

        $normalizedUiLocales = [];

        foreach ($uiLocales as $uiLocale) {
            if (! is_string($uiLocale) || ! ConversationUiLocales::isSupported($uiLocale)) {
                throw new ConversationOperationRejected;
            }

            $normalizedUiLocales[] = $uiLocale;
        }

        if (
            $normalizedUiLocales !== ConversationUiLocales::all()
            || count(array_unique($normalizedUiLocales)) !== count($normalizedUiLocales)
        ) {
            throw new ConversationOperationRejected;
        }

        $normalizedCases = [];
        $seenIds = [];

        foreach ($cases as $case) {
            if (! is_array($case)) {
                throw new ConversationOperationRejected;
            }

            $id = $case['id'] ?? null;
            $mode = $case['coaching_mode'] ?? null;
            $uiLocale = $case['ui_locale'] ?? null;
            $transcript = $case['transcript'] ?? null;
            $category = $case['expected_error_category'] ?? null;
            $safety = $case['expected_safety_state'] ?? null;
            $requiresL1 = $case['requires_explanation_l1'] ?? true;
            $expectsShame = $case['expects_shame_rejection'] ?? false;

            if (
                ! is_string($id)
                || $id === ''
                || isset($seenIds[$id])
                || ! is_string($mode)
                || ! is_string($uiLocale)
                || ! ConversationUiLocales::isSupported($uiLocale)
                || ! is_string($transcript)
                || ! is_string($category)
                || ! is_string($safety)
                || ! is_bool($requiresL1)
                || ! is_bool($expectsShame)
            ) {
                throw new ConversationOperationRejected;
            }

            $seenIds[$id] = true;
            $normalizedCases[] = [
                'id' => $id,
                'coaching_mode' => $mode,
                'ui_locale' => $uiLocale,
                'transcript' => $transcript,
                'expected_error_category' => $category,
                'expected_safety_state' => $safety,
                'requires_explanation_l1' => $requiresL1,
                'expects_shame_rejection' => $expectsShame,
            ];
        }

        $payload = [
            'suite_key' => $suiteKey,
            'suite_version' => $suiteVersion,
            'locale' => $locale,
            'ui_locales' => $normalizedUiLocales,
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
