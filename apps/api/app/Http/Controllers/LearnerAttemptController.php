<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Http\Requests\Learner\ProgressRequest;
use App\Http\Requests\Learner\RecordAttemptResponseRequest;
use App\Http\Requests\Learner\StartAttemptRequest;
use App\Http\Requests\Learner\SubmitAttemptRequest;
use App\Learning\Attempts\DailyMissionAttemptService;
use App\Learning\Exceptions\LearningIdempotencyConflict;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Observability\CorrelationId;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class LearnerAttemptController extends Controller
{
    public function __construct(
        private readonly DailyMissionAttemptService $attempts,
    ) {}

    public function start(
        StartAttemptRequest $request,
        string $mission,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->attempts->start(
                learner: $principal->user,
                enrollmentId: (string) $request->validated(
                    'enrollment_id',
                ),
                missionId: $mission,
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
                correlationId: CorrelationId::forRequest($request),
            );
        } catch (LearningIdempotencyConflict) {
            abort(409);
        } catch (
            CurriculumOperationRejected
            |LearningOperationRejected
        ) {
            abort(404);
        }

        return response()->json([
            'id' => $result['id'],
            'status' => $result['status'],
        ], $result['replayed']
            ? Response::HTTP_OK
            : Response::HTTP_CREATED)
            ->header('Cache-Control', 'no-store');
    }

    public function respond(
        RecordAttemptResponseRequest $request,
        string $attempt,
    ): Response {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $recording = $request->validated('local_recording');

        try {
            $this->attempts->respond(
                learner: $principal->user,
                attemptId: $attempt,
                checkId: (string) $request->validated('check_id'),
                selectedOptionId: (string) $request->validated(
                    'selected_option_id',
                ),
                localRecording: is_array($recording)
                    ? $recording
                    : null,
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
                correlationId: CorrelationId::forRequest($request),
            );
        } catch (LearningIdempotencyConflict) {
            abort(409);
        } catch (
            CurriculumOperationRejected
            |LearningOperationRejected
        ) {
            abort(404);
        }

        return response('', Response::HTTP_NO_CONTENT)
            ->header('Cache-Control', 'no-store');
    }

    public function submit(
        SubmitAttemptRequest $request,
        string $attempt,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->attempts->submit(
                learner: $principal->user,
                attemptId: $attempt,
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
                correlationId: CorrelationId::forRequest($request),
            );
        } catch (LearningIdempotencyConflict) {
            abort(409);
        } catch (
            CurriculumOperationRejected
            |LearningOperationRejected
        ) {
            abort(404);
        }

        return response()->json($result)
            ->header('Cache-Control', 'no-store');
    }

    public function progress(ProgressRequest $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->attempts->progress(
                learner: $principal->user,
                enrollmentId: (string) $request->validated(
                    'enrollment_id',
                ),
            );
        } catch (
            CurriculumOperationRejected
            |LearningOperationRejected
        ) {
            abort(404);
        }

        return response()->json($result)
            ->header('Cache-Control', 'no-store');
    }
}
