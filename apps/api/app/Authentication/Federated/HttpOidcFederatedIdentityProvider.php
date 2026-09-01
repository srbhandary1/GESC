<?php

declare(strict_types=1);

namespace App\Authentication\Federated;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

final class HttpOidcFederatedIdentityProvider implements FederatedIdentityProvider
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function enabled(string $provider): bool
    {
        return $this->clientId($provider) !== null
            && $this->clientSecret($provider) !== null
            && $this->configuredRedirectUri($provider) !== null;
    }

    public function authorizationUrl(
        string $provider,
        string $state,
        string $redirectUri,
    ): string {
        $clientId = $this->clientId($provider);
        $configuredRedirect = $this->configuredRedirectUri($provider);

        if ($clientId === null || $configuredRedirect === null || $configuredRedirect !== $redirectUri) {
            throw new FederatedIdentityUnavailable;
        }

        if ($provider === 'google') {
            return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid',
                'state' => $state,
            ]);
        }

        if ($provider === 'facebook') {
            return 'https://www.facebook.com/v21.0/dialog/oauth?'.http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'state' => $state,
            ]);
        }

        throw new FederatedIdentityUnavailable;
    }

    public function complete(
        string $provider,
        string $authorizationCode,
        string $redirectUri,
    ): FederatedAccount {
        $clientId = $this->clientId($provider);
        $clientSecret = $this->clientSecret($provider);
        $configuredRedirect = $this->configuredRedirectUri($provider);

        if (
            $clientId === null
            || $clientSecret === null
            || $configuredRedirect === null
            || $configuredRedirect !== $redirectUri
            || $authorizationCode === ''
        ) {
            throw new FederatedIdentityUnavailable;
        }

        $subject = $provider === 'google'
            ? $this->googleSubject($clientId, $clientSecret, $authorizationCode, $redirectUri)
            : $this->facebookSubject($clientId, $clientSecret, $authorizationCode, $redirectUri);

        if ($subject === null) {
            throw new FederatedIdentityUnavailable;
        }

        return new FederatedAccount($provider, $subject);
    }

    private function googleSubject(
        string $clientId,
        string $clientSecret,
        string $authorizationCode,
        string $redirectUri,
    ): ?string {
        $token = $this->http->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $authorizationCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        $accessToken = $this->stringField($token, 'access_token');

        if ($accessToken === null) {
            return null;
        }

        $userinfo = $this->http->withToken($accessToken)
            ->acceptJson()
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        return $this->stringField($userinfo, 'sub');
    }

    private function facebookSubject(
        string $clientId,
        string $clientSecret,
        string $authorizationCode,
        string $redirectUri,
    ): ?string {
        $token = $this->http->get('https://graph.facebook.com/v21.0/oauth/access_token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $authorizationCode,
            'redirect_uri' => $redirectUri,
        ]);

        $accessToken = $this->stringField($token, 'access_token');

        if ($accessToken === null) {
            return null;
        }

        $profile = $this->http->get('https://graph.facebook.com/me', [
            'access_token' => $accessToken,
            'fields' => 'id',
        ]);

        return $this->stringField($profile, 'id');
    }

    private function stringField(Response $response, string $field): ?string
    {
        if (! $response->successful()) {
            return null;
        }

        $value = $response->json($field);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function clientId(string $provider): ?string
    {
        return $this->configuredString('authentication.federated.'.$provider.'.client_id');
    }

    private function clientSecret(string $provider): ?string
    {
        return $this->configuredString('authentication.federated.'.$provider.'.client_secret');
    }

    private function configuredRedirectUri(string $provider): ?string
    {
        return $this->configuredString('authentication.federated.'.$provider.'.redirect_uri');
    }

    private function configuredString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
