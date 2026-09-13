<?php

declare(strict_types=1);

namespace App\Operator;

use App\Audit\AuditEventRecorder;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class SpeechEconomicsExportService
{
    /**
     * @var list<string>
     */
    private const array COLUMNS = [
        'usage_date',
        'environment',
        'aggregate_kind',
        'provider_stage',
        'provider_key',
        'model_version',
        'route_role',
        'locale',
        'conversation_mode',
        'result_status',
        'dispatch_kind',
        'billable_unit_kind',
        'reconciliation_status',
        'small_cell_suppressed',
        'attempt_count',
        'dispatch_count',
        'success_count',
        'failure_count',
        'cancelled_count',
        'partial_count',
        'fallback_count',
        'billable_unit_quantity',
        'allocated_inr_microunits',
        'first_result_p50_ms',
        'first_result_p95_ms',
        'completion_p95_ms',
        'first_audible_p95_ms',
        'cost_per_successful_turn_inr_microunits',
    ];

    public function __construct(
        private SpeechEconomicsQuery $query,
        private AuditEventRecorder $audit,
    ) {}

    /**
     * @param  array<string, string>  $filters
     * @return array{export_id: string, csv: string}
     */
    public function export(User $actor, array $filters): array
    {
        $summary = $this->query->summarize($actor, $filters);
        $exportId = (string) Str::ulid();
        $this->audit->recordTenant(
            'speech.economics.export',
            'speech_usage_export',
            $exportId,
        );

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return ['export_id' => $exportId, 'csv' => ''];
        }
        fputcsv($handle, self::COLUMNS);
        foreach ($summary['rows'] as $row) {
            $line = [];
            foreach (self::COLUMNS as $column) {
                $value = $row[$column] ?? '';
                $line[] = is_bool($value) ? ($value ? '1' : '0') : $value;
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return [
            'export_id' => $exportId,
            'csv' => is_string($csv) ? $csv : '',
        ];
    }
}
