<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authentication\AuthenticatedPrincipal;
use App\Authentication\AuthenticationTransport;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireWebAuthentication
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        if ($principal->transport !== AuthenticationTransport::WebSession) {
            throw new AuthorizationException(
                'Privileged operations require a web session.',
            );
        }

        return $next($request);
    }
}
