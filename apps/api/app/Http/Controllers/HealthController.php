<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Health\ReadinessChecker;
use App\Http\Problems\ProblemDetailsFactory;
use App\Observability\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class HealthController extends Controller
{
    public function live(Request $request): JsonResponse
    {
        return $this->healthyResponse($request, 'ok');
    }

    public function ready(
        Request $request,
        ReadinessChecker $checker,
        ProblemDetailsFactory $problems,
    ): JsonResponse {
        if (! $checker->isReady()) {
            return $problems->serviceUnavailable($request);
        }

        return $this->healthyResponse($request, 'ready');
    }

    private function healthyResponse(Request $request, string $status): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'service' => 'global-english-api',
            'checked_at' => Carbon::now('UTC')->toIso8601String(),
            'correlation_id' => CorrelationId::forRequest($request),
        ], headers: [
            'Cache-Control' => 'no-store',
        ]);
    }
}
