<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Aggregate-only pilot analytics for the active organization.
 * Counts and safe rollups only — never learner PII, audio, or transcripts.
 */
final readonly class OperatorPilotAnalyticsQuery
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OperatorContentAuthorization $authorization,
    ) {}

    /**
     * @return array{
     *     generated_at: string,
     *     usage: array{
     *         learner_membership_count: int,
     *         conversation_session_count: int,
     *         conversation_sessions_by_status: array<string, int>,
     *         formative_attempt_count: int,
     *         formative_attempts_by_status: array<string, int>
     *     },
     *     demonstrated_learning: array{
     *         mastery_state_count: int,
     *         mastery_by_knowledge_state: array<string, int>,
     *         review_schedule_count: int
     *     },
     *     distinction_note: string
     * }
     */
    public function summarize(User $actor): array
    {
        $this->authorization->assertCanReadPilotAnalytics($actor);
        $organizationId = $this->contexts->current()->organizationId;

        return [
            'generated_at' => now()->toIso8601String(),
            'usage' => [
                'learner_membership_count' => $this->countLearnerMemberships(
                    $organizationId,
                ),
                'conversation_session_count' => $this->countTable(
                    'conversation_sessions',
                    $organizationId,
                ),
                'conversation_sessions_by_status' => $this->groupCount(
                    'conversation_sessions',
                    'status',
                    $organizationId,
                ),
                'formative_attempt_count' => $this->countTable(
                    'learner_attempts',
                    $organizationId,
                ),
                'formative_attempts_by_status' => $this->groupCount(
                    'learner_attempts',
                    'status',
                    $organizationId,
                ),
            ],
            'demonstrated_learning' => [
                'mastery_state_count' => $this->countTable(
                    'mastery_states',
                    $organizationId,
                ),
                'mastery_by_knowledge_state' => $this->groupCount(
                    'mastery_states',
                    'knowledge_state',
                    $organizationId,
                ),
                'review_schedule_count' => $this->countTable(
                    'review_schedules',
                    $organizationId,
                ),
            ],
            'distinction_note' => 'usage_counts_are_not_demonstrated_learning',
        ];
    }

    private function countLearnerMemberships(string $organizationId): int
    {
        return (int) DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('membership_type', 'learner')
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->count();
    }

    private function countTable(string $table, string $organizationId): int
    {
        return (int) DB::table($table)
            ->where('organization_id', $organizationId)
            ->count();
    }

    /**
     * @return array<string, int>
     */
    private function groupCount(
        string $table,
        string $column,
        string $organizationId,
    ): array {
        $rows = DB::table($table)
            ->select($column, DB::raw('count(*) as aggregate_count'))
            ->where('organization_id', $organizationId)
            ->groupBy($column)
            ->orderBy($column)
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            if (! $row instanceof stdClass) {
                continue;
            }

            $key = $row->{$column} ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $grouped[$key] = (int) ($row->aggregate_count ?? 0);
        }

        return $grouped;
    }
}
