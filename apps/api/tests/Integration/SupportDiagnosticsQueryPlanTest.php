<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SupportDiagnosticsQueryPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function planCases(): iterable
    {
        yield 'stuck learner attempts' => [
            'stuckAttemptsQuery',
            'learner_attempts_support_diagnostics_index',
        ];
        yield 'recoverable voice failures' => [
            'failedVoiceQuery',
            'voice_recordings_support_diagnostics_index',
        ];
        yield 'open deletion timeline' => [
            'openDeletionsQuery',
            'voice_deletions_support_diagnostics_index',
        ];
    }

    #[DataProvider('planCases')]
    public function test_support_diagnostics_plan_uses_the_ordered_tenant_index(
        string $queryMethod,
        string $expectedIndex,
    ): void {
        DB::statement('SET LOCAL enable_seqscan TO off');

        $query = match ($queryMethod) {
            'stuckAttemptsQuery' => $this->stuckAttemptsQuery(),
            'failedVoiceQuery' => $this->failedVoiceQuery(),
            'openDeletionsQuery' => $this->openDeletionsQuery(),
            default => self::fail('Unknown synthetic query-plan case.'),
        };
        $rows = DB::select(
            'EXPLAIN (FORMAT JSON) '.$query->toSql(),
            $query->getBindings(),
        );
        self::assertCount(1, $rows);

        $encoded = $rows[0]->{'QUERY PLAN'} ?? null;
        self::assertIsString($encoded);
        $decoded = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertIsArray($decoded[0]['Plan'] ?? null);

        $plan = $this->flattenPlan($decoded[0]['Plan']);
        self::assertNotContains('Sort', $plan['node_types']);
        self::assertContains($expectedIndex, $plan['index_names']);
    }

    private function stuckAttemptsQuery(): Builder
    {
        return DB::table('learner_attempts')
            ->where('organization_id', '01K00000000000000000000000')
            ->where('status', 'in_progress')
            ->where('updated_at', '<', '2026-08-09T00:00:00Z')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(50)
            ->select(['id', 'status', 'updated_at']);
    }

    private function failedVoiceQuery(): Builder
    {
        return DB::table('voice_recordings')
            ->where('organization_id', '01K00000000000000000000000')
            ->where('status', 'provider_failed_recoverable')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(50)
            ->select(['id', 'status', 'updated_at']);
    }

    private function openDeletionsQuery(): Builder
    {
        return DB::table('voice_deletion_requests')
            ->where('organization_id', '01K00000000000000000000000')
            ->whereIn(
                'status',
                ['pending', 'partially_completed', 'restricted'],
            )
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->limit(50)
            ->select(['id', 'status', 'requested_at']);
    }

    /**
     * @param  array<string, mixed>  $root
     * @return array{node_types: list<string>, index_names: list<string>}
     */
    private function flattenPlan(array $root): array
    {
        $nodeTypes = [];
        $indexNames = [];
        $pending = [$root];

        while ($pending !== []) {
            $node = array_pop($pending);

            $nodeType = $node['Node Type'] ?? null;
            $indexName = $node['Index Name'] ?? null;

            if (is_string($nodeType)) {
                $nodeTypes[] = $nodeType;
            }

            if (is_string($indexName)) {
                $indexNames[] = $indexName;
            }

            $children = $node['Plans'] ?? [];

            if (! is_array($children)) {
                continue;
            }

            foreach ($children as $child) {
                if (is_array($child)) {
                    $pending[] = $child;
                }
            }
        }

        return [
            'node_types' => $nodeTypes,
            'index_names' => $indexNames,
        ];
    }
}
