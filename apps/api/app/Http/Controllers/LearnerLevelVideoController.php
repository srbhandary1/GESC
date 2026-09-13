<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Http\Problems\ProblemDetailsFactory;
use App\Http\Requests\Learner\LessonVideoContentRequest;
use App\Http\Requests\Learner\LevelVideoCatalogRequest;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LevelVideos\PinnedLevelVideoReader;
use App\Learning\Media\VerifiedPrivateVideoResponder;
use App\Learning\Media\VideoRangeNotSatisfiable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LearnerLevelVideoController extends Controller
{
    public function __construct(
        private readonly PinnedLevelVideoReader $videos,
        private readonly VerifiedPrivateVideoResponder $responder,
        private readonly ProblemDetailsFactory $problems,
    ) {}

    public function index(LevelVideoCatalogRequest $request): JsonResponse
    {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $catalog = $this->videos->list(
                learner: $principal->user,
                enrollmentId: (string) $request->validated('enrollment_id'),
            );
        } catch (
            CurriculumOperationRejected
            |LearningOperationRejected
        ) {
            abort(404);
        }

        return response()
            ->json([
                'enrollment_id' => (string) $request->validated(
                    'enrollment_id',
                ),
                'videos' => array_map(
                    static fn ($video): array => $video->publicPayload(),
                    $catalog,
                ),
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(
        LessonVideoContentRequest $request,
        string $asset,
    ): JsonResponse|StreamedResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $video = $this->videos->resolve(
                learner: $principal->user,
                enrollmentId: (string) $request->validated(
                    'enrollment_id',
                ),
                lessonId: (string) $request->validated('lesson_id'),
                assetId: $asset,
            );

            return $this->responder->respond($video, $request);
        } catch (VideoRangeNotSatisfiable $exception) {
            return $this->problems->rangeNotSatisfiable(
                request: $request,
                totalBytes: $exception->totalBytes,
                etag: $exception->etag,
            );
        } catch (
            CurriculumOperationRejected
            |LearningOperationRejected
        ) {
            abort(404);
        }
    }
}
