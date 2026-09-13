<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use App\Models\UserIdentity;

final class UnavailablePasswordRecoveryDeliveryAdapter implements PasswordRecoveryDeliveryAdapter
{
    public function deliver(
        UserIdentity $identity,
        string $challengeId,
        string $token,
    ): void {
        throw new PasswordRecoveryDeliveryUnavailable(
            'No reviewed password-recovery delivery adapter is configured.',
        );
    }
}
