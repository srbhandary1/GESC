<?php

declare(strict_types=1);

namespace App\Learning\Review;

use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * LPL-04 / LPL-06: shared delayed-review inventory for blocking, Home and Progress.
 *
 * Reviews that become due after an unlock is issued must not revoke it; this
 * service evaluates only the current submission moment.
 */
class ReviewDueBlockingService
{
    public function enabled(): bool
    {
        return config('level_sessions.review_due_blocking.enabled', true) === true;
    }

    public function ruleKey(): string
    {
        return (string) config(
            'level_sessions.review_due_blocking.rule_key',
            'review_due_blocking_v1',
        );
    }

    public function hasDueIncompleteReview(
        OrganizationContext $context,
        ?CarbonImmutable $asOf = null,
    ): bool {
        return $this->firstDueIncompleteReview($context, $asOf) !== null;
    }

    /**
     * @return array{
     *   id: string,
     *   enrollment_id: string,
     *   due_at: string,
     *   learning_objective_id: string,
     *   curriculum_mission_id: string,
     *   interval_days: int,
     *   timezone: string
     * }|null
     */
    public function firstDueIncompleteReview(
        OrganizationContext $context,
        ?CarbonImmutable $asOf = null,
    ): ?array {
        if (! $this->enabled()) {
            return null;
        }

        $asOf ??= Date::now();
        $row = $this->incompleteReviewQuery($context)
            ->where('due_at', '<=', $asOf)
            ->orderBy('due_at')
            ->orderBy('sequence')
            ->first([
                'id',
                'enrollment_id',
                'due_at',
                'learning_objective_id',
                'curriculum_mission_id',
                'interval_days',
                'timezone',
            ]);

        return $row instanceof stdClass ? $this->mapOccurrence($row) : null;
    }

    /**
     * @return list<array{
     *   id: string,
     *   enrollment_id: string,
     *   due_at: string,
     *   learning_objective_id: string,
     *   curriculum_mission_id: string,
     *   interval_days: int,
     *   timezone: string
     * }>
     */
    public function upcomingIncompleteReviews(
        OrganizationContext $context,
        int $limit = 3,
        ?CarbonImmutable $asOf = null,
    ): array {
        if (! $this->enabled() || $limit < 1) {
            return [];
        }

        $asOf ??= Date::now();

        $reviews = $this->incompleteReviewQuery($context)
            ->where('due_at', '>', $asOf)
            ->orderBy('due_at')
            ->orderBy('sequence')
            ->limit($limit)
            ->get([
                'id',
                'enrollment_id',
                'due_at',
                'learning_objective_id',
                'curriculum_mission_id',
                'interval_days',
                'timezone',
            ])
            ->map(fn (stdClass $row): array => $this->mapOccurrence($row))
            ->all();

        return array_values($reviews);
    }

    /**
     * @return Builder
     */
    private function incompleteReviewQuery(OrganizationContext $context)
    {
        return DB::table('delayed_review_occurrences')
            ->where('organization_id', $context->organizationId)
            ->where('learner_membership_id', $context->membershipId)
            ->whereNull('completed_at');
    }

    /**
     * @return array{
     *   id: string,
     *   enrollment_id: string,
     *   due_at: string,
     *   learning_objective_id: string,
     *   curriculum_mission_id: string,
     *   interval_days: int,
     *   timezone: string
     * }
     */
    private function mapOccurrence(stdClass $row): array
    {
        return [
            'id' => (string) $row->id,
            'enrollment_id' => (string) $row->enrollment_id,
            'due_at' => CarbonImmutable::parse((string) $row->due_at)
                ->utc()
                ->toIso8601String(),
            'learning_objective_id' => (string) $row->learning_objective_id,
            'curriculum_mission_id' => (string) $row->curriculum_mission_id,
            'interval_days' => (int) $row->interval_days,
            'timezone' => (string) $row->timezone,
        ];
    }
}
