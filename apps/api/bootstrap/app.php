<?php

declare(strict_types=1);

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Problems\ProblemDetailsFactory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignCorrelationId::class);
        $middleware->preventRequestsDuringMaintenance([
            'api/v1/health/live',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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

                return $problems->fromExceptionResponse($request, $response);
            },
        );
    })
    ->create();
