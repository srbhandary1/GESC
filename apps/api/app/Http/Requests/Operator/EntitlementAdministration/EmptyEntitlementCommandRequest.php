<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\EntitlementAdministration;

final class EmptyEntitlementCommandRequest extends StrictEntitlementAdministrationCommandRequest
{
    protected function allowedPayloadKeys(): array
    {
        return [];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [];
    }
}
