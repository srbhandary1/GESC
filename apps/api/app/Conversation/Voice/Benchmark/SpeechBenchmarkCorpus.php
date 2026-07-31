<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Benchmark;

use App\Conversation\Voice\Providers\Azure\AzureSpeechConfiguration;
use JsonException;
use Normalizer;
use RuntimeException;

final readonly class SpeechBenchmarkCorpus
{
    public function __construct(
        private AzureSpeechConfiguration $configuration,
    ) {}

    /**
     * @return list<array{id: string, category: string, text: string}>
     */
    public function load(): array
    {
        $path = $this->configuration->string(
            'speech.benchmark.corpus_path',
        );
        $body = is_file($path) ? file_get_contents($path) : false;

        if (! is_string($body)) {
            throw new RuntimeException('The locked speech corpus is unavailable.');
        }

        try {
            $manifest = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('The locked speech corpus is invalid.');
        }

        if (
            ! is_array($manifest)
            || ($manifest['benchmark_id'] ?? null)
                !== $this->configuration->string('speech.benchmark.id')
            || ($manifest['locale'] ?? null) !== 'en-IN'
            || ! is_array($manifest['utterances'] ?? null)
            || count($manifest['utterances']) !== 48
        ) {
            throw new RuntimeException('The locked speech corpus is invalid.');
        }

        $utterances = [];
        $ids = [];
        $categoryCounts = [];
        $planPath = $this->configuration->string(
            'speech.benchmark.plan_path',
        );
        $plan = is_file($planPath) ? file_get_contents($planPath) : false;
        $planOffset = 0;

        if (! is_string($plan)) {
            throw new RuntimeException('The approved benchmark plan is unavailable.');
        }

        foreach ($manifest['utterances'] as $position => $utterance) {
            if (! is_array($utterance)) {
                throw new RuntimeException('The locked speech corpus is invalid.');
            }

            $id = $utterance['id'] ?? null;
            $category = $utterance['category'] ?? null;
            $text = $utterance['text'] ?? null;
            $wordCount = is_string($text)
                ? count(preg_split('/\s+/u', trim($text)) ?: [])
                : 0;

            if (
                ! is_string($id)
                || ! is_string($category)
                || ! is_string($text)
                || ! preg_match(
                    '/\A(?:foundation|beginner|india|number|repair)-\d{2}\z/',
                    $id,
                )
                || isset($ids[$id])
                || ! in_array(
                    $category,
                    ['foundation', 'beginner', 'india', 'number', 'repair'],
                    true,
                )
                || ! str_starts_with($id, $category.'-')
                || $wordCount < 3
                || $wordCount > 20
                || mb_strlen($text) > 160
                || preg_match('//u', $text) !== 1
                || Normalizer::normalize($text, Normalizer::FORM_C) !== $text
                || $position !== count($utterances)
            ) {
                throw new RuntimeException('The locked speech corpus is invalid.');
            }

            $planRow = '| `'.$id.'` | '.$text.' |';
            $planPosition = strpos($plan, $planRow, $planOffset);
            if ($planPosition === false) {
                throw new RuntimeException(
                    'The locked speech corpus differs from the approved plan.',
                );
            }
            $planOffset = $planPosition + strlen($planRow);

            $ids[$id] = true;
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            $utterances[] = [
                'id' => $id,
                'category' => $category,
                'text' => $text,
            ];
        }

        if ($categoryCounts !== [
            'foundation' => 12,
            'beginner' => 12,
            'india' => 8,
            'number' => 8,
            'repair' => 8,
        ]) {
            throw new RuntimeException('The locked speech corpus is invalid.');
        }

        return $utterances;
    }
}
