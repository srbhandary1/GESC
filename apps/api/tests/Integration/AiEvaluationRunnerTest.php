<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\Evaluation\AiEvaluationRunner;
use App\Conversation\Evaluation\AiEvaluationSuite;
use App\Conversation\Exceptions\ConversationOperationRejected;
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
        $this->assertSame(15, $report['case_count']);
        $this->assertSame(15, $report['passed_count']);
        $this->assertSame(0, $report['estimated_cost_microunits']);
        $this->assertSame(0, $report['metrics']['false_correction_count']);
        $this->assertSame(0, $report['metrics']['missing_l1_count']);
        $this->assertSame(6, $report['metrics']['shame_language_hits']);
        $this->assertTrue($report['metrics']['cost_ceiling_ok']);
        $this->assertNotEmpty($report['prompt_comparison']);
        $this->assertTrue(File::isFile($reportPath));
        $this->assertTrue(File::isFile($exportPath));
        $this->assertSame(1, DB::table('evaluation_runs')->count());
        $this->assertSame(
            'completed',
            DB::table('evaluation_runs')->value('status'),
        );
        $this->assertSame(
            'friend.l1.locale.v1',
            DB::table('evaluation_runs')->value('suite_key'),
        );
    }

    public function test_import_review_accept_and_pending_keep_suite_passing(): void
    {
        $directory = storage_path('framework/testing/ai-evaluation');
        File::ensureDirectoryExists($directory);
        $exportPath = $directory.'/review-export-import.json';
        $importPath = $directory.'/review-import-accept.json';
        $runner = $this->app->make(AiEvaluationRunner::class);
        $suite = $this->app->make(AiEvaluationSuite::class)->load();

        $runner->run(exportReviewPath: $exportPath);
        $export = json_decode(File::get($exportPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($export);
        $this->assertSame($suite['suite_hash'], $export['suite_hash']);

        $dispositions = [];

        foreach ($export['dispositions'] as $index => $row) {
            $this->assertIsArray($row);
            $dispositions[] = [
                'case_id' => $row['case_id'],
                'disposition' => $index === 0 ? 'accept' : 'pending',
            ];
        }

        File::put($importPath, json_encode([
            'suite_key' => $suite['suite_key'],
            'suite_hash' => $suite['suite_hash'],
            'dispositions' => $dispositions,
        ], JSON_THROW_ON_ERROR));

        $report = $runner->run(importReviewPath: $importPath);

        $this->assertSame('completed', $report['status']);
        $this->assertSame(0, $report['failed_count']);
        $this->assertSame('accept', $report['cases'][0]['human_disposition']);
        $this->assertSame('pending', $report['cases'][1]['human_disposition']);
    }

    public function test_import_review_reject_fails_the_matching_case(): void
    {
        $directory = storage_path('framework/testing/ai-evaluation');
        File::ensureDirectoryExists($directory);
        $importPath = $directory.'/review-import-reject.json';
        $runner = $this->app->make(AiEvaluationRunner::class);
        $suite = $this->app->make(AiEvaluationSuite::class)->load();
        $firstCaseId = (string) $suite['cases'][0]['id'];

        File::put($importPath, json_encode([
            'suite_key' => $suite['suite_key'],
            'suite_hash' => $suite['suite_hash'],
            'dispositions' => [
                [
                    'case_id' => $firstCaseId,
                    'disposition' => 'reject',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $report = $runner->run(importReviewPath: $importPath);

        $this->assertSame('failed', $report['status']);
        $this->assertSame(1, $report['failed_count']);
        $this->assertFalse($report['cases'][0]['passed']);
        $this->assertSame('reject', $report['cases'][0]['human_disposition']);
    }

    public function test_import_review_suite_hash_mismatch_fails_closed(): void
    {
        $directory = storage_path('framework/testing/ai-evaluation');
        File::ensureDirectoryExists($directory);
        $importPath = $directory.'/review-import-mismatch.json';
        $runner = $this->app->make(AiEvaluationRunner::class);
        $suite = $this->app->make(AiEvaluationSuite::class)->load();

        File::put($importPath, json_encode([
            'suite_key' => $suite['suite_key'],
            'suite_hash' => str_repeat('0', 64),
            'dispositions' => [
                [
                    'case_id' => (string) $suite['cases'][0]['id'],
                    'disposition' => 'accept',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(ConversationOperationRejected::class);
        $runner->run(importReviewPath: $importPath);
    }

    public function test_import_review_unknown_case_fails_closed(): void
    {
        $directory = storage_path('framework/testing/ai-evaluation');
        File::ensureDirectoryExists($directory);
        $importPath = $directory.'/review-import-unknown.json';
        $runner = $this->app->make(AiEvaluationRunner::class);
        $suite = $this->app->make(AiEvaluationSuite::class)->load();

        File::put($importPath, json_encode([
            'suite_key' => $suite['suite_key'],
            'suite_hash' => $suite['suite_hash'],
            'dispositions' => [
                [
                    'case_id' => 'case.unknown.99',
                    'disposition' => 'accept',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(ConversationOperationRejected::class);
        $runner->run(importReviewPath: $importPath);
    }

    public function test_import_review_missing_file_fails_closed(): void
    {
        $runner = $this->app->make(AiEvaluationRunner::class);

        $this->expectException(ConversationOperationRejected::class);
        $runner->run(
            importReviewPath: storage_path(
                'framework/testing/ai-evaluation/missing-review.json',
            ),
        );
    }
}
