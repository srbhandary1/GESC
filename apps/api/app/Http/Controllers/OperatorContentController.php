<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\ContentReviewCommands;
use App\Curriculum\Publication\CurriculumPublisher;
use App\Curriculum\Publication\CurriculumRollbackService;
use App\Curriculum\Publication\PublicationResult;
use App\Http\Requests\Operator\ListOperatorAuditEventsRequest;
use App\Http\Requests\Operator\PublishOperatorCurriculumRequest;
use App\Http\Requests\Operator\RecordEnglishContentReviewRequest;
use App\Http\Requests\Operator\RecordKannadaContentReviewRequest;
use App\Http\Requests\Operator\RollbackOperatorCurriculumRequest;
use App\Operator\OperatorAuditEventQuery;
use App\Operator\OperatorCurriculumDraftQuery;
use App\Operator\OperatorPublicationStatusQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperatorContentController extends Controller
{
    public function __construct(
        private readonly OperatorCurriculumDraftQuery $drafts,
        private readonly OperatorPublicationStatusQuery $publications,
        private readonly OperatorAuditEventQuery $auditEvents,
        private readonly ContentReviewCommands $reviews,
        private readonly CurriculumPublisher $publisher,
        private readonly CurriculumRollbackService $rollbacks,
    ) {}

    public function drafts(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->drafts->list($principal->user);
        } catch (CurriculumOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function publicationStatus(Request $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->publications->list($principal->user);
        } catch (CurriculumOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function auditEvents(
        ListOperatorAuditEventsRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);
        $limit = (int) ($request->validated('limit') ?? 50);

        try {
            $result = $this->auditEvents->list($principal->user, $limit);
        } catch (CurriculumOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, no-store');
    }

    public function reviewEnglish(
        RecordEnglishContentReviewRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $reviewId = $this->reviews->reviewEnglish(
                actor: $principal->user,
                sourceRevisionId: (string) $request->validated(
                    'source_revision_id',
                ),
                sourceContentHash: (string) $request->validated(
                    'source_content_hash',
                ),
                authorityAppointmentId: (string) $request->validated(
                    'authority_appointment_id',
                ),
                decision: (string) $request->validated('decision'),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
                rationaleKey: $this->nullableString(
                    $request->validated('rationale_key'),
                ),
            );
        } catch (CurriculumIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (CurriculumOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            ['content_review_id' => $reviewId],
            Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    public function reviewKannada(
        RecordKannadaContentReviewRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $reviewId = $this->reviews->reviewKannada(
                actor: $principal->user,
                sourceRevisionId: (string) $request->validated(
                    'source_revision_id',
                ),
                sourceContentHash: (string) $request->validated(
                    'source_content_hash',
                ),
                localizedVariantId: (string) $request->validated(
                    'localized_variant_id',
                ),
                localizedContentHash: (string) $request->validated(
                    'localized_content_hash',
                ),
                authorityAppointmentId: (string) $request->validated(
                    'authority_appointment_id',
                ),
                decision: (string) $request->validated('decision'),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
                rationaleKey: $this->nullableString(
                    $request->validated('rationale_key'),
                ),
            );
        } catch (CurriculumIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (CurriculumOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            ['content_review_id' => $reviewId],
            Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    public function publish(
        PublishOperatorCurriculumRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->publisher->publish(
                actor: $principal->user,
                curriculumVersionId: (string) $request->validated(
                    'curriculum_version_id',
                ),
                expectedHeadVersion: (int) $request->validated(
                    'expected_head_version',
                ),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
            );
        } catch (CurriculumIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (CurriculumOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            $this->publicationPayload($result),
            $result->replayed
                ? Response::HTTP_OK
                : Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    public function rollback(
        RollbackOperatorCurriculumRequest $request,
    ): JsonResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $result = $this->rollbacks->rollback(
                actor: $principal->user,
                programId: (string) $request->validated('program_id'),
                targetPublicationId: (string) $request->validated(
                    'target_publication_id',
                ),
                expectedHeadVersion: (int) $request->validated(
                    'expected_head_version',
                ),
                idempotencyKey: (string) $request->validated(
                    'idempotency_key',
                ),
                reasonKey: (string) $request->validated('reason_key'),
            );
        } catch (CurriculumIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (CurriculumOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            $this->publicationPayload($result),
            $result->replayed
                ? Response::HTTP_OK
                : Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    /**
     * @return array<string, mixed>
     */
    private function publicationPayload(PublicationResult $result): array
    {
        return [
            'publication_id' => $result->publicationId,
            'curriculum_version_id' => $result->curriculumVersionId,
            'manifest_hash' => $result->manifestHash,
            'head_version' => $result->headVersion,
            'replayed' => $result->replayed,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
