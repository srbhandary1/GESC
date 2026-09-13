<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class RecordSpeechLatencyTelemetryRequest extends IdempotentRequest
{
    /** @return array<string, list<mixed>> */
    protected function payloadRules(): array
    {
        $maximum = (int) config(
            'speech_observability.maximum_latency_ms',
            120_000,
        );

        return [
            'client_runtime' => ['required', 'string', Rule::in(['web', 'android'])],
            'network_class' => [
                'required',
                'string',
                Rule::in(['unknown', 'wifi', 'cellular', 'ethernet']),
            ],
            'playback_outcome' => [
                'required',
                'string',
                Rule::in(['started', 'unavailable', 'failed', 'cancelled']),
            ],
            'release_to_ack_ms' => ['nullable', 'integer', 'min:0', 'max:'.$maximum],
            'release_to_first_signal_ms' => ['nullable', 'integer', 'min:0', 'max:'.$maximum],
            'release_to_first_audio_ms' => ['nullable', 'integer', 'min:0', 'max:'.$maximum],
            'turn_json_to_first_audio_ms' => ['nullable', 'integer', 'min:0', 'max:'.$maximum],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), [
                'client_runtime',
                'network_class',
                'playback_outcome',
                'release_to_ack_ms',
                'release_to_first_signal_ms',
                'release_to_first_audio_ms',
                'turn_json_to_first_audio_ms',
            ]) !== []) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }

            if (
                $this->input('playback_outcome') === 'started'
                && $this->input('release_to_first_audio_ms') === null
            ) {
                $validator->errors()->add(
                    'release_to_first_audio_ms',
                    'First-audio latency is required when playback started.',
                );
            }
        });
    }
}
