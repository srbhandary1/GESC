<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Http\Requests\Learner\CompleteOfflineLevelCheckpointRequest;
use App\Http\Requests\Learner\CompleteOfflineMissionRequest;
use App\Http\Requests\Learner\OfflineMissionBundleRequest;
use App\Learning\Exceptions\LearningIdempotencyConflict;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Offline\OfflineCompletionService;
use App\Learning\Offline\OfflineLevelBundleService;
use App\Learning\Offline\OfflineLevelCheckpointCompletionService;
use App\Learning\Offline\OfflineMissionBundleService;
use App\Observability\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LearnerOfflineController extends Controller
{
    public function __construct(
        private readonly OfflineMissionBundleService $bundles,
        private readonly OfflineCompletionService $completions,
        private readonly OfflineLevelBundleService $levelBundles,
        private readonly OfflineLevelCheckpointCompletionService $levelCheckpointCompletions,
    ) {}

    public function bundle(
        OfflineMissionBundleRequest $request,
        string $mission,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $bundle = $this->bundles->issue(
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

        return response()->json($bundle)
            ->header('Cache-Control', 'private, no-store');
    }

    public function complete(
        CompleteOfflineMissionRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $recording = $request->validated('local_recording');

        try {
            $result = $this->completions->complete(
                learner: $principal->user,
                bundleId: (string) $request->validated('bundle_id'),
                lease: (string) $request->validated('lease'),
                clientAttemptId: (string) $request->validated(
                    'client_attempt_id',
                ),
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

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function levelBundle(
        Request $request,
        int $levelNumber,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $bundle = $this->levelBundles->issue(
                learner: $principal->user,
                levelNumber: $levelNumber,
            );
        } catch (LearningOperationRejected) {
            abort(404);
        }

        return response()->json($bundle)
            ->header('Cache-Control', 'private, no-store');
    }

    public function completeLevelCheckpoint(
        CompleteOfflineLevelCheckpointRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->levelCheckpointCompletions->complete(
                learner: $principal->user,
                bundleId: (string) $request->validated('bundle_id'),
                lease: (string) $request->validated('lease'),
                levelNumber: (int) $request->validated('level_number'),
                answers: $request->validated('answers'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );
        } catch (LearningOperationRejected) {
            abort(404);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }
}
