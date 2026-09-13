<?php

declare(strict_types=1);

namespace App\Operator;

use Illuminate\Support\Facades\DB;

/**
 * LPL-06c: content-free learning metrics for operator dashboards.
 */
final readonly class OperatorLearningMetricsQuery
{
    /**
     * @return array{
     *   stage_completion_count: int,
     *   speaking_turn_count: int,
     *   quiz_retry_count: int,
     *   review_return_due_count: int,
     *   review_return_completed_count: int,
     *   ai_fallback_count: int,
     *   latency_sample_count: int,
     *   session_cost_microunits_total: int
     * }
     */
    public function summarize(string $organizationId): array
    {
        $stageCompletionCount = (int) DB::table('level_session_completions')
            ->where('organization_id', $organizationId)
            ->count();

        $speakingTurnCount = (int) DB::table('conversation_turns')
            ->where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->count();

        $quizRetryCount = (int) DB::table('level_session_check_submissions')
            ->where('organization_id', $organizationId)
            ->where('passed', false)
            ->count()
            + (int) DB::table('level_checkpoint_attempts')
                ->where('organization_id', $organizationId)
                ->where('passed', false)
                ->count();

        $reviewDueCount = (int) DB::table('delayed_review_occurrences')
            ->where('organization_id', $organizationId)
            ->whereNull('completed_at')
            ->where('due_at', '<=', now())
            ->count();

        $reviewCompletedCount = (int) DB::table('delayed_review_occurrences')
            ->where('organization_id', $organizationId)
            ->whereNotNull('completed_at')
            ->count();

        $aiFallbackCount = (int) DB::table('speech_usage_daily_aggregates')
            ->where('organization_id', $organizationId)
            ->sum('fallback_count');

        $latencySampleCount = 0;
        if (DB::getSchemaBuilder()->hasTable('speech_client_latency_samples')) {
            $latencySampleCount = (int) DB::table('speech_client_latency_samples')
                ->where('organization_id', $organizationId)
                ->count();
        }

        $sessionCostTotal = (int) DB::table('ai_cost_ledger')
            ->where('organization_id', $organizationId)
            ->sum('estimated_cost_microunits');

        return [
            'stage_completion_count' => $stageCompletionCount,
            'speaking_turn_count' => $speakingTurnCount,
            'quiz_retry_count' => $quizRetryCount,
            'review_return_due_count' => $reviewDueCount,
            'review_return_completed_count' => $reviewCompletedCount,
            'ai_fallback_count' => $aiFallbackCount,
            'latency_sample_count' => $latencySampleCount,
            'session_cost_microunits_total' => $sessionCostTotal,
        ];
    }
}
