<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authentication\Sessions\AuthenticationSessionService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforceAuthenticationSession
{
    public const REQUEST_ATTRIBUTE = 'authentication_session';

    public function __construct(private AuthenticationSessionService $sessions) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (! $user instanceof Authenticatable) {
            throw new AuthenticationException;
        }

        $authenticationSession = $this->sessions->touch($user, $request);

        if ($authenticationSession === null) {
            $this->sessions->revokeCurrent($user, $request, 'invalid_or_expired');

            throw new AuthenticationException;
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $authenticationSession);

        return $next($request);
    }
}
