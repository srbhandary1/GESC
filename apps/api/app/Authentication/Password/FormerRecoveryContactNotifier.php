<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use App\Models\UserIdentity;

interface FormerRecoveryContactNotifier
{
    public const string OUTCOME_ACCEPTED = 'accepted';

    public const string OUTCOME_UNAVAILABLE = 'unavailable';

    public const string OUTCOME_NOT_POSSIBLE = 'not_possible';

    public function notify(UserIdentity $formerIdentity, string $managementEventId): string;
}
