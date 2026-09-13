<?php

declare(strict_types=1);

namespace App\Operator\ContentWorkspace;

use App\Curriculum\CanonicalJson;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceRejected;

final class GovernedContentPayloadPolicy
{
    public const string GOVERNED_TEXT = 'global-english.governed-text.v1';

    public const string DAILY_MISSION = 'global-english.daily-speaking-mission.v1';

    /** @var list<string> */
    private const array CONTENT_TYPES = [
        'assessment_rationale',
        'dialogue',
        'explanation',
        'feedback',
        'instruction',
        'learner_action',
        'model_text',
        'safety_note',
        'title',
        'transcript',
    ];

    /** @var list<string> */
    private const array KANNADA_REQUIRED = [
        'explanation',
        'feedback',
        'instruction',
        'learner_action',
    ];

    /** @var list<string> */
    private const array GOAL_KEYS = [
        'everyday-conversation',
        'workplace-confidence',
        'travel-confidence',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, content_type: string, learner_visible: bool, localization_requirement: string}
     */
    public function source(
        string $contentType,
        bool $learnerVisible,
        array $payload,
    ): array {
        $schema = $payload['schema'] ?? null;

        if ($schema === self::GOVERNED_TEXT) {
            $this->exactKeys($payload, ['schema', 'text']);
            $this->assertContentType($contentType);
            $normalized = [
                'schema' => self::GOVERNED_TEXT,
                'text' => $this->text($payload['text'] ?? null),
            ];
        } elseif ($schema === self::DAILY_MISSION) {
            if ($contentType !== 'instruction' || ! $learnerVisible) {
                throw new ContentWorkspaceRejected;
            }

            $normalized = $this->dailyMission($payload);
        } else {
            throw new ContentWorkspaceRejected;
        }

        $this->assertPayloadSize($normalized);

        return [
            'payload' => $normalized,
            'content_type' => $contentType,
            'learner_visible' => $learnerVisible,
            'localization_requirement' => in_array(
                $contentType,
                self::KANNADA_REQUIRED,
                true,
            ) ? 'kn_required' : 'english_only_pedagogical',
        ];
    }

    /**
     * @param  array<string, mixed>  $sourcePayload
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function kannada(array $sourcePayload, array $payload): array
    {
        $sourceSchema = $sourcePayload['schema'] ?? null;

        if ($sourceSchema === self::GOVERNED_TEXT) {
            $this->exactKeys($payload, ['schema', 'text']);

            if (($payload['schema'] ?? null) !== self::GOVERNED_TEXT) {
                throw new ContentWorkspaceRejected;
            }

            $normalized = [
                'schema' => self::GOVERNED_TEXT,
                'text' => $this->text($payload['text'] ?? null),
            ];
        } elseif ($sourceSchema === self::DAILY_MISSION) {
            $this->exactKeys($payload, ['schema', 'guidance']);

            if (($payload['schema'] ?? null) !== self::DAILY_MISSION) {
                throw new ContentWorkspaceRejected;
            }

            $normalized = [
                'schema' => self::DAILY_MISSION,
                'guidance' => $this->text($payload['guidance'] ?? null),
            ];
        } else {
            throw new ContentWorkspaceRejected;
        }

        $this->assertPayloadSize($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dailyMission(array $payload): array
    {
        $this->exactKeys($payload, [
            'schema',
            'goal_key',
            'estimated_minutes',
            'title',
            'model_text',
            'transcript',
            'can_do',
            'model_audio_duration_ms',
            'check',
        ]);
        $goalKey = $payload['goal_key'] ?? null;
        $estimatedMinutes = $payload['estimated_minutes'] ?? null;
        $audioDuration = $payload['model_audio_duration_ms'] ?? null;
        $check = $payload['check'] ?? null;

        if (
            ! is_string($goalKey)
            || ! in_array($goalKey, self::GOAL_KEYS, true)
            || ! is_int($estimatedMinutes)
            || $estimatedMinutes < 1
            || $estimatedMinutes > 60
            || ! is_int($audioDuration)
            || $audioDuration < 250
            || $audioDuration > 300_000
            || ! is_array($check)
            || array_is_list($check)
        ) {
            throw new ContentWorkspaceRejected;
        }

        $this->exactKeys($check, [
            'id',
            'prompt',
            'options',
            'correct_option_id',
        ]);
        $checkId = $this->key($check['id'] ?? null, 100);
        $correctOptionId = $this->key(
            $check['correct_option_id'] ?? null,
            100,
        );
        $options = $check['options'] ?? null;

        if (
            ! is_array($options)
            || ! array_is_list($options)
            || count($options) < 2
            || count($options) > 6
        ) {
            throw new ContentWorkspaceRejected;
        }

        $normalizedOptions = [];
        $optionIds = [];

        foreach ($options as $option) {
            if (! is_array($option) || array_is_list($option)) {
                throw new ContentWorkspaceRejected;
            }

            $this->exactKeys($option, ['id', 'label']);
            $optionId = $this->key($option['id'] ?? null, 100);

            if (isset($optionIds[$optionId])) {
                throw new ContentWorkspaceRejected;
            }

            $optionIds[$optionId] = true;
            $normalizedOptions[] = [
                'id' => $optionId,
                'label' => $this->text($option['label'] ?? null),
            ];
        }

        if (! isset($optionIds[$correctOptionId])) {
            throw new ContentWorkspaceRejected;
        }

        return [
            'schema' => self::DAILY_MISSION,
            'goal_key' => $goalKey,
            'estimated_minutes' => $estimatedMinutes,
            'title' => $this->text($payload['title'] ?? null),
            'model_text' => $this->text($payload['model_text'] ?? null),
            'transcript' => $this->text($payload['transcript'] ?? null),
            'can_do' => $this->text($payload['can_do'] ?? null),
            'model_audio_duration_ms' => $audioDuration,
            'check' => [
                'id' => $checkId,
                'prompt' => $this->text($check['prompt'] ?? null),
                'options' => $normalizedOptions,
                'correct_option_id' => $correctOptionId,
            ],
        ];
    }

    private function assertContentType(string $contentType): void
    {
        if (! in_array($contentType, self::CONTENT_TYPES, true)) {
            throw new ContentWorkspaceRejected;
        }
    }

    private function text(mixed $value): string
    {
        if (
            ! is_string($value)
            || $value === ''
            || mb_strlen($value) > 8_000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1
            || preg_match('/<[^>]+>/u', $value) === 1
            || preg_match('/(?:javascript|data|https?):/iu', $value) === 1
        ) {
            throw new ContentWorkspaceRejected;
        }

        return $value;
    }

    private function key(mixed $value, int $maximum): string
    {
        if (
            ! is_string($value)
            || strlen($value) > $maximum
            || preg_match('/\A[a-z0-9][a-z0-9._-]*\z/', $value) !== 1
        ) {
            throw new ContentWorkspaceRejected;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function exactKeys(array $payload, array $keys): void
    {
        $actual = array_keys($payload);
        sort($actual, SORT_STRING);
        sort($keys, SORT_STRING);

        if ($actual !== $keys) {
            throw new ContentWorkspaceRejected;
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertPayloadSize(array $payload): void
    {
        if (strlen(CanonicalJson::encode($payload)) > 65_536) {
            throw new ContentWorkspaceRejected;
        }
    }
}
