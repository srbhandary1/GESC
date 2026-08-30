<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Http\Requests\Tuition\CreateTuitionRequestRequest;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Tuition\Exceptions\TuitionRequestIdempotencyConflict;
use App\Learning\Tuition\Exceptions\TuitionRequestRejected;
use App\Learning\Tuition\InstituteContact;
use App\Learning\Tuition\TuitionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LearnerTuitionRequestController extends Controller
{
    public function __construct(
        private readonly TuitionRequestService $tuition,
        private readonly InstituteContact $instituteContact,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $requests = $this->tuition->listMine($principal->user);
        } catch (LearningOperationRejected|TuitionRequestRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'requests' => $requests,
            'institute_contact' => $this->instituteContact->learnerPayload(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(CreateTuitionRequestRequest $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->tuition->create(
                learner: $principal->user,
                preferredWindow: (string) $request->validated('preferred_window'),
                topic: (string) $request->validated('topic'),
                notes: is_string($request->validated('notes'))
                    ? (string) $request->validated('notes')
                    : null,
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );
        } catch (TuitionRequestIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (LearningOperationRejected|TuitionRequestRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            $result['request'],
            $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK,
        )->header('Cache-Control', 'private, no-store');
    }
}
