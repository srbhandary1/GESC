<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Http\Requests\Operator\CreatePrivacyErasureRequest;
use App\Http\Requests\Operator\CreatePrivacyExportRequest;
use App\Http\Requests\Operator\ListSupportDiagnosticsRequest;
use App\Operator\Exceptions\OperatorPrivacyIdempotencyConflict;
use App\Operator\Exceptions\OperatorPrivacyRejected;
use App\Operator\PrivacyErasureService;
use App\Operator\PrivacyExportService;
use App\Operator\SupportDiagnosticsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperatorPrivacyController extends Controller
{
    public function __construct(
        private readonly SupportDiagnosticsQuery $diagnostics,
        private readonly PrivacyExportService $exports,
        private readonly PrivacyErasureService $erasures,
    ) {}

    public function diagnostics(
        ListSupportDiagnosticsRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $limit = (int) ($request->validated('limit') ?? 50);

        try {
            $result = $this->diagnostics->list($principal->user, $limit);
        } catch (OperatorPrivacyRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function createExport(
        CreatePrivacyExportRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->exports->create(
                actor: $principal->user,
                targetMembershipId: (string) $request->validated(
                    'target_membership_id',
                ),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (OperatorPrivacyIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (OperatorPrivacyRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result, Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    public function showExport(
        Request $request,
        string $exportRequest,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->exports->show(
                $principal->user,
                $exportRequest,
            );
        } catch (OperatorPrivacyRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function createErasure(
        CreatePrivacyErasureRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->erasures->create(
                actor: $principal->user,
                targetMembershipId: (string) $request->validated(
                    'target_membership_id',
                ),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (OperatorPrivacyIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (OperatorPrivacyRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result, Response::HTTP_CREATED)
            ->header('Cache-Control', 'private, no-store');
    }

    public function showErasure(
        Request $request,
        string $erasureRequest,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->erasures->show(
                $principal->user,
                $erasureRequest,
            );
        } catch (OperatorPrivacyRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }
}
