<?php

declare(strict_types=1);

namespace App\Authentication\Mobile;

use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

final class MobilePasswordTokenIssuer
{
    public function __construct(
        private readonly AuthorizationServer $server,
        private readonly Container $container,
    ) {}

    /**
     * @return array{
     *     token_type: 'Bearer',
     *     expires_in: int,
     *     access_token: string,
     *     refresh_token: string
     * }
     */
    public function issue(User $user, MobileOAuthClient $client): array
    {
        if (! $client->isApprovedNativeClient()) {
            throw new RuntimeException('The mobile client is not approved for password sign-in.');
        }

        $userId = (string) $user->getAuthIdentifier();

        if ($userId === '') {
            throw new RuntimeException('The authenticated user has no identifier.');
        }

        $request = (new PsrHttpFactory)->createRequest(Request::create('/', 'POST', [
            'grant_type' => MobilePasswordSignInGrant::IDENTIFIER,
            'client_id' => (string) $client->getKey(),
            'user_id' => $userId,
            'scope' => 'mobile:api',
        ]));
        $response = $this->server->respondToAccessTokenRequest(
            $request,
            $this->container->make(ResponseInterface::class),
        );
        $payload = json_decode($response->getBody()->__toString(), true);

        if (
            ! is_array($payload)
            || ($payload['token_type'] ?? null) !== 'Bearer'
            || ! is_int($payload['expires_in'] ?? null)
            || ! is_string($payload['access_token'] ?? null)
            || $payload['access_token'] === ''
            || ! is_string($payload['refresh_token'] ?? null)
            || $payload['refresh_token'] === ''
        ) {
            throw new RuntimeException('The authorization server did not issue a complete token family.');
        }

        return [
            'token_type' => 'Bearer',
            'expires_in' => $payload['expires_in'],
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'],
        ];
    }
}
