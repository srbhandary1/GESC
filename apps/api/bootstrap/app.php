<?php

declare(strict_types=1);

use App\Conversation\Dialogue\ConversationRecoveryUnavailable;
use App\Conversation\Dialogue\DialogueTurnOutcomeUnknown;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\AuthenticateFirstParty;
use App\Http\Middleware\EnforceAuthenticationSession;
use App\Http\Middleware\RequireFreshPrivilegedMfa;
use App\Http\Middleware\RequireWebAuthentication;
use App\Http\Middleware\ResolveOrganizationContext;
use App\Http\Problems\ProblemDetailsFactory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Proxies come from config/trustedproxy.php, which TrustProxies reads per
        // request. The environment file is not loaded yet at this point, so reading
        // TRUSTED_PROXIES here would silently ignore it.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->prepend(AssignCorrelationId::class);
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: [
            'api/v1/auth/mobile/password/*',
            'api/v1/auth/mobile/social/*',
        ]);
        $middleware->alias([
            'auth.first-party' => AuthenticateFirstParty::class,
            'auth.session' => EnforceAuthenticationSession::class,
            'auth.mfa' => RequireFreshPrivilegedMfa::class,
            'auth.password-confirmed' => RequireFreshPrivilegedMfa::class,
            'auth.web-only' => RequireWebAuthentication::class,
            'organization.context' => ResolveOrganizationContext::class,
            'scope' => CheckToken::class,
            'scopes' => CheckTokenForAnyScope::class,
        ]);
        $middleware->preventRequestsDuringMaintenance([
            'api/v1/health/live',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (DialogueTurnOutcomeUnknown $exception, Request $request) {
            return app(ProblemDetailsFactory::class)->fromExceptionResponse($request, response('', 409), $exception);
        });
        $exceptions->render(function (ConversationRecoveryUnavailable $exception) {
            return response()->json(['message' => 'Private conversation recovery is temporarily unavailable.'], 503);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->report(function (Throwable $exception): ?bool {
            $request = app()->bound('request') ? app('request') : null;

            if (! $request instanceof Request || ! $request->is('api/*')) {
                return null;
            }

            Log::error('http.request_failed', [
                'exception_class' => $exception::class,
            ]);

            return false;
        });

        $exceptions->respond(
            function (Response $response, Throwable $exception, Request $request): Response {
                if (! $request->is('api/*') || $response->getStatusCode() < 400) {
                    return $response;
                }

                /** @var ProblemDetailsFactory $problems */
                $problems = app(ProblemDetailsFactory::class);

                return $problems->fromExceptionResponse(
                    $request,
                    $response,
                    $exception,
                );
            },
        );
    })
    ->create();
