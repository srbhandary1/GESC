<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Learning\Home\LearnerHomeService;
use App\Learning\Missions\DailyMissionDefinition;
use App\Learning\Missions\DailyMissionReader;
use App\Learning\Review\ReviewDueBlockingService;
use App\Models\Membership;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Integration\TenancyIntegrationTestCase;

final class LearnerHomeDueReviewInventoryTest extends TenancyIntegrationTestCase
{
    public function test_surfaces_earliest_due_review_independently_of_today_mission(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        $learner = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $learner,
            Membership::TYPE_LEARNER,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['learning.participate'],
            'learner-home-due-review',
        );
        $enrollmentId = (string) Str::ulid();
        $dueMissionId = (string) Str::ulid();
        $upcomingMissionId = (string) Str::ulid();

        $reviewDue = $this->createMock(ReviewDueBlockingService::class);
        $reviewDue->method('firstDueIncompleteReview')
            ->willReturn([
                'id' => '01M2D900000000000000000001',
                'enrollment_id' => $enrollmentId,
                'due_at' => CarbonImmutable::now()->subHour()->toIso8601String(),
                'learning_objective_id' => '01M2D900000000000000000002',
                'curriculum_mission_id' => $dueMissionId,
                'interval_days' => 3,
                'timezone' => 'Asia/Kolkata',
            ]);
        $reviewDue->method('upcomingIncompleteReviews')
            ->willReturn([
                [
                    'id' => '01M2D900000000000000000003',
                    'enrollment_id' => $enrollmentId,
                    'due_at' => CarbonImmutable::now()->addDays(4)->toIso8601String(),
                    'learning_objective_id' => '01M2D900000000000000000004',
                    'curriculum_mission_id' => $upcomingMissionId,
                    'interval_days' => 7,
                    'timezone' => 'Asia/Kolkata',
                ],
            ]);
        $this->app->instance(ReviewDueBlockingService::class, $reviewDue);

        $missions = $this->createMock(DailyMissionReader::class);
        $missions->method('resolve')
            ->willReturnMap([
                [
                    $learner,
                    $enrollmentId,
                    $dueMissionId,
                    $this->missionDefinition(
                        $dueMissionId,
                        $enrollmentId,
                        'Due review mission',
                    ),
                ],
                [
                    $learner,
                    $enrollmentId,
                    $upcomingMissionId,
                    $this->missionDefinition(
                        $upcomingMissionId,
                        $enrollmentId,
                        'Upcoming review mission',
                    ),
                ],
            ]);
        $this->app->instance(DailyMissionReader::class, $missions);

        $home = $this->contexts()->run(
            $context,
            fn (): array => $this->app->make(LearnerHomeService::class)
                ->forLearner($learner),
        );

        self::assertNotNull($home['due_review']);
        self::assertSame($dueMissionId, $home['due_review']['mission_id']);
        self::assertSame('Due review mission', $home['due_review']['title']['text']);
        self::assertNull($home['next_mission']);
        self::assertCount(1, $home['upcoming_reviews']);
        self::assertSame(
            'Upcoming review mission',
            $home['upcoming_reviews'][0]['title']['text'],
        );
    }

    private function missionDefinition(
        string $missionId,
        string $enrollmentId,
        string $title,
    ): DailyMissionDefinition {
        return new DailyMissionDefinition(
            missionId: $missionId,
            enrollmentId: $enrollmentId,
            publicationId: (string) Str::ulid(),
            ownerOrganizationId: (string) Str::ulid(),
            curriculumVersionId: (string) Str::ulid(),
            objectiveId: (string) Str::ulid(),
            learningItemId: (string) Str::ulid(),
            manifestHash: hash('sha256', $missionId.'-manifest'),
            definitionHash: hash('sha256', $missionId.'-definition'),
            sourceRevisionId: (string) Str::ulid(),
            sourceContentHash: hash('sha256', $title),
            kannadaVariantId: (string) Str::ulid(),
            kannadaContentHash: hash('sha256', $title.'-kn'),
            goalKey: 'workplace-confidence',
            estimatedMinutes: 5,
            title: $title,
            kannadaGuidance: 'Guidance',
            modelText: 'Model text',
            transcript: 'Transcript',
            canDo: 'Can do',
            checkId: 'check-one',
            checkPrompt: 'Choose one',
            checkOptions: [
                ['id' => 'option-one', 'label' => 'Option one'],
            ],
            correctOptionId: 'option-one',
            modelAudioAssetId: (string) Str::ulid(),
            modelAudioMimeType: 'audio/wav',
            modelAudioContentHash: hash('sha256', 'audio'),
            modelAudioByteSize: 512,
            modelAudioDurationMs: 1200,
            modelAudioStorageKey: 'media/demo.wav',
        );
    }
}
