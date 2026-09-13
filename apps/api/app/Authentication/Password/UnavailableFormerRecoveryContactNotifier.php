<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use App\Models\UserIdentity;

final readonly class UnavailableFormerRecoveryContactNotifier implements FormerRecoveryContactNotifier
{
    public function notify(UserIdentity $formerIdentity, string $managementEventId): string
    {
        return self::OUTCOME_UNAVAILABLE;
    }
}
