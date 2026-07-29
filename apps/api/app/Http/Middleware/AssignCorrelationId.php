<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Observability\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = CorrelationId::initialize($request);
        $request->attributes->set(CorrelationId::STARTED_AT_ATTRIBUTE, hrtime(true));

        $response = $next($request);
        $response->headers->set(CorrelationId::HEADER, $correlationId);

        return $response;
    }
}
