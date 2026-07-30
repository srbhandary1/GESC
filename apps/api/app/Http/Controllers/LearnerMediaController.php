<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Http\Requests\Learner\MediaContentRequest;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Media\VerifiedPrivateAudioResponder;
use App\Learning\Missions\DailyMissionReader;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LearnerMediaController extends Controller
{
    public function __construct(
        private readonly DailyMissionReader $missions,
        private readonly VerifiedPrivateAudioResponder $media,
    ) {}

    public function show(
        MediaContentRequest $request,
        string $asset,
    ): StreamedResponse {
        $principal = AuthenticatedPrincipal::fromRequest($request);

        try {
            $mission = $this->missions->resolve(
                learner: $principal->user,
                enrollmentId: (string) $request->validated(
                    'enrollment_id',
                ),
                missionId: (string) $request->validated('mission_id'),
            );

            if (
                $mission->modelAudioAssetId !== $asset
            ) {
                throw new LearningOperationRejected;
            }

            return $this->media->respond($mission);
        } catch (
            CurriculumOperationRejected
            |LearningOperationRejected
        ) {
            abort(404);
        }
    }
}
