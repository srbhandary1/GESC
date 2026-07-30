<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Http\Requests\Learner\CompleteOnboardingRequest;
use App\Learning\Exceptions\LearningIdempotencyConflict;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Home\LearnerHomeService;
use App\Learning\Onboarding\LearnerOnboardingService;
use App\Observability\CorrelationId;
use Illuminate\Http\JsonResponse;

final class LearnerOnboardingController extends Controller
{
    public function __construct(
        private readonly LearnerOnboardingService $onboarding,
        private readonly LearnerHomeService $home,
    ) {}

    public function update(
        CompleteOnboardingRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $this->onboarding->complete(
                learner: $principal->user,
                adultConfirmed: (bool) $request->validated(
                    'adult_confirmed',
                ),
                goalKey: (string) $request->validated('goal_key'),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
                correlationId: CorrelationId::forRequest($request),
            );
            $payload = $this->home->forLearner($principal->user);
        } catch (LearningIdempotencyConflict) {
            abort(409);
        } catch (LearningOperationRejected) {
            abort(404);
        }

        return response()->json($payload)
            ->header('Cache-Control', 'no-store');
    }
}
