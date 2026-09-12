<?php

declare(strict_types=1);

use App\Conversation\Evaluation\AiEvaluationRunner;
use App\Curriculum\LessonPractice\App0063LevelsCommissioner;
use App\Curriculum\LessonPractice\App0065AuthoredLevelsReplacer;
use App\Curriculum\LessonPractice\App0066EnglishReviewReplacer;
use App\Curriculum\LessonPractice\App0067KannadaReviewReplacer;
use App\Conversation\Voice\Benchmark\SpeechBenchmarkReviewServer;
use App\Conversation\Voice\Benchmark\SpeechBenchmarkRunner;
use App\Conversation\Voice\Benchmark\SpeechBenchmarkRunStore;
use App\Conversation\Voice\Providers\Azure\AzureSpeechConfiguration;
use App\Conversation\Voice\VoiceRetentionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('voice:purge-expired', function (): int {
    $result = app(
        VoiceRetentionService::class,
    )->purgeDue();
    $this->info(json_encode($result, JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Delete due transient voice audio and transcripts');

Artisan::command(
    'speech:benchmark {--phase=preflight} {--run=} {--listener-slot=} {--expired}',
    function (): int {
        $configuration = app(AzureSpeechConfiguration::class);
        $phaseOption = $this->option('phase');
        $runOption = $this->option('run');
        $phase = is_string($phaseOption) ? $phaseOption : '';
        $runId = is_string($runOption) ? trim($runOption) : '';

        if ($phase === 'purge') {
            try {
                $store = app(SpeechBenchmarkRunStore::class);
                $result = (bool) $this->option('expired')
                    ? $store->purgeExpired()
                    : $store->purge($runId, 'cancelled');
                $purgeFailed = isset($result['failed'])
                    && $result['failed'] > 0;
                $this->info(json_encode([
                    'status' => $purgeFailed
                        ? 'purge_incomplete'
                        : 'purged',
                    'result' => $result,
                ], JSON_THROW_ON_ERROR));

                return $purgeFailed ? 1 : 0;
            } catch (Throwable) {
                $this->error(json_encode([
                    'status' => 'failed',
                    'safe_error_codes' => ['speech_benchmark_purge_failed'],
                ], JSON_THROW_ON_ERROR));

                return 1;
            }
        }

        if ($phase === 'review') {
            $slot = filter_var(
                $this->option('listener-slot'),
                FILTER_VALIDATE_INT,
            );
            if (
                ! app()->environment('speech-benchmark')
                || ! $configuration->boolean('speech.benchmark.enabled')
                || preg_match('/\A[a-f0-9]{32}\z/', $runId) !== 1
                || ! in_array($slot, [1, 2], true)
            ) {
                $this->error(json_encode([
                    'status' => 'blocked',
                    'safe_error_codes' => ['listener_review_not_authorized'],
                ], JSON_THROW_ON_ERROR));

                return 2;
            }
            try {
                $aggregate = app(SpeechBenchmarkReviewServer::class)->serve(
                    $runId,
                    $slot,
                    fn (string $url) => $this->line(json_encode([
                        'status' => 'review_ready',
                        'loopback_url' => $url,
                    ], JSON_THROW_ON_ERROR)),
                );
                $this->info(json_encode([
                    'status' => 'review_recorded',
                    'run_id' => $runId,
                    'aggregate' => $aggregate,
                ], JSON_THROW_ON_ERROR));

                return 0;
            } catch (Throwable) {
                $this->error(json_encode([
                    'status' => 'failed',
                    'safe_error_codes' => ['listener_review_failed'],
                ], JSON_THROW_ON_ERROR));

                return 1;
            }
        }

        if (! in_array($phase, ['preflight', 'prepare', 'complete'], true)) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_codes' => ['benchmark_phase_invalid'],
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        if ($phase !== 'complete') {
            $preflightRequiresListener = $phase === 'preflight'
                && preg_match('/\A[a-f0-9]{32}\z/', $runId) === 1;
            $errors = $configuration->preflightErrors(
                $preflightRequiresListener,
            );
            if ($errors !== []) {
                $this->error(json_encode([
                    'status' => 'blocked',
                    'safe_error_codes' => $errors,
                ], JSON_THROW_ON_ERROR));

                return 2;
            }
        }
        if ($phase === 'preflight') {
            if ($runId !== '') {
                if (preg_match('/\A[a-f0-9]{32}\z/', $runId) !== 1) {
                    $this->error(json_encode([
                        'status' => 'blocked',
                        'safe_error_codes' => ['benchmark_run_id_invalid'],
                    ], JSON_THROW_ON_ERROR));

                    return 2;
                }
                try {
                    $summary = app(
                        SpeechBenchmarkRunner::class,
                    )->preflightCompletion($runId);
                } catch (Throwable) {
                    $this->error(json_encode([
                        'status' => 'blocked',
                        'safe_error_codes' => [
                            'benchmark_completion_not_ready',
                        ],
                    ], JSON_THROW_ON_ERROR));

                    return 2;
                }
                $this->info(json_encode(
                    $summary,
                    JSON_THROW_ON_ERROR,
                ));

                return 0;
            }
            $this->info(json_encode([
                'status' => 'ready',
                'benchmark_id' => $configuration->string('speech.benchmark.id'),
                'configuration_sha256' => $configuration
                    ->configurationFingerprint(),
            ], JSON_THROW_ON_ERROR));

            return 0;
        }
        if (
            $phase === 'complete'
            && preg_match('/\A[a-f0-9]{32}\z/', $runId) !== 1
        ) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_codes' => ['benchmark_run_id_invalid'],
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        try {
            if ($phase === 'prepare') {
                $runId = $runId !== ''
                    ? $runId
                    : app(SpeechBenchmarkRunStore::class)->generateRunId();
                $this->line(json_encode([
                    'status' => 'preparing',
                    'run_id' => $runId,
                ], JSON_THROW_ON_ERROR));
                $summary = app(SpeechBenchmarkRunner::class)->prepare($runId);
                $this->info(json_encode($summary, JSON_THROW_ON_ERROR));

                return 0;
            }
            $report = app(SpeechBenchmarkRunner::class)->complete($runId);
        } catch (Throwable) {
            $this->error(json_encode([
                'status' => 'failed',
                'safe_error_codes' => ['speech_benchmark_failed'],
            ], JSON_THROW_ON_ERROR));

            return 1;
        }

        $this->info(json_encode([
            'status' => $report['passed'] === true ? 'passed' : 'failed',
            'benchmark_id' => $report['benchmark_id'],
            'safe_report_path' => $configuration->string(
                'speech.benchmark.safe_report_path',
            ),
        ], JSON_THROW_ON_ERROR));

        return $report['passed'] === true ? 0 : 1;
    })->purpose('Prepare, review, complete or purge the PR-009 speech benchmark');

Artisan::command(
    'ai:evaluate {--export-review=} {--import-review=} {--report=}',
    function (): int {
        $export = $this->option('export-review');
        $import = $this->option('import-review');
        $reportPath = $this->option('report');
        $runner = app(AiEvaluationRunner::class);
        $report = $runner->run(
            exportReviewPath: is_string($export) && $export !== ''
                ? $export
                : null,
            importReviewPath: is_string($import) && $import !== ''
                ? $import
                : null,
            reportPath: is_string($reportPath) && $reportPath !== ''
                ? $reportPath
                : storage_path('app/ai-evaluation/latest-report.json'),
        );
        $this->info(json_encode([
            'status' => $report['status'],
            'suite_hash' => $report['suite_hash'],
            'report_digest' => $report['report_digest'],
            'passed_count' => $report['passed_count'],
            'failed_count' => $report['failed_count'],
            'estimated_cost_microunits' => $report['estimated_cost_microunits'],
        ], JSON_THROW_ON_ERROR));

        return $report['status'] === 'completed' ? 0 : 1;
    },
)->purpose('Run the locked deterministic AI prompt evaluation suite');

Artisan::command(
    'lesson-practice:commission-app0063-levels
        {--inventory= : Path to levels-1-17 teaching-audio hash inventory JSON}
        {--approval=APP-0063 : Must be APP-0063}',
    function (): int {
        $inventory = $this->option('inventory');
        $approval = $this->option('approval');
        if (
            ! is_string($inventory) || ! is_file($inventory)
            || ! is_string($approval) || $approval !== 'APP-0063'
        ) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'app0063_commission_options_invalid',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        try {
            $result = app(App0063LevelsCommissioner::class)
                ->commission($inventory);
        } catch (Throwable $exception) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'app0063_commission_rejected',
                'detail' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        $this->info(json_encode([
            'status' => 'completed',
            ...$result,
        ], JSON_THROW_ON_ERROR));

        return 0;
    },
)->purpose('APP-0063 private auto-commission Levels 2–17 blueprints/reviews/attachments');

Artisan::command(
    'lesson-practice:replace-app0065-authored-levels
        {--approval=APP-0065 : Must be APP-0065}',
    function (): int {
        $approval = $this->option('approval');
        if (! is_string($approval) || $approval !== 'APP-0065') {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'app0065_replace_options_invalid',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        try {
            $result = app(App0065AuthoredLevelsReplacer::class)
                ->replace();
        } catch (Throwable $exception) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'app0065_replace_rejected',
                'detail' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        $this->info(json_encode([
            'status' => 'completed',
            ...$result,
        ], JSON_THROW_ON_ERROR));

        return 0;
    },
)->purpose('APP-0065 replace Levels 2–17 provisional templates with authored lesson text');

Artisan::command(
    'lesson-practice:record-app0066-english-review
        {--approval=APP-0066 : Must be APP-0066}',
    function (): int {
        $approval = $this->option('approval');
        if (! is_string($approval) || $approval !== 'APP-0066') {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'app0066_review_options_invalid',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        try {
            $result = app(App0066EnglishReviewReplacer::class)
                ->replace();
        } catch (Throwable $exception) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'app0066_review_rejected',
                'detail' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        $this->info(json_encode([
            'status' => 'completed',
            ...$result,
        ], JSON_THROW_ON_ERROR));

        return 0;
    },
)->purpose('APP-0066 record named English academic review of authored Levels 2–17');

Artisan::command(
    'lesson-practice:record-app0067-kannada-review
        {--approval=APP-0067 : Must be APP-0067}',
    function (): int {
        $approval = $this->option('approval');
        if (! is_string($approval) || $approval !== 'APP-0067') {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'app0067_review_options_invalid',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        try {
            $result = app(App0067KannadaReviewReplacer::class)
                ->replace();
        } catch (Throwable $exception) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'app0067_review_rejected',
                'detail' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        $this->info(json_encode([
            'status' => 'completed',
            ...$result,
        ], JSON_THROW_ON_ERROR));

        return 0;
    },
)->purpose('APP-0067 record named Kannada review of authored Levels 2–17');

Schedule::command('voice:purge-expired')
    ->everyFiveMinutes()
    ->withoutOverlapping();

if (app()->environment('speech-benchmark')) {
    Schedule::command('speech:benchmark --phase=purge --expired')
        ->hourly()
        ->withoutOverlapping();
}
