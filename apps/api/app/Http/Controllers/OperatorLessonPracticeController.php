<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Curriculum\LessonPractice\Exceptions\LessonPracticeIdempotencyConflict;
use App\Curriculum\LessonPractice\Exceptions\LessonPracticeOperationRejected;
use App\Curriculum\LessonPractice\LessonPracticeAttachmentService;
use App\Http\Requests\Operator\LessonPracticeAttachRequest;
use App\Models\User;
use App\Operator\MediaLibrary\MediaLibraryAuthorization;
use App\Tenancy\OrganizationAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperatorLessonPracticeController extends Controller
{
    public function __construct(
        private readonly LessonPracticeAttachmentService $lessonPractice,
        private readonly MediaLibraryAuthorization $mediaAuthorization,
        private readonly OrganizationAuthorization $authorization,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $actor = AuthenticatedPrincipal::fromRequest($request)->user;
        $this->assertAuthor($actor);
        $context = $this->mediaAuthorization->context($actor);

        $validated = $request->validate([
            'program_id' => ['required', 'string', 'ulid'],
            'curriculum_version_id' => ['required', 'string', 'ulid'],
            'concept_key' => ['required', 'string', 'max:120'],
            'canonical' => ['required', 'array'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $result = $this->lessonPractice->createBlueprint(
                context: $context,
                programId: (string) $validated['program_id'],
                curriculumVersionId: (string) $validated['curriculum_version_id'],
                conceptKey: (string) $validated['concept_key'],
                canonical: $validated['canonical'],
                idempotencyKey: (string) $validated['idempotency_key'],
            );
        } catch (LessonPracticeIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (LessonPracticeOperationRejected) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $blueprint = $result['blueprint'];

        return response()->json([
            'id' => $blueprint->getKey(),
            'concept_key' => $blueprint->concept_key,
            'revision_number' => $blueprint->revision_number,
            'state' => $blueprint->state,
            'content_hash' => $blueprint->content_hash,
            'replayed' => ! $result['created'],
        ], $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK)
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $blueprint): JsonResponse
    {
        $actor = AuthenticatedPrincipal::fromRequest($request)->user;
        $this->assertAuthor($actor);
        $context = $this->mediaAuthorization->context($actor);

        try {
            $row = $this->lessonPractice->getBlueprint($context, $blueprint);
        } catch (LessonPracticeOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'id' => $row->getKey(),
            'program_id' => $row->program_id,
            'curriculum_version_id' => $row->curriculum_version_id,
            'concept_key' => $row->concept_key,
            'revision_number' => $row->revision_number,
            'state' => $row->state,
            'content_hash' => $row->content_hash,
            'canonical' => $row->canonical_json,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function storeReview(Request $request, string $blueprint): JsonResponse
    {
        $actor = AuthenticatedPrincipal::fromRequest($request)->user;
        $this->assertAuthor($actor);
        $context = $this->mediaAuthorization->context($actor);

        $validated = $request->validate([
            'review_kind' => ['required', 'in:english_academic,kannada'],
            'decision' => ['required', 'in:approved'],
            'blueprint_content_hash' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            $result = $this->lessonPractice->recordReview(
                context: $context,
                blueprintId: $blueprint,
                reviewKind: (string) $validated['review_kind'],
                decision: (string) $validated['decision'],
                expectedContentHash: (string) $validated['blueprint_content_hash'],
                idempotencyKey: (string) $validated['idempotency_key'],
            );
        } catch (LessonPracticeIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (LessonPracticeOperationRejected) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'id' => $result['review']->getKey(),
            'review_kind' => $result['review']->review_kind,
            'decision' => $result['review']->decision,
            'replayed' => ! $result['created'],
        ], $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK)
            ->header('Cache-Control', 'private, no-store');
    }

    public function storeAttachments(
        LessonPracticeAttachRequest $request,
        string $blueprint,
    ): JsonResponse {
        $actor = AuthenticatedPrincipal::fromRequest($request)->user;
        if (! $this->authorization->allows($actor, 'curriculum.author')
            || ! $this->authorization->allows($actor, 'media.metadata.manage')
        ) {
            abort(Response::HTTP_NOT_FOUND);
        }
        $context = $this->mediaAuthorization->context($actor);

        try {
            $result = $this->lessonPractice->attachParts(
                context: $context,
                blueprintId: $blueprint,
                expectedContentHash: (string) $request->validated('blueprint_content_hash'),
                parts: array_values($request->validated('parts')),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );
        } catch (LessonPracticeIdempotencyConflict) {
            abort(Response::HTTP_CONFLICT);
        } catch (LessonPracticeOperationRejected) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($result['created'] === false && $result['media'] === []) {
            abort(Response::HTTP_CONFLICT);
        }

        return response()->json([
            'blueprint_id' => $blueprint,
            'replayed' => ! $result['created'],
            'parts' => array_map(
                static fn ($row): array => [
                    'part_id' => $row->part_id,
                    'part_order' => $row->part_order,
                    'media_asset_id' => $row->media_asset_id,
                ],
                $result['media'],
            ),
        ], $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK)
            ->header('Cache-Control', 'private, no-store');
    }

    private function assertAuthor(User $actor): void
    {
        if (! $this->authorization->allows($actor, 'curriculum.author')) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
