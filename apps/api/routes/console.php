<?php

declare(strict_types=1);

use App\Conversation\Evaluation\AiEvaluationRunner;
use App\Curriculum\LessonPractice\App0063LevelsCommissioner;
use App\Curriculum\LessonPractice\App0065AuthoredLevelsReplacer;
use App\Curriculum\LessonPractice\App0066EnglishReviewReplacer;
use App\Curriculum\LessonPractice\App0067KannadaReviewReplacer;
use App\Curriculum\LessonPractice\DirectPathwayPublicationService;
use App\Identity\LocalOperatorIdentityBootstrapper;
use App\Models\Organization;
use App\Operator\MediaLibrary\App0062LessonAudioIngestor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'auth:bootstrap-local-operator-identity
        {--phone-file= : Absolute owner-readable temporary input file}
        {--user= : Expected existing user ULID}
        {--membership= : Expected existing membership ULID}
        {--approval= : Exact approval evidence reference}',
    function (): int {
        $option = function (string $key): string {
            $value = $this->option($key);

            return is_string($value) ? $value : '';
        };

        try {
            $result = app(LocalOperatorIdentityBootstrapper::class)->bootstrap(
                phoneFile: $option('phone-file'),
                expectedUserId: $option('user'),
                expectedMembershipId: $option('membership'),
                approvalReference: $option('approval'),
            );
        } catch (Throwable) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'local_operator_identity_bootstrap_rejected',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        $this->info(json_encode([
            'status' => 'bound',
            'user_id' => $result->userId,
            'membership_id' => $result->membershipId,
            'identity_id' => $result->identityId,
            'environment' => $result->environment,
            'approval_reference' => $result->approvalReference,
            'bound_at' => $result->boundAt->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        return 0;
    },
)->purpose('Bind the exact approved identity-free local Direct administrator');

Artisan::command(
    'lpl:bootstrap-local-pilot-authority
        {--organization= : Expected Direct organization ULID}
        {--user= : Expected existing user ULID}
        {--membership= : Expected existing membership ULID}
        {--approval= : Exact DR-0024 approval reference}
        {--owner-evidence= : Exact APP-0047 owner-evidence reference}
        {--idempotency-key= : UUID or ULID request identifier}',
    function (): int {
        $option = function (string $key): string {
            $value = $this->option($key);

            return is_string($value) ? $value : '';
        };

        try {
            $result = app(
                LocalLplPilotAuthorityBootstrapper::class,
            )->bootstrap(
                expectedOrganizationId: $option('organization'),
                expectedUserId: $option('user'),
                expectedMembershipId: $option('membership'),
                approvalReference: $option('approval'),
                ownerEvidenceReference: $option('owner-evidence'),
                idempotencyKey: $option('idempotency-key'),
            );
        } catch (Throwable) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'local_lpl_pilot_authority_bootstrap_rejected',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        $this->info(json_encode([
            'status' => $result->replayed ? 'replayed' : 'created',
            'program_id' => $result->programId,
            'role_id' => $result->roleId,
            'membership_id' => $result->membershipId,
            'appointment_id' => $result->appointmentId,
            'environment' => $result->environment,
            'approval_reference' => $result->approvalReference,
            'owner_evidence_reference' => $result->ownerEvidenceReference,
            'completed_at' => $result->completedAt->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        return 0;
    },
)->purpose('Create the exact approved local LPL pilot commissioning authority');

Artisan::command(
    'media:ingest-app0062-lesson-audio
        {--username= : Operator username}
        {--password= : Operator password}
        {--inventory= : Absolute inventory JSON path}
        {--staging-manifest= : Absolute staging hash manifest path}
        {--program= : LPL pilot program ULID}
        {--appointment= : Accepted rights-confirmer appointment ULID}
        {--approval=APP-0062 : Exact approval reference}',
    function (): int {
        $username = $this->option('username');
        $password = $this->option('password');
        $inventory = $this->option('inventory');
        $staging = $this->option('staging-manifest');
        $program = $this->option('program');
        $appointment = $this->option('appointment');
        $approval = $this->option('approval');
        if (
            ! is_string($username) || $username === ''
            || ! is_string($password) || $password === ''
            || ! is_string($inventory) || ! is_file($inventory)
            || ! is_string($staging) || ! is_file($staging)
            || ! is_string($program) || ! Str::isUlid($program)
            || ! is_string($appointment) || ! Str::isUlid($appointment)
            || ! is_string($approval) || $approval !== 'APP-0062'
        ) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'app0062_ingest_options_invalid',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        try {
            $result = app(App0062LessonAudioIngestor::class)->ingest(
                actorUsername: $username,
                actorPassword: $password,
                inventoryPath: $inventory,
                stagingManifestPath: $staging,
                programId: $program,
                appointmentId: $appointment,
                approvalReference: $approval,
            );
        } catch (Throwable $exception) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'app0062_ingest_rejected',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        $this->info(json_encode([
            'status' => 'completed',
            'approval_reference' => $approval,
            ...$result,
        ], JSON_THROW_ON_ERROR));

        return $result['failures'] === [] ? 0 : 1;
    },
)->purpose('Ingest APP-0062 exact teaching-audio hashes as private lesson_audio');

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

Artisan::command(
    'lesson-practice:publish-direct-pathway
        {--approval=WAVE-7.1 : Must be WAVE-7.1}',
    function (): int {
        $approval = $this->option('approval');
        if (! is_string($approval) || $approval !== DirectPathwayPublicationService::APPROVAL) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'wave71_publish_options_invalid',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        try {
            $result = app(DirectPathwayPublicationService::class)
                ->publish();
        } catch (Throwable $exception) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'wave71_publish_rejected',
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
)->purpose('Wave 7.1 publish attached Direct lesson-practice Levels 1–17');

Artisan::command(
    'conversation:seed-roleplay-catalog {--organization= : Organization ULID; defaults to Direct}',
    function (): int {
        $organizationOption = $this->option('organization');
        $organizationId = is_string($organizationOption) && $organizationOption !== ''
            ? $organizationOption
            : (string) DB::table('organizations')
                ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
                ->value('id');

        if ($organizationId === '' || ! Str::isUlid($organizationId)) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'roleplay_catalog_organization_missing',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        $result = app(RoleplayCatalogSeeder::class)
            ->seedOrganization($organizationId, now());

        $this->info(json_encode([
            'status' => 'completed',
            'organization_id' => $organizationId,
            ...$result,
            'catalog_size' => count(RoleplayCatalog::entries()),
        ], JSON_THROW_ON_ERROR));

        return 0;
    },
)->purpose('Seed synthetic Role-play catalog personas/scenarios for an organization');

Artisan::command(
    'lpl:transcribe-media
        {--phase=preflight : preflight, run or status}
        {--campaign= : Exact APP-0052 campaign ULID}
        {--membership= : Exact initiating membership ULID}',
    function (): int {
        $campaign = $this->option('campaign');
        $membership = $this->option('membership');
        $phase = $this->option('phase');
        if (
            ! is_string($campaign)
            || ! Str::isUlid($campaign)
            || ! is_string($membership)
            || ! Str::isUlid($membership)
            || ! is_string($phase)
            || ! in_array($phase, ['preflight', 'run', 'status'], true)
        ) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'media_transcription_options_invalid',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }

        try {
            if ($phase === 'status') {
                $report = app(MediaTranscriptionCampaignRunner::class)->report(
                    $campaign,
                );
                if (($report['state'] ?? null) === 'completed') {
                    app(MediaTranscriptionSafeReportWriter::class)->write(
                        $report,
                    );
                }
                $this->info(json_encode([
                    ...$report,
                    'status' => 'reported',
                ], JSON_THROW_ON_ERROR));

                return ($report['state'] ?? null) === 'completed' ? 0 : 1;
            }

            $authorization = app(
                MediaTranscriptionConfiguration::class,
            )->authorize($campaign);
            if ($phase === 'preflight') {
                $this->info(json_encode([
                    'status' => 'authorized',
                    'campaign_id' => $campaign,
                    'manifest_sha256' => $authorization['manifest_sha256'],
                    'configuration_sha256' => $authorization['configuration_sha256'],
                    'logical_assets' => 4,
                ], JSON_THROW_ON_ERROR));

                return 0;
            }

            $report = app(MediaTranscriptionCampaignRunner::class)->run(
                $campaign,
                $membership,
                static fn (array $current, Factory $http): AzureCurriculumMediaTranscriptionProvider => new AzureCurriculumMediaTranscriptionProvider(
                    $http,
                    $current,
                ),
            );
            $terminal = in_array(
                $report['state'] ?? null,
                ['provider_complete', 'completed', 'failed'],
                true,
            );
            if ($terminal) {
                $permit = config('media_transcription.evidence.permit_file');
                if (is_string($permit) && is_file($permit) && ! unlink($permit)) {
                    throw new RuntimeException('Campaign permit removal failed.');
                }
                config()->set('media_transcription.live_requests_enabled', false);
                putenv('LPL_MEDIA_TRANSCRIPTION_LIVE_REQUESTS_ENABLED');
            }
            $reportPath = $authorization['safe_report_file'] ?? null;
            app(MediaTranscriptionSafeReportWriter::class)->write(
                $report,
                is_string($reportPath) ? $reportPath : null,
            );
            $this->info(json_encode([
                ...$report,
                'status' => 'finished',
                'permit_removed' => ! is_file((string) config(
                    'media_transcription.evidence.permit_file',
                )),
                'credential_rotation_required' => true,
            ], JSON_THROW_ON_ERROR));

            return ($report['state'] ?? null) === 'provider_complete' ? 0 : 1;
        } catch (Throwable) {
            $this->error(json_encode([
                'status' => 'blocked',
                'safe_error_code' => 'media_transcription_rejected',
            ], JSON_THROW_ON_ERROR));

            return 2;
        }
    },
)->purpose('Run the exact disabled-default APP-0052 media transcription campaign');

Artisan::command('lpl:purge-media-transcription-candidates {--limit=100}', function (): int {
    $limit = $this->option('limit');
    if (! is_string($limit) || ! ctype_digit($limit)) {
        $this->error(json_encode([
            'status' => 'blocked',
            'safe_error_code' => 'media_transcription_cleanup_limit_invalid',
        ], JSON_THROW_ON_ERROR));

        return 2;
    }
    $result = app(MediaTranscriptionRetentionService::class)->run((int) $limit);
    $this->info(json_encode($result, JSON_THROW_ON_ERROR));

    return $result['overdue_backlog'] === 0
        && $result['dead_letter_backlog'] === 0 ? 0 : 1;
})->purpose('Expire and delete short-lived curriculum transcription candidates');

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
