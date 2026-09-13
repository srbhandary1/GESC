<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Curriculum\Exceptions\CurriculumHeadConflict;
use App\Curriculum\Exceptions\CurriculumIdempotencyConflict;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\ContentReviewCommands;
use App\Curriculum\Governance\CurriculumGraphGovernanceCommands;
use App\Curriculum\Publication\CurriculumPublicationActivator;
use App\Curriculum\Publication\CurriculumPublicationScheduleCancellationService;
use App\Curriculum\Publication\CurriculumPublicationScheduler;
use App\Curriculum\Publication\CurriculumRetirementService;
use App\Http\Requests\Operator\ContentWorkspace\AbandonContentWorkspaceRequest;
use App\Http\Requests\Operator\ContentWorkspace\ActivateContentPublicationRequest;
use App\Http\Requests\Operator\ContentWorkspace\AppendEnglishContentRequest;
use App\Http\Requests\Operator\ContentWorkspace\AppendKannadaContentRequest;
use App\Http\Requests\Operator\ContentWorkspace\CancelContentPublicationScheduleRequest;
use App\Http\Requests\Operator\ContentWorkspace\CorrectContentProgramDescriptorRequest;
use App\Http\Requests\Operator\ContentWorkspace\CreateContentDocumentRequest;
use App\Http\Requests\Operator\ContentWorkspace\CreateContentProgramRequest;
use App\Http\Requests\Operator\ContentWorkspace\CreateContentSuccessorRequest;
use App\Http\Requests\Operator\ContentWorkspace\ListContentWorkspaceRequest;
use App\Http\Requests\Operator\ContentWorkspace\RecordStructureContentReviewRequest;
use App\Http\Requests\Operator\ContentWorkspace\RetireContentPublicationRequest;
use App\Http\Requests\Operator\ContentWorkspace\SaveContentGraphRequest;
use App\Http\Requests\Operator\ContentWorkspace\ScheduleContentPublicationRequest;
use App\Http\Requests\Operator\ContentWorkspace\VersionedContentWorkspaceRequest;
use App\Http\Requests\Operator\ContentWorkspace\WithdrawContentReviewRequest;
use App\Models\User;
use App\Operator\ContentWorkspace\ContentWorkspaceCommandResult;
use App\Operator\ContentWorkspace\ContentWorkspaceCommands;
use App\Operator\ContentWorkspace\ContentWorkspaceQuery;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceConflict;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceRejected;
use App\Operator\Exceptions\InvalidOperatorCursor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class OperatorContentWorkspaceController extends Controller
{
    public function __construct(
        private readonly ContentWorkspaceQuery $queries,
        private readonly ContentWorkspaceCommands $commands,
        private readonly CurriculumGraphGovernanceCommands $graphReviews,
        private readonly ContentReviewCommands $contentReviews,
        private readonly CurriculumPublicationScheduler $scheduler,
        private readonly CurriculumPublicationActivator $activator,
        private readonly CurriculumPublicationScheduleCancellationService $scheduleCancellations,
        private readonly CurriculumRetirementService $retirements,
    ) {}

    public function workspaces(ListContentWorkspaceRequest $request): JsonResponse
    {
        return $this->read(function (User $actor) use ($request): array {
            $cursor = $request->validated('cursor');

            return $this->queries->workspaces(
                $actor,
                (int) ($request->validated('limit') ?? 25),
                is_string($cursor) ? $cursor : null,
            );
        }, $request);
    }

    public function workspace(Request $request, string $curriculumVersion): JsonResponse
    {
        return $this->read(
            fn (User $actor): array => $this->queries->workspace(
                $actor,
                $curriculumVersion,
            ),
            $request,
        );
    }

    public function preview(
        ListContentWorkspaceRequest $request,
        string $curriculumVersion,
    ): JsonResponse {
        return $this->read(
            fn (User $actor): array => $this->queries->preview(
                $actor,
                $curriculumVersion,
                (string) $request->validated('document_key'),
            ),
            $request,
        );
    }

    public function reviewQueue(ListContentWorkspaceRequest $request): JsonResponse
    {
        return $this->read(function (User $actor) use ($request): array {
            $cursor = $request->validated('cursor');

            return $this->queries->reviewQueue(
                $actor,
                (string) $request->validated('queue'),
                (int) ($request->validated('limit') ?? 25),
                is_string($cursor) ? $cursor : null,
            );
        }, $request);
    }

    public function history(
        ListContentWorkspaceRequest $request,
        string $curriculumVersion,
    ): JsonResponse {
        return $this->read(function (User $actor) use ($request, $curriculumVersion): array {
            $cursor = $request->validated('cursor');

            return $this->queries->history(
                $actor,
                $curriculumVersion,
                (int) ($request->validated('limit') ?? 25),
                is_string($cursor) ? $cursor : null,
            );
        }, $request);
    }

    public function createProgram(CreateContentProgramRequest $request): JsonResponse
    {
        $actor = $this->actor($request);

        return $this->command($this->commands->createProgram(
            actor: $actor,
            programKey: (string) $request->validated('program_key'),
            internalName: (string) $request->validated('internal_name'),
            versionKey: (string) $request->validated('version_key'),
            expectedVersion: (int) $request->validated('expected_version'),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function correctDescriptor(
        CorrectContentProgramDescriptorRequest $request,
        string $program,
    ): JsonResponse {
        return $this->command($this->commands->correctDescriptor(
            actor: $this->actor($request),
            programId: $program,
            internalName: (string) $request->validated('internal_name'),
            expectedDescriptorVersion: (int) $request->validated(
                'expected_descriptor_version',
            ),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function createSuccessor(
        CreateContentSuccessorRequest $request,
        string $curriculumVersion,
    ): JsonResponse {
        return $this->command($this->commands->createSuccessor(
            actor: $this->actor($request),
            sourceCurriculumVersionId: $curriculumVersion,
            successorVersionKey: (string) $request->validated(
                'successor_version_key',
            ),
            expectedSourceVersion: (int) $request->validated(
                'expected_source_version',
            ),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function saveGraph(
        SaveContentGraphRequest $request,
        string $curriculumVersion,
    ): JsonResponse {
        return $this->command($this->commands->saveGraph(
            actor: $this->actor($request),
            curriculumVersionId: $curriculumVersion,
            expectedWorkspaceVersion: (int) $request->validated(
                'expected_workspace_version',
            ),
            graph: $this->array($request->validated('graph')),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function submitReview(
        VersionedContentWorkspaceRequest $request,
        string $curriculumVersion,
    ): JsonResponse {
        return $this->command($this->commands->submitGraphReview(
            actor: $this->actor($request),
            curriculumVersionId: $curriculumVersion,
            expectedWorkspaceVersion: (int) $request->validated(
                'expected_workspace_version',
            ),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function abandon(
        AbandonContentWorkspaceRequest $request,
        string $curriculumVersion,
    ): JsonResponse {
        return $this->command($this->commands->abandon(
            actor: $this->actor($request),
            curriculumVersionId: $curriculumVersion,
            expectedWorkspaceVersion: (int) $request->validated(
                'expected_workspace_version',
            ),
            reasonKey: (string) $request->validated('reason_key'),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function createDocument(
        CreateContentDocumentRequest $request,
        string $curriculumVersion,
    ): JsonResponse {
        return $this->command($this->commands->createDocument(
            actor: $this->actor($request),
            curriculumVersionId: $curriculumVersion,
            documentKey: (string) $request->validated('document_key'),
            contentType: (string) $request->validated('content_type'),
            learnerVisible: (bool) $request->validated('learner_visible'),
            payload: $this->array($request->validated('payload')),
            expectedWorkspaceVersion: (int) $request->validated(
                'expected_workspace_version',
            ),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function appendEnglish(
        AppendEnglishContentRequest $request,
        string $document,
    ): JsonResponse {
        $expectedHead = $request->validated('expected_head_revision_id');

        return $this->command($this->commands->appendEnglish(
            actor: $this->actor($request),
            contentDocumentId: $document,
            expectedHeadRevisionId: is_string($expectedHead) ? $expectedHead : null,
            expectedRevisionNumber: (int) $request->validated(
                'expected_revision_number',
            ),
            contentType: (string) $request->validated('content_type'),
            learnerVisible: (bool) $request->validated('learner_visible'),
            payload: $this->array($request->validated('payload')),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function appendKannada(
        AppendKannadaContentRequest $request,
        string $revision,
    ): JsonResponse {
        $expectedVariant = $request->validated('expected_head_variant_id');

        return $this->command($this->commands->appendKannada(
            actor: $this->actor($request),
            sourceRevisionId: $revision,
            sourceContentHash: (string) $request->validated(
                'source_content_hash',
            ),
            expectedSourceRevisionNumber: (int) $request->validated(
                'expected_source_revision_number',
            ),
            expectedHeadVariantId: is_string($expectedVariant)
                ? $expectedVariant
                : null,
            expectedVariantRevisionNumber: (int) $request->validated(
                'expected_variant_revision_number',
            ),
            payload: $this->array($request->validated('payload')),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        ));
    }

    public function reviewStructure(
        RecordStructureContentReviewRequest $request,
    ): JsonResponse {
        return $this->governance(function () use ($request): array {
            $reviewId = $this->graphReviews->review(
                actor: $this->actor($request),
                curriculumGraphRevisionId: (string) $request->validated(
                    'curriculum_graph_revision_id',
                ),
                graphHash: (string) $request->validated('graph_hash'),
                authorityAppointmentId: (string) $request->validated(
                    'authority_appointment_id',
                ),
                decision: (string) $request->validated('decision'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
                rationaleKey: (string) $request->validated('rationale_key'),
            );

            return ['curriculum_graph_review_id' => $reviewId];
        });
    }

    public function withdrawReview(
        WithdrawContentReviewRequest $request,
        string $review,
    ): JsonResponse {
        return $this->governance(function () use ($request, $review): array {
            $kind = (string) $request->validated('review_kind');
            $appointment = (string) $request->validated(
                'authority_appointment_id',
            );
            $rationale = (string) $request->validated('rationale_key');
            $idempotency = (string) $request->validated('idempotency_key');
            $actor = $this->actor($request);
            $resultId = $kind === 'structure'
                ? $this->graphReviews->withdraw(
                    actor: $actor,
                    curriculumGraphReviewId: $review,
                    authorityAppointmentId: $appointment,
                    rationaleKey: $rationale,
                    idempotencyKey: $idempotency,
                )
                : $this->contentReviews->withdraw(
                    actor: $actor,
                    contentReviewId: $review,
                    authorityAppointmentId: $appointment,
                    rationaleKey: $rationale,
                    idempotencyKey: $idempotency,
                );

            return ['withdrawal_review_id' => $resultId];
        });
    }

    public function schedule(ScheduleContentPublicationRequest $request): JsonResponse
    {
        return $this->governance(function () use ($request): array {
            $result = $this->scheduler->schedule(
                actor: $this->actor($request),
                curriculumVersionId: (string) $request->validated(
                    'curriculum_version_id',
                ),
                activationAt: CarbonImmutable::parse(
                    (string) $request->validated('activation_at'),
                ),
                expectedHeadVersion: (int) $request->validated(
                    'expected_head_version',
                ),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );

            return [
                'publication_id' => $result->publicationId,
                'curriculum_version_id' => $result->curriculumVersionId,
                'manifest_hash' => $result->manifestHash,
                'activation_at' => $result->activationAt->toIso8601String(),
                'head_version' => $result->headVersion,
                'replayed' => $result->replayed,
            ];
        });
    }

    public function activate(ActivateContentPublicationRequest $request): JsonResponse
    {
        return $this->governance(function () use ($request): array {
            $result = $this->activator->activate(
                actor: $this->actor($request),
                publicationId: (string) $request->validated('publication_id'),
                expectedHeadVersion: (int) $request->validated(
                    'expected_head_version',
                ),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );

            return [
                'publication_id' => $result->publicationId,
                'curriculum_version_id' => $result->curriculumVersionId,
                'manifest_hash' => $result->manifestHash,
                'activated_at' => $result->activatedAt->toIso8601String(),
                'head_version' => $result->headVersion,
                'replayed' => $result->replayed,
            ];
        });
    }

    public function cancelSchedule(
        CancelContentPublicationScheduleRequest $request,
    ): JsonResponse {
        return $this->governance(function () use ($request): array {
            $result = $this->scheduleCancellations->cancel(
                actor: $this->actor($request),
                publicationId: (string) $request->validated('publication_id'),
                expectedHeadVersion: (int) $request->validated(
                    'expected_head_version',
                ),
                reasonKey: (string) $request->validated('reason_key'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );

            return [
                'publication_id' => $result->publicationId,
                'curriculum_version_id' => $result->curriculumVersionId,
                'cancelled_at' => $result->cancelledAt->toIso8601String(),
                'head_version' => $result->headVersion,
                'replayed' => $result->replayed,
            ];
        });
    }

    public function retire(RetireContentPublicationRequest $request): JsonResponse
    {
        return $this->governance(function () use ($request): array {
            $result = $this->retirements->retire(
                actor: $this->actor($request),
                programId: (string) $request->validated('program_id'),
                expectedHeadVersion: (int) $request->validated(
                    'expected_head_version',
                ),
                idempotencyKey: (string) $request->validated('idempotency_key'),
                reasonKey: (string) $request->validated('reason_key'),
            );

            return [
                'program_id' => $result->programId,
                'publication_id' => $result->publicationId,
                'curriculum_version_id' => $result->curriculumVersionId,
                'head_version' => $result->headVersion,
                'replayed' => $result->replayed,
            ];
        });
    }

    /** @param callable(User): array<string, mixed> $callback */
    private function read(callable $callback, Request $request): JsonResponse
    {
        try {
            $payload = $callback($this->actor($request));
        } catch (InvalidOperatorCursor) {
            throw ValidationException::withMessages([
                'cursor' => ['The cursor is invalid or expired.'],
            ]);
        }

        return response()->json($payload)->header(
            'Cache-Control',
            'private, no-store',
        );
    }

    private function command(ContentWorkspaceCommandResult $result): JsonResponse
    {
        return response()->json(
            [
                'result_type' => $result->resultType,
                'result_id' => $result->resultId,
                'replayed' => $result->replayed,
                ...$result->data,
            ],
            $result->replayed ? Response::HTTP_OK : Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    /** @param callable(): array<string, mixed> $callback */
    private function governance(callable $callback): JsonResponse
    {
        try {
            $payload = $callback();
        } catch (CurriculumIdempotencyConflict $exception) {
            throw new ContentWorkspaceConflict(
                ContentWorkspaceConflict::IDEMPOTENCY,
                $exception,
            );
        } catch (CurriculumHeadConflict $exception) {
            throw new ContentWorkspaceConflict(
                ContentWorkspaceConflict::HEAD,
                $exception,
            );
        } catch (CurriculumOperationRejected $exception) {
            throw new ContentWorkspaceRejected(previous: $exception);
        }

        $replayed = ($payload['replayed'] ?? false) === true;

        return response()->json(
            $payload,
            $replayed ? Response::HTTP_OK : Response::HTTP_CREATED,
        )->header('Cache-Control', 'private, no-store');
    }

    private function actor(Request $request): User
    {
        return AuthenticatedPrincipal::fromRequest($request)->user;
    }

    /** @return array<string, mixed> */
    private function array(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw ValidationException::withMessages([
                'payload' => ['The payload must be an object.'],
            ]);
        }

        return $value;
    }
}
