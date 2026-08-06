<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\Evaluation\AiEvaluationRunner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class AiEvaluationRunnerTest extends TenancyIntegrationTestCase
{
    public function test_locked_suite_passes_and_writes_comparison_report(): void
    {
        $directory = storage_path('framework/testing/ai-evaluation');
        File::ensureDirectoryExists($directory);
        $reportPath = $directory.'/report.json';
        $exportPath = $directory.'/review-export.json';
        $runner = $this->app->make(AiEvaluationRunner::class);

        $report = $runner->run(
            exportReviewPath: $exportPath,
            reportPath: $reportPath,
        );

        $this->assertSame('completed', $report['status']);
        $this->assertSame(0, $report['failed_count']);
        $this->assertSame(0, $report['estimated_cost_microunits']);
        $this->assertNotEmpty($report['prompt_comparison']);
        $this->assertTrue(File::isFile($reportPath));
        $this->assertTrue(File::isFile($exportPath));
        $this->assertSame(1, DB::table('evaluation_runs')->count());
        $this->assertSame(
            'completed',
            DB::table('evaluation_runs')->value('status'),
        );
    }
}
