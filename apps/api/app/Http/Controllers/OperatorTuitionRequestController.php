<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Http\Requests\Tuition\AcknowledgeTuitionRequestRequest;
use App\Learning\Tuition\Exceptions\TuitionRequestIdempotencyConflict;
use App\Learning\Tuition\TuitionRequestService;
use App\Operator\LearnerAdministration\Exceptions\LearnerAdministrationRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperatorTuitionRequestController extends Controller
{
    public function __construct(
        private readonly TuitionRequestService $tuition,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        return response()->json([
            'requests' => $this->tuition->listForOperator($principal->user),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function acknowledge(
        AcknowledgeTuitionRequestRequest $request,
        string $tuitionRequest,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->tuition->acknowledge(
                $principal->user,
                $tuitionRequest,
                (string) $request->validated('idempotency_key'),
            );
        } catch (TuitionRequestIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (LearnerAdministrationRejected $exception) {
            throw $exception;
        }

        return response()->json(
            $result['request'],
            $result['created'] ? Response::HTTP_OK : Response::HTTP_OK,
        )->header('Cache-Control', 'private, no-store');
    }
}
