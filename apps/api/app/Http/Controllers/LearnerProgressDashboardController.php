<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Progress\LearnerProgressDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LearnerProgressDashboardController extends Controller
{
    public function __construct(
        private readonly LearnerProgressDashboardService $dashboard,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $payload = $this->dashboard->forLearner($principal->user);
        } catch (LearningOperationRejected) {
            abort(404);
        }

        return response()->json($payload)
            ->header('Cache-Control', 'no-store');
    }
}
