<?php

declare(strict_types=1);

namespace App\Learning\Progress;

use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Home\MeaningfulSpeakingDayProjector;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class LearnerProgressDashboardService
{
    public function __construct(
        private LearnerAuthority $authority,
        private MeaningfulSpeakingDayProjector $speakingDayProjector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forLearner(User $learner): array
    {
        $context = $this->authority->require($learner);
        $organizationId = $context->organizationId;
        $membershipId = (string) $context->membershipId;
        $enrollment = $this->activeEnrollment($organizationId, $membershipId);

        return [
            'enrollment' => $enrollment instanceof stdClass
                ? [
                    'id' => $this->string($enrollment, 'id'),
                    'status' => 'active',
                ]
                : null,
            'objectives' => $enrollment instanceof stdClass
                ? $this->objectives(
                    $organizationId,
                    $this->string($enrollment, 'id'),
                    $membershipId,
                )
                : [],
            'speaking_activity' => $this->speakingActivity(
                $organizationId,
                $membershipId,
            ),
            'attempt_improvement' => $enrollment instanceof stdClass
                ? $this->attemptImprovement(
                    $organizationId,
                    $this->string($enrollment, 'id'),
                    $membershipId,
                )
                : $this->emptyAttemptImprovement(),
            'delayed_review' => $enrollment instanceof stdClass
                ? $this->delayedReview(
                    $organizationId,
                    $this->string($enrollment, 'id'),
                    $membershipId,
                )
                : $this->emptyDelayedReview(),
            'recurring_errors' => [
                'patterns' => $this->recurringErrorPatterns(
                    $organizationId,
                    $membershipId,
                ),
            ],
        ];
    }

    private function activeEnrollment(
        string $organizationId,
        string $membershipId,
    ): ?stdClass {
        $enrollment = DB::table('enrollments')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'active')
            ->whereNotNull('curriculum_publication_id')
            ->orderBy('enrolled_at')
            ->orderBy('id')
            ->first(['id', 'status']);

        return $enrollment instanceof stdClass ? $enrollment : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function objectives(
        string $organizationId,
        string $enrollmentId,
        string $membershipId,
    ): array {
        $rows = DB::table('mastery_states')
            ->join('learning_objectives', function ($join): void {
                $join
                    ->on(
                        'learning_objectives.organization_id',
                        '=',
                        'mastery_states.curriculum_owner_organization_id',
                    )
                    ->on(
                        'learning_objectives.curriculum_version_id',
                        '=',
                        'mastery_states.curriculum_version_id',
                    )
                    ->on(
                        'learning_objectives.id',
                        '=',
                        'mastery_states.learning_objective_id',
                    );
            })
            ->where('mastery_states.organization_id', $organizationId)
            ->where('mastery_states.enrollment_id', $enrollmentId)
            ->where('mastery_states.learner_membership_id', $membershipId)
            ->orderBy('learning_objectives.sequence')
            ->orderBy('mastery_states.learning_objective_id')
            ->get([
                'mastery_states.learning_objective_id',
                'learning_objectives.key as objective_key',
                'mastery_states.knowledge_state',
                'mastery_states.spoken_state',
                'mastery_states.best_knowledge_score',
                'mastery_states.mastered_at',
                'mastery_states.evidence_attempt_id',
            ]);

        /** @var list<array<string, mixed>> $objectives */
        $objectives = $rows
            ->map(function (stdClass $row): array {
                return [
                    'objective_id' => $this->string($row, 'learning_objective_id'),
                    'objective_key' => $this->string($row, 'objective_key'),
                    'knowledge_state' => $this->string($row, 'knowledge_state'),
                    'spoken_state' => $this->string($row, 'spoken_state'),
                    'best_knowledge_score' => (int) $row->best_knowledge_score,
                    'mastered_at' => $row->mastered_at === null
                        ? null
                        : CarbonImmutable::parse(
                            $this->string($row, 'mastered_at'),
                        )->utc()->toIso8601String(),
                    'evidence_attempt_id' => $this->string(
                        $row,
                        'evidence_attempt_id',
                    ),
                ];
            })
            ->values()
            ->all();

        return $objectives;
    }

    /**
     * @return array<string, mixed>
     */
    private function speakingActivity(
        string $organizationId,
        string $membershipId,
    ): array {
        $this->speakingDayProjector->reconcileLearner(
            $organizationId,
            $membershipId,
        );

        $timezone = DB::table('organizations')
            ->where('id', $organizationId)
            ->value('timezone');
        $timezone = is_string($timezone) && $timezone !== ''
            ? $timezone
            : 'Asia/Kolkata';

        $meaningfulDays = (int) DB::table('meaningful_speaking_days')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->count();

        $turns = DB::table('conversation_turns')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'completed')
            ->whereNotNull('assistant_reply_digest')
            ->whereNull('failed_stage')
            ->get(['accepted_at', 'completed_at']);

        $completedTurnCount = $turns->count();
        $speakingDurationMs = 0;

        foreach ($turns as $turn) {
            if (
                ! is_string($turn->accepted_at)
                || ! is_string($turn->completed_at)
            ) {
                continue;
            }

            $acceptedAt = CarbonImmutable::parse($turn->accepted_at);
            $completedAt = CarbonImmutable::parse($turn->completed_at);
            $speakingDurationMs += max(
                0,
                $completedAt->getTimestampMs() - $acceptedAt->getTimestampMs(),
            );
        }

        return [
            'meaningful_speaking_days_count' => $meaningfulDays,
            'completed_turn_count' => $completedTurnCount,
            'speaking_duration_ms' => $speakingDurationMs,
            'speaking_minutes' => intdiv($speakingDurationMs, 60_000),
            'timezone' => $timezone,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function attemptImprovement(
        string $organizationId,
        string $enrollmentId,
        string $membershipId,
    ): array {
        $rows = DB::table('learner_attempts')
            ->join('attempt_submissions', function ($join): void {
                $join
                    ->on(
                        'attempt_submissions.organization_id',
                        '=',
                        'learner_attempts.organization_id',
                    )
                    ->on(
                        'attempt_submissions.learner_attempt_id',
                        '=',
                        'learner_attempts.id',
                    );
            })
            ->where('learner_attempts.organization_id', $organizationId)
            ->where('learner_attempts.enrollment_id', $enrollmentId)
            ->where('learner_attempts.learner_membership_id', $membershipId)
            ->orderBy('learner_attempts.curriculum_mission_id')
            ->orderBy('learner_attempts.attempt_number')
            ->get([
                'learner_attempts.curriculum_mission_id',
                'learner_attempts.attempt_number',
                'attempt_submissions.knowledge_check_correct',
                'attempt_submissions.knowledge_state',
                'attempt_submissions.knowledge_score',
            ]);

        if ($rows->isEmpty()) {
            return $this->emptyAttemptImprovement();
        }

        /** @var array<string, list<stdClass>> $byMission */
        $byMission = [];

        foreach ($rows as $row) {
            $missionId = $this->string($row, 'curriculum_mission_id');
            $byMission[$missionId][] = $row;
        }

        $missionsAttempted = count($byMission);
        $passedOnFirstAttempt = 0;
        $improvedOnRetry = 0;
        $stillNotMastered = 0;

        foreach ($byMission as $missionAttempts) {
            $first = $missionAttempts[0];
            $best = $missionAttempts[0];

            foreach ($missionAttempts as $attempt) {
                if ((int) $attempt->knowledge_score > (int) $best->knowledge_score) {
                    $best = $attempt;
                }
            }

            if ($first->knowledge_check_correct) {
                $passedOnFirstAttempt++;

                continue;
            }

            if (
                $best->knowledge_state === 'mastered'
                && (int) $best->knowledge_score > (int) $first->knowledge_score
            ) {
                $improvedOnRetry++;

                continue;
            }

            if ($best->knowledge_state !== 'mastered') {
                $stillNotMastered++;
            }
        }

        return [
            'missions_attempted' => $missionsAttempted,
            'missions_passed_on_first_attempt' => $passedOnFirstAttempt,
            'missions_improved_on_retry' => $improvedOnRetry,
            'missions_still_not_mastered' => $stillNotMastered,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyAttemptImprovement(): array
    {
        return [
            'missions_attempted' => 0,
            'missions_passed_on_first_attempt' => 0,
            'missions_improved_on_retry' => 0,
            'missions_still_not_mastered' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function delayedReview(
        string $organizationId,
        string $enrollmentId,
        string $membershipId,
    ): array {
        $rows = DB::table('delayed_review_occurrences')
            ->where('organization_id', $organizationId)
            ->where('enrollment_id', $enrollmentId)
            ->where('learner_membership_id', $membershipId)
            ->get(['due_at', 'completed_at']);

        $now = Date::now();
        $scheduledCount = $rows->count();
        $dueCount = 0;
        $pendingCount = 0;
        $completedCount = 0;
        $completedOnTimeCount = 0;
        $completedLateCount = 0;

        foreach ($rows as $row) {
            $dueAt = CarbonImmutable::parse($this->string($row, 'due_at'));

            if ($row->completed_at === null) {
                if ($dueAt->lte($now)) {
                    $dueCount++;
                } else {
                    $pendingCount++;
                }

                continue;
            }

            $completedCount++;
            $completedAt = CarbonImmutable::parse(
                $this->string($row, 'completed_at'),
            );

            if ($completedAt->lte($dueAt)) {
                $completedOnTimeCount++;
            } else {
                $completedLateCount++;
            }
        }

        return [
            'scheduled_count' => $scheduledCount,
            'due_count' => $dueCount,
            'pending_count' => $pendingCount,
            'completed_count' => $completedCount,
            'completed_on_time_count' => $completedOnTimeCount,
            'completed_late_count' => $completedLateCount,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyDelayedReview(): array
    {
        return [
            'scheduled_count' => 0,
            'due_count' => 0,
            'pending_count' => 0,
            'completed_count' => 0,
            'completed_on_time_count' => 0,
            'completed_late_count' => 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recurringErrorPatterns(
        string $organizationId,
        string $membershipId,
    ): array {
        $now = CarbonImmutable::now('UTC');
        $rows = DB::table('learner_error_patterns')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->orderByDesc('last_observed_at')
            ->orderBy('error_category')
            ->get([
                'error_category',
                'observation_count',
                'first_observed_at',
                'last_observed_at',
            ]);

        /** @var list<array<string, mixed>> $patterns */
        $patterns = $rows
            ->map(function (stdClass $row) use ($now): array {
                $firstObserved = CarbonImmutable::parse(
                    $this->string($row, 'first_observed_at'),
                );
                $lastObserved = CarbonImmutable::parse(
                    $this->string($row, 'last_observed_at'),
                );
                $observationCount = (int) $row->observation_count;

                return [
                    'error_category' => $this->string($row, 'error_category'),
                    'observation_count' => $observationCount,
                    'first_observed_at' => $firstObserved->utc()->toIso8601String(),
                    'last_observed_at' => $lastObserved->utc()->toIso8601String(),
                    'trend' => $this->errorTrend(
                        $observationCount,
                        $firstObserved,
                        $lastObserved,
                        $now,
                    ),
                ];
            })
            ->values()
            ->all();

        return $patterns;
    }

    private function errorTrend(
        int $observationCount,
        CarbonImmutable $firstObserved,
        CarbonImmutable $lastObserved,
        CarbonImmutable $now,
    ): string {
        if ($observationCount <= 1) {
            return 'stable';
        }

        if ($lastObserved->diffInDays($now) >= 14) {
            return 'reducing';
        }

        if ($firstObserved->diffInDays($now) <= 7) {
            return 'increasing';
        }

        return 'stable';
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
