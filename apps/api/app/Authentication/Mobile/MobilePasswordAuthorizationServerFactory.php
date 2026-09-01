<?php

declare(strict_types=1);

namespace App\Authentication\Mobile;

use Illuminate\Contracts\Encryption\Encrypter;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\ClientRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Bridge\ScopeRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;

final class MobilePasswordAuthorizationServerFactory
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly AccessTokenRepository $accessTokens,
        private readonly ScopeRepository $scopes,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly Encrypter $encrypter,
    ) {}

    public function make(): AuthorizationServer
    {
        $server = new AuthorizationServer(
            $this->clients,
            $this->accessTokens,
            $this->scopes,
            $this->privateKey(),
            Passport::tokenEncryptionKey($this->encrypter),
        );
        $server->setDefaultScope(Passport::$defaultScope);
        $server->revokeRefreshTokens(Passport::$revokeRefreshTokenAfterUse);

        $grant = new MobilePasswordSignInGrant($this->refreshTokens);
        $grant->setRefreshTokenTTL(Passport::refreshTokensExpireIn());
        $server->enableGrantType($grant, Passport::tokensExpireIn());

        return $server;
    }

    private function privateKey(): CryptKey
    {
        $key = str_replace('\\n', "\n", (string) (config('passport.private_key') ?? ''));

        if ($key === '') {
            $key = 'file://'.Passport::keyPath('oauth-private.key');
        }

        return new CryptKey($key, null, Passport::$validateKeyPermissions);
    }
}
