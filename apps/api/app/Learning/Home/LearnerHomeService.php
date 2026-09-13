<?php

declare(strict_types=1);

namespace App\Learning\Home;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Learning\Missions\DailyMissionReader;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class LearnerHomeService
{
    public function __construct(
        private LearnerAuthority $authority,
        private DailyMissionReader $missions,
        private MeaningfulSpeakingStreakReader $streaks,
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
        $dueReview = null;

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

            if ($mission !== null) {
                $dueReview = DB::table('delayed_review_occurrences')
                    ->where('organization_id', $context->organizationId)
                    ->where('learner_membership_id', $membershipId)
                    ->where('enrollment_id', $this->string($enrollment, 'id'))
                    ->where('curriculum_mission_id', $mission->missionId)
                    ->whereNull('completed_at')
                    ->where('due_at', '<=', Date::now())
                    ->orderBy('due_at')
                    ->orderBy('sequence')
                    ->first([
                        'id',
                        'learning_objective_id',
                        'curriculum_mission_id',
                        'interval_days',
                        'due_at',
                        'timezone',
                    ]);
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
            'due_review' => $dueReview instanceof stdClass
                && $enrollment instanceof stdClass
                && $mission !== null
                ? [
                    'id' => $this->string($dueReview, 'id'),
                    'enrollment_id' => $this->string($enrollment, 'id'),
                    'mission_id' => $this->string($dueReview, 'curriculum_mission_id'),
                    'objective_id' => $this->string($dueReview, 'learning_objective_id'),
                    'interval_days' => (int) $dueReview->interval_days,
                    'due_at' => CarbonImmutable::parse(
                        $this->string($dueReview, 'due_at'),
                    )->utc()->toIso8601String(),
                    'timezone' => $this->string($dueReview, 'timezone'),
                    'title' => [
                        'locale' => 'en-IN',
                        'text' => $mission->title,
                        'revision_id' => $mission->sourceRevisionId,
                        'content_hash' => $mission->sourceContentHash,
                    ],
                ]
                : null,
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
