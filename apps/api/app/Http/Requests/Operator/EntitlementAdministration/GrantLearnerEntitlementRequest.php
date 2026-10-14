<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\EntitlementAdministration;

final class GrantLearnerEntitlementRequest extends StrictEntitlementAdministrationCommandRequest
{
    protected function allowedPayloadKeys(): array
    {
        return ['plan_id', 'reason', 'payment_reference'];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'plan_id' => ['required', 'string', 'ulid'],
            'reason' => ['nullable', 'string', 'max:240'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
