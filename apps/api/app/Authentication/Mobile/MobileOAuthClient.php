<?php

declare(strict_types=1);

namespace App\Authentication\Mobile;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;
use Laravel\Passport\Scope;

final class MobileOAuthClient extends Client
{
    private const REDIRECT_URI = 'com.globalenglish.app:/oauth/callback';

    /**
     * The installed Android app is a reviewed first-party public client. Once
     * the learner authenticates, a second consent click adds no meaningful
     * choice. Keep automatic approval fail-closed to the exact native client
     * shape and the one mobile API scope.
     *
     * @param  Scope[]  $scopes
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->isApprovedNativeClient()
            && count($scopes) === 1
            && ($scopes[0] ?? null) instanceof Scope
            && $scopes[0]->id === 'mobile:api';
    }

    public function isApprovedNativeClient(): bool
    {
        $provider = $this->getAttribute('provider');
        $effectiveProvider = is_string($provider) && $provider !== ''
            ? $provider
            : config('auth.guards.api.provider');
        $grantTypes = $this->getAttribute('grant_types');
        $redirectUris = $this->getAttribute('redirect_uris');

        if (! is_array($grantTypes) || ! is_array($redirectUris)) {
            return false;
        }

        sort($grantTypes);
        sort($redirectUris);

        return ! $this->revoked
            && ! $this->confidential()
            && $this->firstParty()
            && $this->getAttribute('owner_type') === null
            && $this->getAttribute('owner_id') === null
            && $effectiveProvider === 'users'
            && $grantTypes === ['authorization_code', 'refresh_token']
            && $redirectUris === [self::REDIRECT_URI]
            && $this->hasScope('mobile:api');
    }
}
