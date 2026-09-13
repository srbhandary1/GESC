<?php

declare(strict_types=1);

namespace App\Health;

use App\Authentication\Password\FormerRecoveryContactNotifier;
use App\Authentication\Password\PasswordRecoveryDeliveryAdapter;
use App\Authentication\Password\UnavailableFormerRecoveryContactNotifier;
use App\Authentication\Password\UnavailablePasswordRecoveryDeliveryAdapter;

final readonly class PasswordRecoveryDeliveryReadinessProbe implements ReadinessProbe
{
    public function __construct(
        private PasswordRecoveryDeliveryAdapter $delivery,
        private FormerRecoveryContactNotifier $formerContactNotifier,
    ) {}

    public function name(): string
    {
        return 'password_recovery_delivery';
    }

    public function isReady(): bool
    {
        return ! app()->environment('production')
            || (
                ! $this->delivery instanceof UnavailablePasswordRecoveryDeliveryAdapter
                && ! $this->formerContactNotifier instanceof UnavailableFormerRecoveryContactNotifier
            );
    }
}
