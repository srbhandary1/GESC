<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Conversation\Memory\Exceptions\MemoryIdempotencyConflict;
use App\Conversation\Memory\Exceptions\MemoryOperationRejected;
use App\Conversation\Memory\LearnerMemoryService;
use App\Http\Requests\Memory\CreateLearnerMemoryFactRequest;
use App\Http\Requests\Memory\DeleteLearnerMemoryFactRequest;
use App\Http\Requests\Memory\UpdateLearnerMemoryFactRequest;
use App\Learning\Exceptions\LearningOperationRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LearnerMemoryController extends Controller
{
    public function __construct(
        private readonly LearnerMemoryService $memory,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $facts = $this->memory->list($principal->user);
        } catch (LearningOperationRejected|MemoryOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'facts' => $facts,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(
        CreateLearnerMemoryFactRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->memory->create(
                learner: $principal->user,
                factText: (string) $request->validated('fact_text'),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (MemoryIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (LearningOperationRejected|MemoryOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            $result,
            $result['replayed'] === true
                ? Response::HTTP_OK
                : Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    public function update(
        UpdateLearnerMemoryFactRequest $request,
        string $fact,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->memory->update(
                learner: $principal->user,
                factId: $fact,
                factText: (string) $request->validated('fact_text'),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (MemoryIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (LearningOperationRejected|MemoryOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function destroy(
        DeleteLearnerMemoryFactRequest $request,
        string $fact,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->memory->delete(
                learner: $principal->user,
                factId: $fact,
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (MemoryIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (LearningOperationRejected|MemoryOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }
}
