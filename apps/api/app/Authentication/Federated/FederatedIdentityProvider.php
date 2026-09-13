<?php

declare(strict_types=1);

namespace App\Authentication\Federated;

interface FederatedIdentityProvider
{
    public function enabled(string $provider): bool;

    public function authorizationUrl(
        string $provider,
        string $state,
        string $redirectUri,
    ): string;

    public function complete(
        string $provider,
        string $authorizationCode,
        string $redirectUri,
    ): FederatedAccount;
}
