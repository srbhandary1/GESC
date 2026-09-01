<?php

declare(strict_types=1);

namespace App\Entitlements\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class EntitlementDenied extends HttpException
{
    public const string LEVEL_LOCKED = 'entitlement.level_locked';

    public const string ROLEPLAY_LOCKED = 'entitlement.roleplay_locked';

    public const string DEMO_AI_EXHAUSTED = 'entitlement.demo_ai_exhausted';

    public const string EXPIRED = 'entitlement.expired';

    public const string CHECKOUT_UNAVAILABLE = 'entitlement.checkout_unavailable';

    public function __construct(
        public readonly string $messageKey,
        int $status = 409,
    ) {
        parent::__construct($status);
    }
}
