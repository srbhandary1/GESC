<?php

declare(strict_types=1);

namespace App\Authentication\Federated;

final class DisabledFederatedIdentityProvider implements FederatedIdentityProvider
{
    public function enabled(string $provider): bool
    {
        return false;
    }

    public function authorizationUrl(
        string $provider,
        string $state,
        string $redirectUri,
    ): string {
        throw new FederatedIdentityUnavailable;
    }

    public function complete(
        string $provider,
        string $authorizationCode,
        string $redirectUri,
    ): FederatedAccount {
        throw new FederatedIdentityUnavailable;
    }
}
