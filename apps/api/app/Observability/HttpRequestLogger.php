<?php

declare(strict_types=1);

namespace App\Observability;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;

final class HttpRequestLogger
{
    public function handle(RequestHandled $event): void
    {
        if (! $event->request->is('api/*')) {
            return;
        }

        $route = $event->request->route();
        $routeName = $route instanceof Route ? $route->getName() : null;
        $status = $event->response->getStatusCode();
        $startedAt = $event->request->attributes->get(CorrelationId::STARTED_AT_ATTRIBUTE);
        $durationMilliseconds = is_int($startedAt)
            ? max(0.0, (hrtime(true) - $startedAt) / 1_000_000)
            : null;

        $context = [
            'http_method' => $event->request->getMethod(),
            'route_name' => $routeName,
            'status_code' => $status,
            'duration_ms' => $durationMilliseconds === null
                ? null
                : round($durationMilliseconds, 3),
        ];

        if ($status >= 500) {
            Log::warning('http.request_completed', $context);

            return;
        }

        Log::info('http.request_completed', $context);
    }
}
