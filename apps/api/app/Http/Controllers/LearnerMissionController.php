<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Http\Requests\Learner\EnrollmentMissionRequest;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Missions\DailyMissionReader;
use Illuminate\Http\JsonResponse;

final class LearnerMissionController extends Controller
{
    public function __construct(
        private readonly DailyMissionReader $missions,
    ) {}

    public function show(
        EnrollmentMissionRequest $request,
        string $mission,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $definition = $this->missions->resolve(
                learner: $principal->user,
                enrollmentId: (string) $request->validated(
                    'enrollment_id',
                ),
                missionId: $mission,
            );
        } catch (
            CurriculumOperationRejected
            |LearningOperationRejected
        ) {
            abort(404);
        }

        return response()->json($definition->publicPayload())
            ->header('Cache-Control', 'no-store');
    }
}
