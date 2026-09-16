<?php

declare(strict_types=1);

namespace App\Learning\Review;

use App\Learning\Exceptions\LearningOperationRejected;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DelayedReviewScheduler
{
    public function reconcile(
        stdClass $attempt,
        CarbonImmutable $evidenceAt,
    ): void {
        $organizationId = $this->string($attempt, 'organization_id');
        $attemptId = $this->string($attempt, 'id');
        $mastery = DB::table('mastery_states')
            ->where('organization_id', $organizationId)
            ->where('enrollment_id', $this->string($attempt, 'enrollment_id'))
            ->where('learning_objective_id', $this->string($attempt, 'learning_objective_id'))
            ->where('knowledge_state', 'mastered')
            ->where('evidence_attempt_id', $attemptId)
            ->where('mastered_at', $evidenceAt)
            ->first(['id']);

        if (! $mastery instanceof stdClass) {
            return;
        }

        $timezone = DB::table('organizations')
            ->where('id', $organizationId)
            ->lockForUpdate()
            ->value('timezone');
        $timezone = is_string($timezone) && $timezone !== ''
            ? $timezone
            : 'Asia/Kolkata';
        $intervals = config('review.interval_days');
        $hour = config('review.local_due_hour');
        $version = config('review.version');

        if (! is_array($intervals) || ! is_int($hour) || ! is_string($version)) {
            throw new LearningOperationRejected;
        }

        foreach (array_values($intervals) as $index => $days) {
            if (! is_int($days) || $days < 1) {
                throw new LearningOperationRejected;
            }

            $dueAt = $evidenceAt->setTimezone($timezone)
                ->addDays($days)
                ->setTime($hour, 0)
                ->utc();
            DB::table('delayed_review_occurrences')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'enrollment_id' => $this->string($attempt, 'enrollment_id'),
                'learner_membership_id' => $this->string($attempt, 'learner_membership_id'),
                'learning_objective_id' => $this->string($attempt, 'learning_objective_id'),
                'source_attempt_id' => $attemptId,
                'curriculum_mission_id' => $this->string($attempt, 'curriculum_mission_id'),
                'sequence' => $index + 1,
                'interval_days' => $days,
                'rule_version' => $version,
                'timezone' => $timezone,
                'due_at' => $dueAt,
                'completed_at' => null,
                'completion_attempt_id' => null,
                'created_at' => $evidenceAt,
                'updated_at' => $evidenceAt,
            ]);
        }
    }

    public function recordEligibleAttempt(
        stdClass $attempt,
        CarbonImmutable $attemptedAt,
    ): void {
        $organizationId = $this->string($attempt, 'organization_id');
        $occurrence = DB::table('delayed_review_occurrences')
            ->where('organization_id', $organizationId)
            ->where('enrollment_id', $this->string($attempt, 'enrollment_id'))
            ->where('learner_membership_id', $this->string($attempt, 'learner_membership_id'))
            ->where('learning_objective_id', $this->string($attempt, 'learning_objective_id'))
            ->whereNull('completed_at')
            ->where('due_at', '<=', $attemptedAt)
            ->orderBy('due_at')
            ->lockForUpdate()
            ->first(['id']);

        if ($occurrence instanceof stdClass) {
            DB::table('delayed_review_occurrences')
                ->where('organization_id', $organizationId)
                ->where('id', $this->string($occurrence, 'id'))
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => $attemptedAt,
                    'completion_attempt_id' => $this->string($attempt, 'id'),
                    'updated_at' => $attemptedAt,
                ]);
        }
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
