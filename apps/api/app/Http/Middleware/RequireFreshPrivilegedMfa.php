<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authentication\Sessions\AuthenticationSessionService;
use App\Models\AuthenticationSession;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireFreshPrivilegedMfa
{
    public function __construct(private AuthenticationSessionService $sessions) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authenticationSession = $request->attributes->get(
            EnforceAuthenticationSession::REQUEST_ATTRIBUTE,
        );

        if (
            ! $authenticationSession instanceof AuthenticationSession
            || ! $this->sessions->hasFreshMfa($authenticationSession)
        ) {
            throw new AuthorizationException('A recent privileged MFA assertion is required.');
        }

        return $next($request);
    }
}
