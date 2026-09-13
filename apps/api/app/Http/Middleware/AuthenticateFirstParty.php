<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authentication\AuthenticatedPrincipal;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Models\AuthenticationSession;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateFirstParty
{
    public function __construct(
        private AuthenticationSessionService $sessions,
        private MobileTokenFamilyService $mobileFamilies,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $webUser = Auth::guard('web')->user();

        if ($webUser instanceof User) {
            if ($webUser->status !== User::STATUS_ACTIVE) {
                $this->sessions->revokeCurrent(
                    $webUser,
                    $request,
                    'user_inactive',
                );

                throw new AuthenticationException;
            }

            $authenticationSession = $this->sessions->touch(
                $webUser,
                $request,
            );

            if ($authenticationSession === null) {
                $this->sessions->revokeCurrent(
                    $webUser,
                    $request,
                    'invalid_or_expired',
                );

                throw new AuthenticationException;
            }

            return $this->continueAs(
                $request,
                $next,
                AuthenticatedPrincipal::web(
                    $webUser,
                    $authenticationSession,
                ),
            );
        }

        if ($request->bearerToken() === null) {
            throw new AuthenticationException;
        }

        $mobileUser = Auth::guard('api')->user();

        if (
            ! $mobileUser instanceof User
            || $mobileUser->status !== User::STATUS_ACTIVE
        ) {
            throw new AuthenticationException;
        }

        $accessToken = $mobileUser->currentAccessToken();

        if (
            ! $accessToken instanceof AccessToken
            || ! $accessToken->can('mobile:api')
        ) {
            throw new AuthenticationException;
        }

        $attributes = $accessToken->toArray();
        $accessTokenId = $attributes['oauth_access_token_id'] ?? null;

        if (! is_string($accessTokenId) || $accessTokenId === '') {
            throw new AuthenticationException;
        }

        $family = $this->mobileFamilies->activeForAccessToken(
            $accessTokenId,
            (string) $mobileUser->getKey(),
        );

        if ($family === null) {
            throw new AuthenticationException;
        }

        return $this->continueAs(
            $request,
            $next,
            AuthenticatedPrincipal::mobile($mobileUser, $family),
        );
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    private function continueAs(
        Request $request,
        Closure $next,
        AuthenticatedPrincipal $principal,
    ): Response {
        $request->attributes->set(
            AuthenticatedPrincipal::REQUEST_ATTRIBUTE,
            $principal,
        );
        if ($principal->webSession instanceof AuthenticationSession) {
            $request->attributes->set(
                EnforceAuthenticationSession::REQUEST_ATTRIBUTE,
                $principal->webSession,
            );
        }
        $request->setUserResolver(
            static fn (?string $guard = null): User => $principal->user,
        );

        return $next($request);
    }
}
