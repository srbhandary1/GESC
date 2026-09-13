<?php

declare(strict_types=1);

namespace App\Learning\Home;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Learning\Missions\DailyMissionReader;
use App\Learning\Review\ReviewDueBlockingService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class LearnerHomeService
{
    public function __construct(
        private LearnerAuthority $authority,
        private DailyMissionReader $missions,
        private MeaningfulSpeakingStreakReader $streaks,
        private ReviewDueBlockingService $reviewDue,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forLearner(User $learner): array
    {
        $context = $this->authority->require($learner);
        $membershipId = (string) $context->membershipId;
        $onboarding = DB::table('learner_profiles')
            ->leftJoin('learner_profile_current_goals', function ($join): void {
                $join
                    ->on(
                        'learner_profile_current_goals.organization_id',
                        '=',
                        'learner_profiles.organization_id',
                    )
                    ->on(
                        'learner_profile_current_goals.learner_profile_id',
                        '=',
                        'learner_profiles.id',
                    )
                    ->on(
                        'learner_profile_current_goals.learner_membership_id',
                        '=',
                        'learner_profiles.learner_membership_id',
                    );
            })
            ->where(
                'learner_profiles.organization_id',
                $context->organizationId,
            )
            ->where(
                'learner_profiles.learner_membership_id',
                $membershipId,
            )
            ->first([
                'learner_profiles.adult_confirmed_at',
                'learner_profiles.onboarding_completed_at',
                'learner_profile_current_goals.goal_key',
            ]);
        $enrollment = DB::table('enrollments')
            ->where('organization_id', $context->organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'active')
            ->whereNotNull('curriculum_publication_id')
            ->orderBy('enrolled_at')
            ->orderBy('id')
            ->first(['id', 'status']);
        $mission = null;
        $dueReviewPayload = null;
        $upcomingReviews = [];

        if ($enrollment instanceof stdClass) {
            try {
                $mission = $this->missions->first(
                    $learner,
                    $this->string($enrollment, 'id'),
                );
            } catch (
                CurriculumOperationRejected
                |LearningOperationRejected
            ) {
                // An enrollment may remain active while its pinned delivery is
                // suspended or its current rights predicate fails.
                $mission = null;
            }
        }

        $dueSummary = $this->reviewDue->firstDueIncompleteReview($context);
        if ($dueSummary !== null) {
            try {
                $reviewMission = $this->missions->resolve(
                    $learner,
                    $dueSummary['enrollment_id'],
                    $dueSummary['curriculum_mission_id'],
                );
                $dueReviewPayload = [
                    'id' => $dueSummary['id'],
                    'enrollment_id' => $dueSummary['enrollment_id'],
                    'mission_id' => $dueSummary['curriculum_mission_id'],
                    'objective_id' => $dueSummary['learning_objective_id'],
                    'interval_days' => $dueSummary['interval_days'],
                    'due_at' => $dueSummary['due_at'],
                    'timezone' => $dueSummary['timezone'],
                    'title' => [
                        'locale' => 'en-IN',
                        'text' => $reviewMission->title,
                        'revision_id' => $reviewMission->sourceRevisionId,
                        'content_hash' => $reviewMission->sourceContentHash,
                    ],
                ];
            } catch (
                CurriculumOperationRejected
                |LearningOperationRejected
            ) {
                $dueReviewPayload = null;
            }
        }

        foreach ($this->reviewDue->upcomingIncompleteReviews($context) as $upcoming) {
            try {
                $upcomingMission = $this->missions->resolve(
                    $learner,
                    $upcoming['enrollment_id'],
                    $upcoming['curriculum_mission_id'],
                );
                $upcomingReviews[] = [
                    'id' => $upcoming['id'],
                    'enrollment_id' => $upcoming['enrollment_id'],
                    'mission_id' => $upcoming['curriculum_mission_id'],
                    'objective_id' => $upcoming['learning_objective_id'],
                    'interval_days' => $upcoming['interval_days'],
                    'due_at' => $upcoming['due_at'],
                    'timezone' => $upcoming['timezone'],
                    'title' => [
                        'locale' => 'en-IN',
                        'text' => $upcomingMission->title,
                        'revision_id' => $upcomingMission->sourceRevisionId,
                        'content_hash' => $upcomingMission->sourceContentHash,
                    ],
                ];
            } catch (
                CurriculumOperationRejected
                |LearningOperationRejected
            ) {
                continue;
            }
        }

        $streak = $this->streaks->read(
            $context->organizationId,
            $membershipId,
        );

        return [
            'onboarding' => [
                'completed' => $onboarding instanceof stdClass
                    && $onboarding->onboarding_completed_at !== null,
                'adult_confirmed' => $onboarding instanceof stdClass
                    && $onboarding->adult_confirmed_at !== null,
                'goal_key' => $onboarding instanceof stdClass
                    && is_string($onboarding->goal_key)
                        ? $onboarding->goal_key
                        : null,
            ],
            'enrollment' => $enrollment instanceof stdClass
                ? [
                    'id' => $this->string($enrollment, 'id'),
                    'status' => 'active',
                ]
                : null,
            'streak' => $streak,
            'due_review' => $dueReviewPayload,
            'upcoming_reviews' => $upcomingReviews,
            'next_mission' => $mission === null
                ? null
                : [
                    'id' => $mission->missionId,
                    'estimated_minutes' => $mission->estimatedMinutes,
                    'title' => [
                        'locale' => 'en-IN',
                        'text' => $mission->title,
                        'revision_id' => $mission->sourceRevisionId,
                        'content_hash' => $mission->sourceContentHash,
                    ],
                ],
        ];
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new LearningOperationRejected;
        }

        return $value;
    }
}
