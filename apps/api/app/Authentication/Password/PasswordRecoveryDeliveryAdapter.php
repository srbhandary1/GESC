<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use App\Models\UserIdentity;

interface PasswordRecoveryDeliveryAdapter
{
    public function deliver(
        UserIdentity $identity,
        string $challengeId,
        string $token,
    ): void;
}
