<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

final class ListSpeechEconomicsRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'environment' => ['sometimes', 'string', 'max:32'],
            'provider_stage' => ['sometimes', 'in:stt,model,tts,end_to_end'],
            'provider_key' => ['sometimes', 'string', 'max:64'],
            'locale' => ['sometimes', 'string', 'max:16'],
            'conversation_mode' => ['sometimes', 'string', 'max:24'],
            'result_status' => ['sometimes', 'in:succeeded,failed,cancelled,partial,started,unavailable'],
            'dispatch_kind' => ['sometimes', 'in:provider_dispatch,cache,replay,local_execution,unknown,client_sample'],
            'route_role' => ['sometimes', 'in:primary,fallback,benchmark,client'],
        ];
    }
}
