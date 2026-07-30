<?php

declare(strict_types=1);

namespace App\Http\Requests\Learner;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteOfflineMissionRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'schema' => [
                'required',
                'string',
                Rule::in([(string) config('offline.completion_schema')]),
            ],
            'bundle_id' => ['required', 'ulid'],
            'lease' => ['required', 'string', 'max:8192'],
            'client_attempt_id' => ['required', 'uuid'],
            'check_id' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[a-z0-9][a-z0-9._-]{0,99}\z/',
            ],
            'selected_option_id' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[a-z0-9][a-z0-9._-]{0,99}\z/',
            ],
            'local_recording' => ['present', 'nullable', 'array'],
            'idempotency_key' => [
                'required',
                'string',
                'regex:/\A(?:[0-9A-HJKMNP-TV-Z]{26}|[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\z/',
            ],
        ];

        if (! is_array($this->input('local_recording'))) {
            return $rules;
        }

        return [
            ...$rules,
            'local_recording.captured' => [
                'required',
                'boolean',
                'accepted',
            ],
            'local_recording.client_capture_id' => [
                'required',
                'uuid',
            ],
            'local_recording.mime_type' => [
                'required',
                'string',
                Rule::in(config('learning.capture.mime_types', [])),
            ],
            'local_recording.byte_size' => [
                'required',
                'integer',
                'min:'.(int) config(
                    'learning.capture.minimum_bytes',
                    128,
                ),
                'max:'.(int) config(
                    'learning.capture.maximum_bytes',
                    10_000_000,
                ),
            ],
            'local_recording.duration_ms' => [
                'required',
                'integer',
                'min:'.(int) config(
                    'learning.capture.minimum_duration_ms',
                    250,
                ),
                'max:'.(int) config(
                    'learning.capture.maximum_duration_ms',
                    120_000,
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [
            ...parent::validationData(),
            'idempotency_key' => $this->header('Idempotency-Key'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                array_diff(
                    array_keys($this->all()),
                    [
                        'schema',
                        'bundle_id',
                        'lease',
                        'client_attempt_id',
                        'check_id',
                        'selected_option_id',
                        'local_recording',
                    ],
                ) !== []
            ) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }

            $recording = $this->input('local_recording');

            if (
                is_array($recording)
                && (
                    ($recording['captured'] ?? null) !== true
                    || ! is_int($recording['byte_size'] ?? null)
                    || ! is_int($recording['duration_ms'] ?? null)
                    || array_diff(
                        array_keys($recording),
                        [
                            'captured',
                            'client_capture_id',
                            'mime_type',
                            'byte_size',
                            'duration_ms',
                        ],
                    ) !== []
                )
            ) {
                $validator->errors()->add(
                    'local_recording',
                    'The local recording receipt is invalid.',
                );
            }
        });
    }
}
