<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authentication\Mobile\OAuthAuthorizationTarget;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforcePassportAuthenticationSession
{
    public function __construct(
        private AuthenticationSessionService $sessions,
        private OAuthAuthorizationTarget $targets,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (! $user instanceof Authenticatable) {
            return $this->guestResponse($request);
        }

        $persistedUser = User::query()->find($user->getAuthIdentifier());

        if (! $persistedUser instanceof User || $persistedUser->status !== User::STATUS_ACTIVE) {
            $this->sessions->revokeCurrent($user, $request, 'user_not_active');

            return $this->guestResponse($request);
        }

        Auth::guard('web')->setUser($persistedUser);
        $authenticationSession = $this->sessions->touch($persistedUser, $request);

        if ($authenticationSession === null) {
            $this->sessions->revokeCurrent($persistedUser, $request, 'invalid_or_expired');

            return $this->guestResponse($request);
        }

        $request->attributes->set(
            EnforceAuthenticationSession::REQUEST_ATTRIBUTE,
            $authenticationSession,
        );

        return $next($request);
    }

    private function guestResponse(Request $request): RedirectResponse
    {
        if ($request->isMethod('GET') && $this->targets->capture($request) !== null) {
            return redirect()->route('auth.mobile.sign-in');
        }

        throw new AuthenticationException;
    }
}
