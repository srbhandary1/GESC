<?php

declare(strict_types=1);

namespace App\Authentication\Federated;

final class FakeFederatedIdentityProvider implements FederatedIdentityProvider
{
    public function enabled(string $provider): bool
    {
        return in_array($provider, ['google', 'facebook'], true);
    }

    public function authorizationUrl(
        string $provider,
        string $state,
        string $redirectUri,
    ): string {
        if (! $this->enabled($provider)) {
            throw new FederatedIdentityUnavailable;
        }

        return 'https://federated.test/authorize?'.http_build_query([
            'provider' => $provider,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    public function complete(
        string $provider,
        string $authorizationCode,
        string $redirectUri,
    ): FederatedAccount {
        if (! $this->enabled($provider) || $authorizationCode === '' || $redirectUri === '') {
            throw new FederatedIdentityUnavailable;
        }

        return new FederatedAccount($provider, $authorizationCode);
    }
}
