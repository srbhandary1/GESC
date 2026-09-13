<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\EntitlementAdministration;

final class GrantLearnerEntitlementRequest extends StrictEntitlementAdministrationCommandRequest
{
    protected function allowedPayloadKeys(): array
    {
        return ['plan_id'];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'plan_id' => ['required', 'string', 'ulid'],
        ];
    }
}
