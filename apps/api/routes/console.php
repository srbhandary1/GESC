<?php

declare(strict_types=1);

use App\Conversation\Voice\Benchmark\SpeechBenchmarkRunner;
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

Artisan::command('speech:benchmark {--preflight}', function (): int {
    $configuration = app(AzureSpeechConfiguration::class);
    $errors = $configuration->preflightErrors(false);

    if ($errors !== []) {
        $this->error(json_encode([
            'status' => 'blocked',
            'safe_error_codes' => $errors,
        ], JSON_THROW_ON_ERROR));

        return 2;
    }

    if ((bool) $this->option('preflight')) {
        $this->info(json_encode([
            'status' => 'ready',
            'benchmark_id' => $configuration->string('speech.benchmark.id'),
            'configuration_sha256' => $configuration
                ->configurationFingerprint(),
        ], JSON_THROW_ON_ERROR));

        return 0;
    }

    try {
        $report = app(SpeechBenchmarkRunner::class)->run();
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
})->purpose('Run the locked, fail-closed PR-009 Azure Speech benchmark');

Schedule::command('voice:purge-expired')
    ->everyFiveMinutes()
    ->withoutOverlapping();
