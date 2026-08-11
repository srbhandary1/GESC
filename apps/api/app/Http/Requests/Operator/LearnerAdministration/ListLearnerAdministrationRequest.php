<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\LearnerAdministration;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListLearnerAdministrationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $query = $this->query('q');

        if (is_string($query)) {
            $this->query->set('q', trim($query));
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'cursor' => ['sometimes', 'string', 'max:4096'],
            'status' => [
                'sometimes',
                'string',
                Rule::in($this->allowedStatuses()),
            ],
            'goal_key' => [
                'sometimes',
                'string',
                Rule::in((array) config('learning.goal_keys', [])),
            ],
            'cohort_id' => ['sometimes', 'string', 'ulid'],
            'q' => [
                'sometimes',
                'string',
                'min:1',
                'max:80',
                'not_regex:/[%_\\\\\x00-\x1F\x7F]/u',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = match ($this->route()?->getName()) {
                'api.v1.operator.learners.index' => [
                    'limit', 'cursor', 'status', 'goal_key', 'cohort_id', 'q',
                ],
                'api.v1.operator.learners.enrollments.index' => [
                    'limit', 'cursor',
                ],
                'api.v1.operator.learner-invitations.index' => [
                    'limit', 'cursor', 'status',
                ],
                default => [],
            };

            if (array_diff(array_keys($this->query()), $allowed) !== []) {
                $validator->errors()->add(
                    'query',
                    'The request contains unsupported query parameters.',
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    private function allowedStatuses(): array
    {
        return match ($this->route()?->getName()) {
            'api.v1.operator.learners.index' => [
                'active', 'suspended', 'ended',
            ],
            'api.v1.operator.learner-invitations.index' => [
                'pending', 'accepted', 'revoked', 'expired',
            ],
            default => [],
        };
    }
}
