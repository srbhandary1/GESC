<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Symfony\Component\HttpFoundation\Response;

final class EnforceMobileOAuthPolicy
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->is('oauth/authorize')) {
            $state = $request->string('state')->toString();
            $clientId = $request->string('client_id')->toString();
            $unexpected = array_diff(array_keys($request->query()), [
                'client_id',
                'redirect_uri',
                'response_type',
                'scope',
                'state',
                'code_challenge',
                'code_challenge_method',
            ]);

            if (
                $unexpected !== []
                ||
                $request->string('response_type')->toString() !== 'code'
                || $this->hasClientAuthentication($request)
                || $request->string('scope')->toString() !== 'mobile:api'
                || $request->string('code_challenge_method')->toString() !== 'S256'
                || preg_match(
                    '/^[A-Za-z0-9_-]{43,128}$/',
                    $request->string('code_challenge')->toString(),
                ) !== 1
                || strlen($state) < 32
                || strlen($state) > 512
                || ! $this->isApprovedMobileClient($clientId)
            ) {
                return $this->error(
                    'invalid_request',
                    'Authorization requests require an active public mobile client, mobile:api scope, state and S256 PKCE.',
                );
            }
        }

        if ($request->isMethod('POST') && $request->is('oauth/token')) {
            $grantType = $request->string('grant_type')->toString();
            $installationId = $request->string('installation_id')->toString();
            $clientId = $request->string('client_id')->toString();
            $allowed = match ($grantType) {
                'authorization_code' => [
                    'grant_type',
                    'client_id',
                    'redirect_uri',
                    'code',
                    'code_verifier',
                    'installation_id',
                ],
                'refresh_token' => [
                    'grant_type',
                    'client_id',
                    'refresh_token',
                    'installation_id',
                ],
                default => [],
            };

            if (
                ! in_array($grantType, ['authorization_code', 'refresh_token'], true)
                || ! Str::isUlid($installationId)
                || array_diff(array_keys($request->all()), $allowed) !== []
                || $this->hasClientAuthentication($request)
                || ! $this->isApprovedMobileClient($clientId)
            ) {
                return $this->error(
                    'invalid_request',
                    'The mobile token request does not satisfy public-client policy.',
                );
            }
        }

        return $next($request);
    }

    private function hasClientAuthentication(Request $request): bool
    {
        return $request->headers->has('Authorization')
            || $request->server->has('PHP_AUTH_USER')
            || $request->server->has('PHP_AUTH_PW');
    }

    private function isApprovedMobileClient(string $clientId): bool
    {
        if (! Str::isUuid($clientId)) {
            return false;
        }

        /** @var Client|null $client */
        $client = Client::query()
            ->whereKey($clientId)
            ->where('revoked', false)
            ->first();

        if (! $client instanceof Client) {
            return false;
        }

        $provider = $client->getAttribute('provider');
        $effectiveProvider = is_string($provider) && $provider !== ''
            ? $provider
            : config('auth.guards.api.provider');

        return ! $client->confidential()
            && $client->firstParty()
            && $client->getAttribute('owner_type') === null
            && $client->getAttribute('owner_id') === null
            && $effectiveProvider === 'users'
            && $client->hasGrantType('authorization_code')
            && $client->hasGrantType('refresh_token')
            && $client->hasScope('mobile:api');
    }

    private function error(string $error, string $description): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'error_description' => $description,
        ], 400, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }
}
