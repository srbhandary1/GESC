<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

abstract class StrictContentCommandRequest extends IdempotentRequest
{
    /** @return list<string> */
    abstract protected function allowedPayloadKeys(): array;

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), $this->allowedPayloadKeys()) !== []) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }

            $this->validateCommand($validator);
        });
    }

    protected function validateCommand(Validator $validator): void {}

    /** @return array<string, list<mixed>> */
    protected function governedSourcePayloadRules(): array
    {
        return [
            'payload.schema' => ['required', 'string', 'in:global-english.governed-text.v1,global-english.daily-speaking-mission.v1'],
            'payload.text' => ['sometimes', 'string', 'min:1', 'max:8000'],
            'payload.goal_key' => ['sometimes', 'string', 'in:everyday-conversation,workplace-confidence,travel-confidence'],
            'payload.estimated_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'payload.title' => ['sometimes', 'string', 'min:1', 'max:8000'],
            'payload.model_text' => ['sometimes', 'string', 'min:1', 'max:8000'],
            'payload.transcript' => ['sometimes', 'string', 'min:1', 'max:8000'],
            'payload.can_do' => ['sometimes', 'string', 'min:1', 'max:8000'],
            'payload.model_audio_duration_ms' => ['sometimes', 'integer', 'min:250', 'max:300000'],
            'payload.check' => ['sometimes', 'array'],
            'payload.check.id' => ['sometimes', 'string', 'max:100', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/'],
            'payload.check.prompt' => ['sometimes', 'string', 'min:1', 'max:8000'],
            'payload.check.options' => ['sometimes', 'array', 'min:2', 'max:6'],
            'payload.check.options.*' => ['array'],
            'payload.check.options.*.id' => ['required_with:payload.check.options', 'string', 'max:100', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/'],
            'payload.check.options.*.label' => ['required_with:payload.check.options', 'string', 'min:1', 'max:8000'],
            'payload.check.correct_option_id' => ['sometimes', 'string', 'max:100', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/'],
        ];
    }

    /** @return array<string, list<mixed>> */
    protected function governedKannadaPayloadRules(): array
    {
        return [
            'payload.schema' => ['required', 'string', 'in:global-english.governed-text.v1,global-english.daily-speaking-mission.v1'],
            'payload.text' => ['sometimes', 'string', 'min:1', 'max:8000'],
            'payload.guidance' => ['sometimes', 'string', 'min:1', 'max:8000'],
        ];
    }

    protected function validateGovernedSourcePayload(Validator $validator): void
    {
        $payload = $this->input('payload');

        if (! is_array($payload) || array_is_list($payload)) {
            return;
        }

        $schema = $payload['schema'] ?? null;

        if ($schema === 'global-english.governed-text.v1') {
            $this->validateExactKeys($validator, $payload, ['schema', 'text']);

            return;
        }

        if ($schema !== 'global-english.daily-speaking-mission.v1') {
            return;
        }

        $this->validateExactKeys($validator, $payload, [
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
        $check = $payload['check'] ?? null;

        if (is_array($check) && ! array_is_list($check)) {
            $this->validateExactKeys($validator, $check, [
                'id',
                'prompt',
                'options',
                'correct_option_id',
            ]);
            $options = $check['options'] ?? null;

            if (is_array($options) && array_is_list($options)) {
                foreach ($options as $option) {
                    if (is_array($option) && ! array_is_list($option)) {
                        $this->validateExactKeys(
                            $validator,
                            $option,
                            ['id', 'label'],
                        );
                    }
                }
            }
        }

        if (
            $this->input('content_type') !== 'instruction'
            || $this->input('learner_visible') !== true
        ) {
            $validator->errors()->add(
                'payload.schema',
                'Daily speaking missions must be learner-visible instruction content.',
            );
        }
    }

    protected function validateGovernedKannadaPayload(Validator $validator): void
    {
        $payload = $this->input('payload');

        if (! is_array($payload) || array_is_list($payload)) {
            return;
        }

        $keys = ($payload['schema'] ?? null)
            === 'global-english.daily-speaking-mission.v1'
                ? ['schema', 'guidance']
                : ['schema', 'text'];
        $this->validateExactKeys($validator, $payload, $keys);
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $expected
     */
    private function validateExactKeys(
        Validator $validator,
        array $value,
        array $expected,
    ): void {
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        if ($actual !== $expected) {
            $validator->errors()->add(
                'payload',
                'The governed payload contains unsupported or missing fields.',
            );
        }
    }

    /** @param list<string> $fields */
    protected function trimStringFields(array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
