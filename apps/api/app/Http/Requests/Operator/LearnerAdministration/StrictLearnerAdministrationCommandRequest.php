<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\LearnerAdministration;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Contracts\Validation\Validator;

abstract class StrictLearnerAdministrationCommandRequest extends IdempotentRequest
{
    /**
     * @return list<string>
     */
    abstract protected function allowedPayloadKeys(): array;

    /**
     * @return list<array{0: string, 1: string}>
     */
    protected function nullableVersionPairs(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), $this->allowedPayloadKeys()) !== []) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }

            foreach ($this->nullableVersionPairs() as [$resource, $version]) {
                if (($this->input($resource) === null) !== ($this->input($version) === null)) {
                    $validator->errors()->add(
                        $version,
                        'The resource and expected version must both be present or both be null.',
                    );
                }
            }
        });
    }
}
