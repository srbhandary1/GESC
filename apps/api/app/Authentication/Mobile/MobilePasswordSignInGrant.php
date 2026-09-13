<?php

declare(strict_types=1);

namespace App\Authentication\Mobile;

use DateInterval;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MobilePasswordSignInGrant extends AbstractGrant
{
    public const string IDENTIFIER = 'password_sign_in';

    public function __construct(RefreshTokenRepositoryInterface $refreshTokenRepository)
    {
        $this->setRefreshTokenRepository($refreshTokenRepository);
        $this->refreshTokenTTL = new DateInterval('P1M');
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL,
    ): ResponseTypeInterface {
        $client = $this->validateClient($request);
        $userId = $this->getRequestParameter('user_id', $request);

        if (! is_string($userId)) {
            throw OAuthServerException::invalidRequest('user_id');
        }

        $scopes = $this->validateScopes(
            $this->getRequestParameter('scope', $request, $this->defaultScope),
        );
        $finalized = $this->scopeRepository->finalizeScopes(
            $scopes,
            $this->getIdentifier(),
            $client,
            $userId,
        );
        $accessToken = $this->issueAccessToken(
            $accessTokenTTL,
            $client,
            $userId,
            $finalized,
        );
        $this->getEmitter()->emit(new RequestAccessTokenEvent(
            RequestEvent::ACCESS_TOKEN_ISSUED,
            $request,
            $accessToken,
        ));
        $responseType->setAccessToken($accessToken);

        $refreshToken = $this->issueRefreshToken($accessToken);

        if ($refreshToken !== null) {
            $this->getEmitter()->emit(new RequestRefreshTokenEvent(
                RequestEvent::REFRESH_TOKEN_ISSUED,
                $request,
                $refreshToken,
            ));
            $responseType->setRefreshToken($refreshToken);
        }

        return $responseType;
    }

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    protected function supportsGrantType(ClientEntityInterface $client, string $grantType): bool
    {
        if ($grantType === self::IDENTIFIER) {
            return method_exists($client, 'supportsGrantType')
                && $client->supportsGrantType('authorization_code');
        }

        return parent::supportsGrantType($client, $grantType);
    }
}
