<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\CurriculumPublicationScheduler;
use App\Curriculum\Publication\CurriculumPublisher;
use App\Curriculum\Publication\CurriculumRollbackService;
use App\Learning\Attempts\DailyMissionAttemptService;
use App\Learning\Exceptions\LearningIdempotencyConflict;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Missions\DailyMissionDefinition;
use App\Learning\Missions\DailyMissionReader;
use App\Learning\Offline\OfflineCompletionService;
use App\Learning\Offline\OfflineMissionBundleService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * @phpstan-type OfflineCommand array{
 *   bundle_id: string,
 *   lease: string,
 *   client_attempt_id: string,
 *   check_id: string,
 *   selected_option_id: string
 * }
 */
final class ConcurrentCurriculumPublicationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
    }

    protected function tearDown(): void
    {
        if (
            DB::connection()->getDriverName() === 'pgsql'
            && Schema::hasTable('learner_attempts')
        ) {
            DB::statement(
                <<<'SQL'
                    TRUNCATE TABLE
                        roleplay_scenarios,
                        learning_commands,
                        review_schedules,
                        mastery_states,
                        attempt_submissions,
                        item_responses,
                        learner_attempts,
                        learner_goals,
                        learner_profiles,
                        enrollments
                    CASCADE
                    SQL,
            );
        }

        parent::tearDown();
    }

    public function test_two_first_publications_with_the_same_expected_head_allow_exactly_one_winner(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The PostgreSQL concurrency gate requires the pcntl extension.',
        );

        $fixture = $this->makeSeededDraftPublishable();
        $databaseConfig = DB::connection()->getConfig();

        DB::disconnect();

        /** @var list<array{pid: int, pipe: resource, reaped: bool}> $workers */
        $workers = [];
        $lockConnection = null;
        $results = [];
        $bothWorkersBlocked = false;

        try {
            for ($worker = 0; $worker < 2; $worker++) {
                $workers[] = $this->spawnPublicationWorker(
                    organizationId: $fixture['organization_id'],
                    actorUserId: $fixture['actor_user_id'],
                    actorMembershipId: $fixture['actor_membership_id'],
                    curriculumVersionId: $fixture['curriculum_version_id'],
                    idempotencyKey: sprintf('first-publication-race-%d', $worker),
                );
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 10);

                if (fgets($worker['pipe']) !== "ready\n") {
                    throw new RuntimeException(
                        'A concurrent publication worker did not become ready.',
                    );
                }
            }

            $lockConnection = $this->openPostgreSqlConnection($databaseConfig);
            $lockConnection->beginTransaction();

            $lock = $lockConnection->prepare(
                'select id from programs where id = :id for update',
            );
            $lock->execute(['id' => $fixture['program_id']]);

            foreach ($workers as $worker) {
                if (fwrite($worker['pipe'], "go\n") === false) {
                    throw new RuntimeException(
                        'A concurrent publication worker could not be released.',
                    );
                }

                fflush($worker['pipe']);
            }

            $bothWorkersBlocked = $this->waitUntilBothWorkersBlock(
                $lockConnection,
            );

            $lockConnection->commit();

            foreach ($workers as $index => $worker) {
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException(
                        'A concurrent publication worker returned no result.',
                    );
                }

                /** @var array{outcome?: mixed, publication_id?: mixed, head_version?: mixed, worker_exception?: mixed} $result */
                $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $results[] = $result;

                $waited = pcntl_waitpid($worker['pid'], $processStatus);

                if (
                    $waited !== $worker['pid']
                    || pcntl_wexitstatus($processStatus) !== 0
                ) {
                    throw new RuntimeException(
                        'A concurrent publication worker failed.',
                    );
                }

                $workers[$index]['reaped'] = true;
            }
        } finally {
            if (
                $lockConnection instanceof PDO
                && $lockConnection->inTransaction()
            ) {
                $lockConnection->rollBack();
            }

            foreach ($workers as $index => $worker) {
                if (is_resource($worker['pipe'])) {
                    fclose($worker['pipe']);
                }

                if ($worker['reaped']) {
                    continue;
                }

                $waited = pcntl_waitpid(
                    $worker['pid'],
                    $processStatus,
                    WNOHANG,
                );

                if (
                    $waited === 0
                    && function_exists('posix_kill')
                ) {
                    posix_kill($worker['pid'], SIGKILL);
                    pcntl_waitpid($worker['pid'], $processStatus);
                }

                $workers[$index]['reaped'] = true;
            }

            DB::purge();
            DB::reconnect();
        }

        self::assertTrue(
            $bothWorkersBlocked,
            sprintf(
                'Both publication workers must enter the PostgreSQL lock queue; worker results: %s.',
                json_encode($results, JSON_THROW_ON_ERROR),
            ),
        );
        self::assertCount(2, $results);
        self::assertSame(
            ['published', 'rejected'],
            collect($results)
                ->pluck('outcome')
                ->sort()
                ->values()
                ->all(),
        );

        /** @var array{publication_id: string, head_version: int} $winner */
        $winner = collect($results)
            ->firstWhere('outcome', 'published');

        self::assertSame(1, $winner['head_version']);
        self::assertSame(
            1,
            DB::table('curriculum_publications')
                ->where('organization_id', $fixture['organization_id'])
                ->where('program_id', $fixture['program_id'])
                ->count(),
        );
        self::assertSame(
            $winner['publication_id'],
            DB::table('curriculum_publications')
                ->where('organization_id', $fixture['organization_id'])
                ->where('program_id', $fixture['program_id'])
                ->sole()
                ->id,
        );
        self::assertSame(
            1,
            DB::table('publication_heads')
                ->where('organization_id', $fixture['organization_id'])
                ->where('program_id', $fixture['program_id'])
                ->count(),
        );
        $head = DB::table('publication_heads')
            ->where('organization_id', $fixture['organization_id'])
            ->where('program_id', $fixture['program_id'])
            ->sole();
        self::assertSame($winner['publication_id'], $head->active_publication_id);
        self::assertSame(1, $head->lock_version);
        self::assertSame(
            1,
            DB::table('publication_commands')
                ->where('organization_id', $fixture['organization_id'])
                ->where('operation', 'curriculum.publish')
                ->where('aggregate_id', $fixture['program_id'])
                ->count(),
        );
    }

    public function test_two_future_schedules_with_the_same_expected_head_allow_exactly_one_winner(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The PostgreSQL concurrency gate requires the pcntl extension.',
        );

        $fixture = $this->makeSeededDraftPublishable();
        $databaseConfig = DB::connection()->getConfig();
        $activationAt = now()->addHour()->toISOString();

        DB::disconnect();

        /** @var list<array{pid: int, pipe: resource, reaped: bool}> $workers */
        $workers = [];
        $lockConnection = null;
        $results = [];
        $bothWorkersBlocked = false;

        try {
            for ($worker = 0; $worker < 2; $worker++) {
                $workers[] = $this->spawnPublicationWorker(
                    organizationId: $fixture['organization_id'],
                    actorUserId: $fixture['actor_user_id'],
                    actorMembershipId: $fixture['actor_membership_id'],
                    curriculumVersionId: $fixture['curriculum_version_id'],
                    idempotencyKey: sprintf('first-schedule-race-%d', $worker),
                    activationAt: $activationAt,
                );
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 10);

                if (fgets($worker['pipe']) !== "ready\n") {
                    throw new RuntimeException(
                        'A concurrent schedule worker did not become ready.',
                    );
                }
            }

            $lockConnection = $this->openPostgreSqlConnection($databaseConfig);
            $lockConnection->beginTransaction();

            $lock = $lockConnection->prepare(
                'select id from programs where id = :id for update',
            );
            $lock->execute(['id' => $fixture['program_id']]);

            foreach ($workers as $worker) {
                if (fwrite($worker['pipe'], "go\n") === false) {
                    throw new RuntimeException(
                        'A concurrent schedule worker could not be released.',
                    );
                }

                fflush($worker['pipe']);
            }

            $bothWorkersBlocked = $this->waitUntilBothWorkersBlock(
                $lockConnection,
            );
            $lockConnection->commit();

            foreach ($workers as $index => $worker) {
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException(
                        'A concurrent schedule worker returned no result.',
                    );
                }

                /** @var array{outcome?: mixed, publication_id?: mixed, head_version?: mixed, worker_exception?: mixed} $result */
                $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $results[] = $result;

                $waited = pcntl_waitpid($worker['pid'], $processStatus);

                if (
                    $waited !== $worker['pid']
                    || pcntl_wexitstatus($processStatus) !== 0
                ) {
                    throw new RuntimeException(
                        'A concurrent schedule worker failed.',
                    );
                }

                $workers[$index]['reaped'] = true;
            }
        } finally {
            if (
                $lockConnection instanceof PDO
                && $lockConnection->inTransaction()
            ) {
                $lockConnection->rollBack();
            }

            foreach ($workers as $index => $worker) {
                if (is_resource($worker['pipe'])) {
                    fclose($worker['pipe']);
                }

                if ($worker['reaped']) {
                    continue;
                }

                $waited = pcntl_waitpid(
                    $worker['pid'],
                    $processStatus,
                    WNOHANG,
                );

                if (
                    $waited === 0
                    && function_exists('posix_kill')
                ) {
                    posix_kill($worker['pid'], SIGKILL);
                    pcntl_waitpid($worker['pid'], $processStatus);
                }

                $workers[$index]['reaped'] = true;
            }

            DB::purge();
            DB::reconnect();
        }

        self::assertTrue(
            $bothWorkersBlocked,
            sprintf(
                'Both schedule workers must enter the PostgreSQL lock queue; worker results: %s.',
                json_encode($results, JSON_THROW_ON_ERROR),
            ),
        );
        self::assertCount(2, $results);
        self::assertSame(
            ['rejected', 'scheduled'],
            collect($results)
                ->pluck('outcome')
                ->sort()
                ->values()
                ->all(),
        );

        /** @var array{publication_id: string, head_version: int} $winner */
        $winner = collect($results)->firstWhere('outcome', 'scheduled');

        self::assertSame(1, $winner['head_version']);
        self::assertSame(
            1,
            DB::table('curriculum_publications')
                ->where('organization_id', $fixture['organization_id'])
                ->where('program_id', $fixture['program_id'])
                ->count(),
        );
        $head = DB::table('publication_heads')
            ->where('organization_id', $fixture['organization_id'])
            ->where('program_id', $fixture['program_id'])
            ->sole();
        self::assertNull($head->active_curriculum_version_id);
        self::assertNull($head->active_publication_id);
        self::assertSame(1, $head->lock_version);
        self::assertSame(
            1,
            DB::table('publication_events')
                ->where('organization_id', $fixture['organization_id'])
                ->where('program_id', $fixture['program_id'])
                ->where('event_type', 'scheduled')
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('publication_commands')
                ->where('organization_id', $fixture['organization_id'])
                ->where('operation', 'curriculum.schedule')
                ->where('aggregate_id', $fixture['program_id'])
                ->count(),
        );
    }

    public function test_two_rollbacks_with_the_same_expected_head_allow_exactly_one_winner(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The PostgreSQL concurrency gate requires the pcntl extension.',
        );

        $fixture = $this->makeSeededDraftPublishable();
        $actor = User::query()->findOrFail($fixture['actor_user_id']);
        $context = new OrganizationContext(
            organizationId: $fixture['organization_id'],
            actorUserId: $fixture['actor_user_id'],
            membershipId: $fixture['actor_membership_id'],
            source: OrganizationContextSource::Membership,
        );
        $contexts = $this->app->make(OrganizationContextManager::class);
        $first = $contexts->run(
            $context,
            fn () => $this->app->make(CurriculumPublisher::class)->publish(
                actor: $actor,
                curriculumVersionId: $fixture['curriculum_version_id'],
                expectedHeadVersion: 0,
                idempotencyKey: 'rollback-race-first-publication',
            ),
        );
        $secondVersionId = $this->insertAdditionalPublishableVersion(
            $fixture,
            'rollback-v2',
        );
        $second = $contexts->run(
            $context,
            fn () => $this->app->make(CurriculumPublisher::class)->publish(
                actor: $actor,
                curriculumVersionId: $secondVersionId,
                expectedHeadVersion: 1,
                idempotencyKey: 'rollback-race-second-publication',
            ),
        );
        self::assertNotSame($first->publicationId, $second->publicationId);
        self::assertSame(2, $second->headVersion);
        $databaseConfig = DB::connection()->getConfig();
        DB::disconnect();

        /** @var list<array{pid: int, pipe: resource, reaped: bool}> $workers */
        $workers = [];
        $lockConnection = null;
        $results = [];
        $bothWorkersBlocked = false;

        try {
            for ($worker = 0; $worker < 2; $worker++) {
                $workers[] = $this->spawnPublicationWorker(
                    organizationId: $fixture['organization_id'],
                    actorUserId: $fixture['actor_user_id'],
                    actorMembershipId: $fixture['actor_membership_id'],
                    curriculumVersionId: $fixture['curriculum_version_id'],
                    idempotencyKey: sprintf('rollback-race-%d', $worker),
                    rollbackProgramId: $fixture['program_id'],
                    rollbackTargetPublicationId: $first->publicationId,
                    expectedHeadVersion: 2,
                );
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 10);

                if (fgets($worker['pipe']) !== "ready\n") {
                    throw new RuntimeException(
                        'A concurrent rollback worker did not become ready.',
                    );
                }
            }

            $lockConnection = $this->openPostgreSqlConnection($databaseConfig);
            $lockConnection->beginTransaction();
            $lock = $lockConnection->prepare(
                'select id from programs where id = :id for update',
            );
            $lock->execute(['id' => $fixture['program_id']]);

            foreach ($workers as $worker) {
                fwrite($worker['pipe'], "go\n");
                fflush($worker['pipe']);
            }

            $bothWorkersBlocked = $this->waitUntilBothWorkersBlock(
                $lockConnection,
            );
            $lockConnection->commit();

            foreach ($workers as $index => $worker) {
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException(
                        'A concurrent rollback worker returned no result.',
                    );
                }

                $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

                if (! is_array($result)) {
                    throw new RuntimeException(
                        'A concurrent rollback worker returned invalid JSON.',
                    );
                }

                $results[] = $result;
                $waited = pcntl_waitpid($worker['pid'], $processStatus);

                if (
                    $waited !== $worker['pid']
                    || pcntl_wexitstatus($processStatus) !== 0
                ) {
                    throw new RuntimeException(
                        'A concurrent rollback worker failed.',
                    );
                }

                $workers[$index]['reaped'] = true;
            }
        } finally {
            if (
                $lockConnection instanceof PDO
                && $lockConnection->inTransaction()
            ) {
                $lockConnection->rollBack();
            }

            foreach ($workers as $index => $worker) {
                if (is_resource($worker['pipe'])) {
                    fclose($worker['pipe']);
                }

                if ($worker['reaped']) {
                    continue;
                }

                $waited = pcntl_waitpid(
                    $worker['pid'],
                    $processStatus,
                    WNOHANG,
                );

                if ($waited === 0 && function_exists('posix_kill')) {
                    posix_kill($worker['pid'], SIGKILL);
                    pcntl_waitpid($worker['pid'], $processStatus);
                }

                $workers[$index]['reaped'] = true;
            }

            DB::purge();
            DB::reconnect();
        }

        self::assertTrue($bothWorkersBlocked);
        self::assertSame(
            ['rejected', 'rolled_back'],
            collect($results)
                ->pluck('outcome')
                ->sort()
                ->values()
                ->all(),
        );
        $head = DB::table('publication_heads')
            ->where('organization_id', $fixture['organization_id'])
            ->where('program_id', $fixture['program_id'])
            ->sole();
        self::assertSame($first->publicationId, $head->active_publication_id);
        self::assertSame(3, $head->lock_version);
        self::assertSame(
            1,
            DB::table('publication_commands')
                ->where('organization_id', $fixture['organization_id'])
                ->where('operation', 'curriculum.rollback')
                ->where('aggregate_id', $fixture['program_id'])
                ->count(),
        );
    }

    public function test_concurrent_learning_start_and_submit_have_one_authoritative_attempt_and_submission(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The PostgreSQL concurrency gate requires the pcntl extension.',
        );

        $fixture = $this->makeConcurrentLearningFixture();
        $this->app->instance(
            DailyMissionReader::class,
            new ConcurrentFixedDailyMissionReader($fixture['mission']),
        );
        $databaseConfig = DB::connection()->getConfig();
        $startKeys = [(string) Str::uuid(), (string) Str::uuid()];
        $startResults = $this->raceLearningWorkers(
            databaseConfig: $databaseConfig,
            operation: 'start',
            organizationId: $fixture['organization_id'],
            actorUserId: $fixture['learner_user_id'],
            actorMembershipId: $fixture['learner_membership_id'],
            enrollmentId: $fixture['enrollment_id'],
            missionId: $fixture['mission']->missionId,
            attemptId: null,
            idempotencyKeys: $startKeys,
        );

        self::assertSame(
            ['started', 'started'],
            collect($startResults)
                ->pluck('outcome')
                ->sort()
                ->values()
                ->all(),
        );
        self::assertSame(
            [false, true],
            collect($startResults)
                ->pluck('result.replayed')
                ->sort()
                ->values()
                ->all(),
        );
        self::assertCount(
            1,
            collect($startResults)
                ->pluck('result.id')
                ->unique()
                ->values()
                ->all(),
        );
        $attemptId = (string) $startResults[0]['result']['id'];
        $attempt = DB::table('learner_attempts')
            ->where('organization_id', $fixture['organization_id'])
            ->where('enrollment_id', $fixture['enrollment_id'])
            ->where(
                'curriculum_mission_id',
                $fixture['mission']->missionId,
            )
            ->sole();

        self::assertSame($attemptId, $attempt->id);
        self::assertSame(1, $attempt->attempt_number);
        self::assertSame('in_progress', $attempt->status);
        self::assertSame(
            2,
            DB::table('learning_commands')
                ->where('organization_id', $fixture['organization_id'])
                ->where('operation', 'learner.attempt.start')
                ->where('aggregate_id', $fixture['enrollment_id'])
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('audit_events')
                ->where('organization_id', $fixture['organization_id'])
                ->where('action', 'learner.attempt.started')
                ->where('subject_id', $attemptId)
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('outbox_events')
                ->where('organization_id', $fixture['organization_id'])
                ->where('event_type', 'learner.attempt.started')
                ->where('aggregate_id', $attemptId)
                ->count(),
        );

        $learner = User::query()->findOrFail(
            $fixture['learner_user_id'],
        );
        $learnerContext = new OrganizationContext(
            organizationId: $fixture['organization_id'],
            actorUserId: $fixture['learner_user_id'],
            membershipId: $fixture['learner_membership_id'],
            source: OrganizationContextSource::Membership,
        );
        $this->app
            ->make(OrganizationContextManager::class)
            ->run(
                $learnerContext,
                fn (): bool => $this->app->make(
                    DailyMissionAttemptService::class,
                )->respond(
                    learner: $learner,
                    attemptId: $attemptId,
                    checkId: $fixture['mission']->checkId,
                    selectedOptionId: $fixture['mission']->correctOptionId,
                    localRecording: null,
                    idempotencyKey: (string) Str::uuid(),
                ),
            );

        $submitKeys = [(string) Str::uuid(), (string) Str::uuid()];
        $submitResults = $this->raceLearningWorkers(
            databaseConfig: $databaseConfig,
            operation: 'submit',
            organizationId: $fixture['organization_id'],
            actorUserId: $fixture['learner_user_id'],
            actorMembershipId: $fixture['learner_membership_id'],
            enrollmentId: $fixture['enrollment_id'],
            missionId: $fixture['mission']->missionId,
            attemptId: $attemptId,
            idempotencyKeys: $submitKeys,
        );

        self::assertSame(
            ['rejected', 'submitted'],
            collect($submitResults)
                ->pluck('outcome')
                ->sort()
                ->values()
                ->all(),
        );
        /** @var array{
         *   outcome: string,
         *   idempotency_key: string,
         *   result: array<string, mixed>
         * } $winner
         */
        $winner = collect($submitResults)
            ->firstWhere('outcome', 'submitted');
        /** @var array{
         *   outcome: string,
         *   idempotency_key: string,
         *   rejection_class: class-string
         * } $loser
         */
        $loser = collect($submitResults)
            ->firstWhere('outcome', 'rejected');
        self::assertSame(
            LearningIdempotencyConflict::class,
            $loser['rejection_class'],
        );
        self::assertSame(
            [
                'first_attempt_id' => $attemptId,
                'first_attempt_number' => 1,
                'latest_attempt_id' => $attemptId,
                'latest_attempt_number' => 1,
                'best_attempt_id' => $attemptId,
                'best_attempt_number' => 1,
            ],
            $winner['result']['evidence'],
        );
        self::assertSame(
            1,
            DB::table('attempt_submissions')
                ->where('organization_id', $fixture['organization_id'])
                ->where('learner_attempt_id', $attemptId)
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('mastery_states')
                ->where('organization_id', $fixture['organization_id'])
                ->where('enrollment_id', $fixture['enrollment_id'])
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('review_schedules')
                ->where('organization_id', $fixture['organization_id'])
                ->where('source_attempt_id', $attemptId)
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('learning_commands')
                ->where('organization_id', $fixture['organization_id'])
                ->where('operation', 'learner.attempt.submit')
                ->where('aggregate_id', $attemptId)
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('learning_commands')
                ->where('organization_id', $fixture['organization_id'])
                ->where('operation', 'learner.attempt.submit')
                ->where('aggregate_id', $attemptId)
                ->where('idempotency_key', $loser['idempotency_key'])
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('audit_events')
                ->where('organization_id', $fixture['organization_id'])
                ->where('action', 'learner.attempt.submitted')
                ->where('subject_id', $attemptId)
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('outbox_events')
                ->where('organization_id', $fixture['organization_id'])
                ->where('event_type', 'learner.attempt.submitted')
                ->where('aggregate_id', $attemptId)
                ->count(),
        );

        $learner = User::query()->findOrFail(
            $fixture['learner_user_id'],
        );
        $exactReplay = $this->app
            ->make(OrganizationContextManager::class)
            ->run(
                $learnerContext,
                fn (): array => $this->app->make(
                    DailyMissionAttemptService::class,
                )->submit(
                    learner: $learner,
                    attemptId: $attemptId,
                    idempotencyKey: $winner['idempotency_key'],
                ),
            );
        self::assertSame($winner['result'], $exactReplay);
    }

    public function test_concurrent_exact_offline_completion_replay_creates_one_submitted_attempt(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The PostgreSQL concurrency gate requires the pcntl extension.',
        );

        $fixture = $this->makeConcurrentLearningFixture();
        $this->app->instance(
            DailyMissionReader::class,
            new ConcurrentFixedDailyMissionReader($fixture['mission']),
        );
        $learner = User::query()->findOrFail(
            $fixture['learner_user_id'],
        );
        $learnerContext = new OrganizationContext(
            organizationId: $fixture['organization_id'],
            actorUserId: $fixture['learner_user_id'],
            membershipId: $fixture['learner_membership_id'],
            source: OrganizationContextSource::Membership,
        );
        $bundle = $this->app
            ->make(OrganizationContextManager::class)
            ->run(
                $learnerContext,
                fn (): array => $this->app->make(
                    OfflineMissionBundleService::class,
                )->issue(
                    learner: $learner,
                    enrollmentId: $fixture['enrollment_id'],
                    missionId: $fixture['mission']->missionId,
                ),
            );
        $commandId = (string) Str::uuid();
        $results = $this->raceLearningWorkers(
            databaseConfig: DB::connection()->getConfig(),
            operation: 'offline',
            organizationId: $fixture['organization_id'],
            actorUserId: $fixture['learner_user_id'],
            actorMembershipId: $fixture['learner_membership_id'],
            enrollmentId: $fixture['enrollment_id'],
            missionId: $fixture['mission']->missionId,
            attemptId: null,
            idempotencyKeys: [$commandId, $commandId],
            offlineCommand: [
                'bundle_id' => $bundle['manifest']['bundle_id'],
                'lease' => $bundle['lease'],
                'client_attempt_id' => (string) Str::uuid(),
                'check_id' => $fixture['mission']->checkId,
                'selected_option_id' => $fixture['mission']
                    ->correctOptionId,
            ],
        );

        self::assertSame(
            ['completed', 'completed'],
            collect($results)
                ->pluck('outcome')
                ->sort()
                ->values()
                ->all(),
        );
        self::assertSame($results[0]['result'], $results[1]['result']);
        self::assertSame(
            1,
            DB::table('learner_attempts')
                ->where('organization_id', $fixture['organization_id'])
                ->where('enrollment_id', $fixture['enrollment_id'])
                ->where('status', 'submitted')
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('attempt_submissions')
                ->where('organization_id', $fixture['organization_id'])
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('learning_commands')
                ->where('organization_id', $fixture['organization_id'])
                ->where('operation', 'learner.offline.complete')
                ->where('idempotency_key', $commandId)
                ->count(),
        );
    }

    public function test_transactional_authorization_recheck_locks_authority_rows_until_the_command_finishes(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());

        $fixture = $this->makeSeededDraftPublishable();
        $actor = User::query()->findOrFail($fixture['actor_user_id']);
        $context = new OrganizationContext(
            organizationId: $fixture['organization_id'],
            actorUserId: $fixture['actor_user_id'],
            membershipId: $fixture['actor_membership_id'],
            source: OrganizationContextSource::Membership,
        );
        $roleId = DB::table('membership_roles')
            ->where('organization_id', $fixture['organization_id'])
            ->where('membership_id', $fixture['actor_membership_id'])
            ->value('role_id');

        self::assertIsString($roleId);

        $revoker = $this->openPostgreSqlConnection(
            DB::connection()->getConfig(),
        );
        $revoker->exec("set lock_timeout = '250ms'");
        DB::beginTransaction();

        try {
            $allowed = $this->app
                ->make(OrganizationContextManager::class)
                ->run(
                    $context,
                    fn (): bool => $this->app
                        ->make(OrganizationAuthorization::class)
                        ->allows($actor, 'curriculum.publish'),
                );

            self::assertTrue($allowed);

            $delete = $revoker->prepare(
                <<<'SQL'
                    delete from role_permissions
                    where organization_id = :organization_id
                      and role_id = :role_id
                    SQL,
            );

            try {
                $delete->execute([
                    'organization_id' => $fixture['organization_id'],
                    'role_id' => $roleId,
                ]);
                self::fail(
                    'Concurrent permission revocation must wait for the authoritative transaction.',
                );
            } catch (PDOException $exception) {
                self::assertSame('55P03', $exception->getCode());
            }
        } finally {
            DB::rollBack();
        }

        self::assertSame(
            1,
            DB::table('role_permissions')
                ->where('organization_id', $fixture['organization_id'])
                ->where('role_id', $roleId)
                ->count(),
        );
    }

    /**
     * @return array{
     *   organization_id: string,
     *   learner_user_id: string,
     *   learner_membership_id: string,
     *   enrollment_id: string,
     *   mission: DailyMissionDefinition
     * }
     */
    private function makeConcurrentLearningFixture(): array
    {
        $fixture = $this->makeSeededDraftPublishable();
        $publisher = User::query()->findOrFail(
            $fixture['actor_user_id'],
        );
        $publisherContext = new OrganizationContext(
            organizationId: $fixture['organization_id'],
            actorUserId: $fixture['actor_user_id'],
            membershipId: $fixture['actor_membership_id'],
            source: OrganizationContextSource::Membership,
        );
        $publication = $this->app
            ->make(OrganizationContextManager::class)
            ->run(
                $publisherContext,
                fn () => $this->app->make(
                    CurriculumPublisher::class,
                )->publish(
                    actor: $publisher,
                    curriculumVersionId: $fixture[
                        'curriculum_version_id'
                    ],
                    expectedHeadVersion: 0,
                    idempotencyKey: 'learning-concurrency-publication',
                ),
            );
        $learner = $this->createUserAndMembership(
            $fixture['organization_id'],
            Membership::TYPE_LEARNER,
        );
        $learnerRoleId = $this->requiredStringValue(
            'roles',
            'id',
            [
                'organization_id' => $fixture['organization_id'],
                'key' => Role::DIRECT_LEARNER_KEY,
            ],
        );
        $now = now();
        DB::table('membership_roles')->insert([
            'organization_id' => $fixture['organization_id'],
            'membership_id' => $learner['membership_id'],
            'role_id' => $learnerRoleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $enrollmentId = (string) Str::ulid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $fixture['organization_id'],
            'learner_membership_id' => $learner['membership_id'],
            'cohort_id' => null,
            'curriculum_owner_organization_id' => $fixture[
                'organization_id'
            ],
            'curriculum_version_id' => $fixture[
                'curriculum_version_id'
            ],
            'curriculum_publication_id' => $publication->publicationId,
            'curriculum_share_grant_id' => null,
            'status' => 'active',
            'enrolled_at' => $now,
            'activated_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $missionId = $this->requiredStringValue(
            'curriculum_missions',
            'id',
            [
                'organization_id' => $fixture['organization_id'],
                'curriculum_version_id' => $fixture[
                    'curriculum_version_id'
                ],
                'key' => 'synthetic-introduction-mission',
            ],
        );
        $objectiveId = $this->requiredStringValue(
            'learning_objectives',
            'id',
            [
                'organization_id' => $fixture['organization_id'],
                'curriculum_version_id' => $fixture[
                    'curriculum_version_id'
                ],
                'key' => 'introduce-yourself',
            ],
        );
        $learningItemId = $this->requiredStringValue(
            'learning_items',
            'id',
            [
                'organization_id' => $fixture['organization_id'],
                'curriculum_version_id' => $fixture[
                    'curriculum_version_id'
                ],
                'key' => 'synthetic-introduction-prompt',
            ],
        );
        $sourceRevision = DB::table('content_revisions')
            ->where('organization_id', $fixture['organization_id'])
            ->where(
                'curriculum_version_id',
                $fixture['curriculum_version_id'],
            )
            ->where('revision_number', 1)
            ->sole(['id', 'content_hash']);
        $localizedVariant = DB::table('localized_variants')
            ->where('organization_id', $fixture['organization_id'])
            ->where(
                'curriculum_version_id',
                $fixture['curriculum_version_id'],
            )
            ->where('locale', 'kn-IN')
            ->sole(['id', 'content_hash']);
        $definitionHash = CanonicalJson::sha256([
            'fixture' => 'concurrent-learning',
            'mission_id' => $missionId,
            'publication_id' => $publication->publicationId,
        ]);
        $mission = new DailyMissionDefinition(
            missionId: $missionId,
            enrollmentId: $enrollmentId,
            publicationId: $publication->publicationId,
            ownerOrganizationId: $fixture['organization_id'],
            curriculumVersionId: $fixture['curriculum_version_id'],
            objectiveId: $objectiveId,
            learningItemId: $learningItemId,
            manifestHash: $publication->manifestHash,
            definitionHash: $definitionHash,
            sourceRevisionId: (string) $sourceRevision->id,
            sourceContentHash: (string) $sourceRevision->content_hash,
            kannadaVariantId: (string) $localizedVariant->id,
            kannadaContentHash: (string) $localizedVariant->content_hash,
            goalKey: 'everyday-conversation',
            estimatedMinutes: 3,
            title: 'Synthetic concurrency mission',
            kannadaGuidance: 'Synthetic test guidance.',
            modelText: 'Hello.',
            transcript: 'Hello.',
            canDo: 'I can choose the governed greeting.',
            checkId: 'concurrent-check',
            checkPrompt: 'Choose the greeting.',
            checkOptions: [
                ['id' => 'correct', 'label' => 'Hello'],
                ['id' => 'incorrect', 'label' => 'Goodbye'],
            ],
            correctOptionId: 'correct',
            modelAudioAssetId: (string) Str::ulid(),
            modelAudioMimeType: 'audio/webm',
            modelAudioContentHash: hash(
                'sha256',
                'synthetic-concurrency-audio',
            ),
            modelAudioByteSize: 512,
            modelAudioDurationMs: 1200,
            modelAudioStorageKey: 'test/concurrent-learning.webm',
        );

        return [
            'organization_id' => $fixture['organization_id'],
            'learner_user_id' => $learner['user_id'],
            'learner_membership_id' => $learner['membership_id'],
            'enrollment_id' => $enrollmentId,
            'mission' => $mission,
        ];
    }

    /**
     * @return array{
     *   organization_id: string,
     *   actor_user_id: string,
     *   actor_membership_id: string,
     *   program_id: string,
     *   curriculum_version_id: string,
     *   translator_membership_id: string,
     *   english_appointment_id: string,
     *   english_appointment_event_id: string,
     *   english_reviewer_membership_id: string,
     *   kannada_appointment_id: string,
     *   kannada_appointment_event_id: string,
     *   kannada_reviewer_membership_id: string
     * }
     */
    private function makeSeededDraftPublishable(): array
    {
        $organizationId = $this->requiredStringValue(
            'organizations',
            'id',
            ['system_key' => Organization::DIRECT_SYSTEM_KEY],
        );
        $programId = $this->requiredStringValue(
            'programs',
            'id',
            [
                'organization_id' => $organizationId,
                'key' => 'synthetic-foundation-preview',
            ],
        );
        $curriculumVersionId = $this->requiredStringValue(
            'curriculum_versions',
            'id',
            [
                'organization_id' => $organizationId,
                'program_id' => $programId,
                'key' => 'draft-1',
            ],
        );
        $sourceRevisionId = $this->requiredStringValue(
            'content_revisions',
            'id',
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'revision_number' => 1,
            ],
        );
        $sourceHash = $this->requiredStringValue(
            'content_revisions',
            'content_hash',
            ['id' => $sourceRevisionId],
        );
        $graphRevisionId = $this->requiredStringValue(
            'curriculum_graph_revisions',
            'id',
            [
                'organization_id' => $organizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'revision_number' => 1,
            ],
        );
        $graphHash = $this->requiredStringValue(
            'curriculum_graph_revisions',
            'graph_hash',
            ['id' => $graphRevisionId],
        );
        $publisher = $this->createUserAndMembership(
            $organizationId,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $englishReviewer = $this->createUserAndMembership(
            $organizationId,
            Membership::TYPE_TRAINER,
        );
        $kannadaReviewer = $this->createUserAndMembership(
            $organizationId,
            Membership::TYPE_TRAINER,
        );
        $translator = $this->createUserAndMembership(
            $organizationId,
            Membership::TYPE_TRAINER,
        );
        $this->grantPublicationPermission(
            organizationId: $organizationId,
            membershipId: $publisher['membership_id'],
        );
        [$englishAppointmentId, $englishAcceptanceEventId]
            = $this->insertAcceptedAppointment(
                organizationId: $organizationId,
                programId: $programId,
                appointeeUserId: $englishReviewer['user_id'],
                appointeeMembershipId: $englishReviewer['membership_id'],
                appointedByMembershipId: $publisher['membership_id'],
                authorityKind: 'english_academic_reviewer',
            );
        [$kannadaAppointmentId, $kannadaAcceptanceEventId]
            = $this->insertAcceptedAppointment(
                organizationId: $organizationId,
                programId: $programId,
                appointeeUserId: $kannadaReviewer['user_id'],
                appointeeMembershipId: $kannadaReviewer['membership_id'],
                appointedByMembershipId: $publisher['membership_id'],
                authorityKind: 'kannada_reviewer',
            );
        $now = now();
        $variantId = (string) Str::ulid();
        $variantPayload = [
            'fixture_notice' => 'Synthetic test translation only.',
            'instruction' => 'ವಂದನೆ ಹೇಳಿ ನಿಮ್ಮ ಹೆಸರನ್ನು ಬಳಸಿ ನಿಮ್ಮನ್ನು ಪರಿಚಯಿಸಿಕೊಳ್ಳಿ.',
        ];
        $variantHash = CanonicalJson::sha256($variantPayload);

        DB::table('localized_variants')->insert([
            'id' => $variantId,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'source_revision_id' => $sourceRevisionId,
            'parent_variant_id' => null,
            'locale' => 'kn-IN',
            'revision_number' => 1,
            'source_content_hash' => $sourceHash,
            'payload' => CanonicalJson::encode($variantPayload),
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $translator['membership_id'],
            'translator_attribution' => 'synthetic-concurrency-test-translator',
            'translated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_reviews')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graphHash,
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $englishAppointmentId,
            'authority_appointment_event_id' => $englishAcceptanceEventId,
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $englishReviewer['membership_id'],
            'decision' => 'approved',
            'rationale_key' => null,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_reviews')->insert([
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'review_kind' => 'academic_en',
                'source_revision_id' => $sourceRevisionId,
                'localized_variant_id' => null,
                'authority_appointment_id' => $englishAppointmentId,
                'authority_appointment_event_id' => $englishAcceptanceEventId,
                'reviewer_membership_id' => $englishReviewer['membership_id'],
                'decision' => 'approved',
                'source_content_hash' => $sourceHash,
                'localized_content_hash' => null,
                'rationale_key' => null,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'curriculum_version_id' => $curriculumVersionId,
                'review_kind' => 'kannada',
                'source_revision_id' => $sourceRevisionId,
                'localized_variant_id' => $variantId,
                'authority_appointment_id' => $kannadaAppointmentId,
                'authority_appointment_event_id' => $kannadaAcceptanceEventId,
                'reviewer_membership_id' => $kannadaReviewer['membership_id'],
                'decision' => 'approved',
                'source_content_hash' => $sourceHash,
                'localized_content_hash' => $variantHash,
                'rationale_key' => null,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return [
            'organization_id' => $organizationId,
            'actor_user_id' => $publisher['user_id'],
            'actor_membership_id' => $publisher['membership_id'],
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'translator_membership_id' => $translator['membership_id'],
            'english_appointment_id' => $englishAppointmentId,
            'english_appointment_event_id' => $englishAcceptanceEventId,
            'english_reviewer_membership_id' => $englishReviewer['membership_id'],
            'kannada_appointment_id' => $kannadaAppointmentId,
            'kannada_appointment_event_id' => $kannadaAcceptanceEventId,
            'kannada_reviewer_membership_id' => $kannadaReviewer['membership_id'],
        ];
    }

    /**
     * @param  array{
     *   organization_id: string,
     *   actor_membership_id: string,
     *   program_id: string,
     *   translator_membership_id: string,
     *   english_appointment_id: string,
     *   english_appointment_event_id: string,
     *   english_reviewer_membership_id: string,
     *   kannada_appointment_id: string,
     *   kannada_appointment_event_id: string,
     *   kannada_reviewer_membership_id: string
     * }  $fixture
     */
    private function insertAdditionalPublishableVersion(
        array $fixture,
        string $versionKey,
    ): string {
        $versionId = (string) Str::ulid();
        $objectiveId = (string) Str::ulid();
        $moduleId = (string) Str::ulid();
        $lessonId = (string) Str::ulid();
        $documentId = (string) Str::ulid();
        $sourceRevisionId = (string) Str::ulid();
        $variantId = (string) Str::ulid();
        $itemId = (string) Str::ulid();
        $missionId = (string) Str::ulid();
        $sourcePayload = [
            'instruction' => 'Synthetic rollback-race English instruction.',
        ];
        $variantPayload = [
            'instruction' => 'Synthetic rollback-race Kannada instruction.',
        ];
        $sourceHash = CanonicalJson::sha256($sourcePayload);
        $variantHash = CanonicalJson::sha256($variantPayload);
        $now = now();

        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $fixture['organization_id'],
            'program_id' => $fixture['program_id'],
            'key' => $versionKey,
            'created_by_membership_id' => $fixture['actor_membership_id'],
            'created_by_attribution' => 'synthetic-rollback-race',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_objectives')->insert([
            'id' => $objectiveId,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'key' => 'rollback-objective',
            'outcome_code' => 'ROLLBACK_RACE',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_modules')->insert([
            'id' => $moduleId,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'key' => 'rollback-module',
            'sequence' => 1,
            'estimated_minutes' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lessons')->insert([
            'id' => $lessonId,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'curriculum_module_id' => $moduleId,
            'key' => 'rollback-lesson',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lesson_objectives')->insert([
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'objective_id' => $objectiveId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_documents')->insert([
            'id' => $documentId,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'key' => 'rollback-instruction',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revisions')->insert([
            'id' => $sourceRevisionId,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'parent_revision_id' => null,
            'revision_number' => 1,
            'source_locale' => 'en-IN',
            'content_type' => 'instruction',
            'learner_visible' => true,
            'localization_requirement' => 'kn_required',
            'pedagogically_authoritative' => true,
            'payload' => CanonicalJson::encode($sourcePayload),
            'content_hash' => $sourceHash,
            'authored_by_membership_id' => $fixture['actor_membership_id'],
            'author_attribution' => 'synthetic-rollback-race',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_revision_heads')->insert([
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'content_document_id' => $documentId,
            'content_revision_id' => $sourceRevisionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('localized_variants')->insert([
            'id' => $variantId,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'source_revision_id' => $sourceRevisionId,
            'parent_variant_id' => null,
            'locale' => 'kn-IN',
            'revision_number' => 1,
            'source_content_hash' => $sourceHash,
            'payload' => CanonicalJson::encode($variantPayload),
            'content_hash' => $variantHash,
            'translated_by_membership_id' => $fixture['translator_membership_id'],
            'translator_attribution' => 'synthetic-rollback-race',
            'translated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('learning_items')->insert([
            'id' => $itemId,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'lesson_id' => $lessonId,
            'content_document_id' => $documentId,
            'key' => 'rollback-item',
            'item_type' => 'speaking_prompt',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_missions')->insert([
            'id' => $missionId,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'instruction_content_document_id' => $documentId,
            'key' => 'rollback-mission',
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('mission_items')->insert([
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'mission_id' => $missionId,
            'learning_item_id' => $itemId,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $graph = $this->app->make(CurriculumGraphProjector::class)->project(
            $fixture['organization_id'],
            $versionId,
        );
        $graphRevisionId = (string) Str::ulid();
        DB::table('curriculum_graph_revisions')->insert([
            'id' => $graphRevisionId,
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'parent_revision_id' => null,
            'parent_revision_number' => null,
            'revision_number' => 1,
            'graph' => CanonicalJson::encode($graph['graph']),
            'graph_hash' => $graph['graph_hash'],
            'authored_by_membership_id' => $fixture['actor_membership_id'],
            'author_attribution' => 'synthetic-rollback-race',
            'authored_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_heads')->insert([
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graph['graph_hash'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_graph_reviews')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $fixture['organization_id'],
            'curriculum_version_id' => $versionId,
            'curriculum_graph_revision_id' => $graphRevisionId,
            'graph_hash' => $graph['graph_hash'],
            'authority_kind' => 'english_academic_reviewer',
            'authority_appointment_id' => $fixture['english_appointment_id'],
            'authority_appointment_event_id' => $fixture['english_appointment_event_id'],
            'authority_appointment_event_type' => 'accepted',
            'reviewer_membership_id' => $fixture['english_reviewer_membership_id'],
            'decision' => 'approved',
            'rationale_key' => null,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('content_reviews')->insert([
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $fixture['organization_id'],
                'curriculum_version_id' => $versionId,
                'review_kind' => 'academic_en',
                'source_revision_id' => $sourceRevisionId,
                'localized_variant_id' => null,
                'authority_appointment_id' => $fixture['english_appointment_id'],
                'authority_appointment_event_id' => $fixture['english_appointment_event_id'],
                'reviewer_membership_id' => $fixture['english_reviewer_membership_id'],
                'decision' => 'approved',
                'source_content_hash' => $sourceHash,
                'localized_content_hash' => null,
                'rationale_key' => null,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $fixture['organization_id'],
                'curriculum_version_id' => $versionId,
                'review_kind' => 'kannada',
                'source_revision_id' => $sourceRevisionId,
                'localized_variant_id' => $variantId,
                'authority_appointment_id' => $fixture['kannada_appointment_id'],
                'authority_appointment_event_id' => $fixture['kannada_appointment_event_id'],
                'reviewer_membership_id' => $fixture['kannada_reviewer_membership_id'],
                'decision' => 'approved',
                'source_content_hash' => $sourceHash,
                'localized_content_hash' => $variantHash,
                'rationale_key' => null,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return $versionId;
    }

    /**
     * @return array{user_id: string, membership_id: string}
     */
    private function createUserAndMembership(
        string $organizationId,
        string $membershipType,
    ): array {
        $user = User::query()->forceCreate([
            'status' => User::STATUS_ACTIVE,
        ]);
        $membershipId = (string) Str::ulid();
        $now = now();

        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organizationId,
            'user_id' => $user->getKey(),
            'membership_type' => $membershipType,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'user_id' => (string) $user->getKey(),
            'membership_id' => $membershipId,
        ];
    }

    private function grantPublicationPermission(
        string $organizationId,
        string $membershipId,
    ): void {
        $now = now();
        $roleId = (string) Str::ulid();
        $permissionId = $this->requiredStringValue(
            'permissions',
            'id',
            ['key' => 'curriculum.publish'],
        );

        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $organizationId,
            'key' => 'synthetic-concurrent-publisher',
            'name' => 'Synthetic concurrent publisher',
            'is_system' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('membership_roles')->insert([
            'organization_id' => $organizationId,
            'membership_id' => $membershipId,
            'role_id' => $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_permissions')->insert([
            'organization_id' => $organizationId,
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array{string, string}
     */
    private function insertAcceptedAppointment(
        string $organizationId,
        string $programId,
        string $appointeeUserId,
        string $appointeeMembershipId,
        string $appointedByMembershipId,
        string $authorityKind,
    ): array {
        $appointmentId = (string) Str::ulid();
        $appointedEventId = (string) Str::ulid();
        $acceptedEventId = (string) Str::ulid();
        $now = now();
        $effectiveAt = now()->subMinute();

        DB::table('authority_appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $organizationId,
            'appointee_user_id' => $appointeeUserId,
            'appointee_membership_id' => $appointeeMembershipId,
            'authority_kind' => $authorityKind,
            'scope_type' => 'program',
            'program_id' => $programId,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'qualification_basis' => 'Synthetic concurrency-test qualification',
            'approval_evidence_key' => 'APP-TEST-PUBLICATION-CONCURRENCY',
            'appointed_by_membership_id' => $appointedByMembershipId,
            'appointed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointment_events')->insert([
            [
                'id' => $appointedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'appointed',
                'actor_membership_id' => $appointedByMembershipId,
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => $effectiveAt,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $acceptedEventId,
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'accepted',
                'actor_membership_id' => $appointeeMembershipId,
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => $effectiveAt,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return [$appointmentId, $acceptedEventId];
    }

    /**
     * @param  array<string, int|string>  $where
     */
    private function requiredStringValue(
        string $table,
        string $column,
        array $where,
    ): string {
        $value = DB::table($table)->where($where)->value($column);

        if (! is_string($value)) {
            throw new RuntimeException(sprintf(
                'The synthetic concurrency fixture could not resolve %s.%s.',
                $table,
                $column,
            ));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $databaseConfig
     * @param  list<string>  $idempotencyKeys
     * @param  OfflineCommand|null  $offlineCommand
     * @return list<array<string, mixed>>
     */
    private function raceLearningWorkers(
        array $databaseConfig,
        string $operation,
        string $organizationId,
        string $actorUserId,
        string $actorMembershipId,
        string $enrollmentId,
        string $missionId,
        ?string $attemptId,
        array $idempotencyKeys,
        ?array $offlineCommand = null,
    ): array {
        DB::disconnect();

        /** @var list<array{pid: int, pipe: resource, reaped: bool}> $workers */
        $workers = [];
        /** @var list<int> $backendPids */
        $backendPids = [];
        /** @var list<array<string, mixed>> $results */
        $results = [];
        $lockConnection = null;
        $bothWorkersBlocked = false;

        try {
            foreach ($idempotencyKeys as $idempotencyKey) {
                $workers[] = $this->spawnLearningWorker(
                    operation: $operation,
                    organizationId: $organizationId,
                    actorUserId: $actorUserId,
                    actorMembershipId: $actorMembershipId,
                    enrollmentId: $enrollmentId,
                    missionId: $missionId,
                    attemptId: $attemptId,
                    idempotencyKey: $idempotencyKey,
                    offlineCommand: $offlineCommand,
                );
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 10);
                $ready = fgets($worker['pipe']);

                if (
                    ! is_string($ready)
                    || preg_match('/\Aready:(\d+)\n\z/', $ready, $match)
                        !== 1
                ) {
                    throw new RuntimeException(
                        'A concurrent learning worker did not become ready.',
                    );
                }

                $backendPids[] = (int) $match[1];
            }

            $lockConnection = $this->openPostgreSqlConnection(
                $databaseConfig,
            );
            $lockConnection->beginTransaction();
            $lock = $lockConnection->prepare(
                'select id from memberships where id = :id for update',
            );
            $lock->execute(['id' => $actorMembershipId]);

            foreach ($workers as $worker) {
                if (fwrite($worker['pipe'], "go\n") === false) {
                    throw new RuntimeException(
                        'A concurrent learning worker could not be released.',
                    );
                }

                fflush($worker['pipe']);
            }

            $bothWorkersBlocked = $this->waitUntilLearningWorkersBlock(
                $lockConnection,
                $backendPids,
            );
            $lockConnection->commit();

            foreach ($workers as $index => $worker) {
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException(
                        'A concurrent learning worker returned no result.',
                    );
                }

                /** @var array<string, mixed> $result */
                $result = json_decode(
                    $line,
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );
                $results[] = $result;
                $waited = pcntl_waitpid(
                    $worker['pid'],
                    $processStatus,
                );

                if (
                    $waited !== $worker['pid']
                    || pcntl_wexitstatus($processStatus) !== 0
                ) {
                    throw new RuntimeException(
                        'A concurrent learning worker failed.',
                    );
                }

                $workers[$index]['reaped'] = true;
            }
        } finally {
            if (
                $lockConnection instanceof PDO
                && $lockConnection->inTransaction()
            ) {
                $lockConnection->rollBack();
            }

            foreach ($workers as $index => $worker) {
                if (is_resource($worker['pipe'])) {
                    fclose($worker['pipe']);
                }

                if ($worker['reaped']) {
                    continue;
                }

                $waited = pcntl_waitpid(
                    $worker['pid'],
                    $processStatus,
                    WNOHANG,
                );

                if (
                    $waited === 0
                    && function_exists('posix_kill')
                ) {
                    posix_kill($worker['pid'], SIGKILL);
                    pcntl_waitpid($worker['pid'], $processStatus);
                }

                $workers[$index]['reaped'] = true;
            }

            DB::purge();
            DB::reconnect();
        }

        self::assertTrue(
            $bothWorkersBlocked,
            sprintf(
                'Both %s workers must enter the PostgreSQL lock queue; results: %s.',
                $operation,
                json_encode($results, JSON_THROW_ON_ERROR),
            ),
        );

        return $results;
    }

    /**
     * @param  OfflineCommand|null  $offlineCommand
     * @return array{pid: int, pipe: resource, reaped: bool}
     */
    private function spawnLearningWorker(
        string $operation,
        string $organizationId,
        string $actorUserId,
        string $actorMembershipId,
        string $enrollmentId,
        string $missionId,
        ?string $attemptId,
        string $idempotencyKey,
        ?array $offlineCommand,
    ): array {
        $sockets = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );

        if ($sockets === false) {
            throw new RuntimeException(
                'The concurrent learning worker pipe could not be created.',
            );
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);

            throw new RuntimeException(
                'The concurrent learning worker could not be forked.',
            );
        }

        if ($pid === 0) {
            fclose($sockets[0]);
            $this->runLearningWorker(
                pipe: $sockets[1],
                operation: $operation,
                organizationId: $organizationId,
                actorUserId: $actorUserId,
                actorMembershipId: $actorMembershipId,
                enrollmentId: $enrollmentId,
                missionId: $missionId,
                attemptId: $attemptId,
                idempotencyKey: $idempotencyKey,
                offlineCommand: $offlineCommand,
            );
        }

        fclose($sockets[1]);

        return [
            'pid' => $pid,
            'pipe' => $sockets[0],
            'reaped' => false,
        ];
    }

    /**
     * @param  resource  $pipe
     * @param  OfflineCommand|null  $offlineCommand
     */
    private function runLearningWorker(
        mixed $pipe,
        string $operation,
        string $organizationId,
        string $actorUserId,
        string $actorMembershipId,
        string $enrollmentId,
        string $missionId,
        ?string $attemptId,
        string $idempotencyKey,
        ?array $offlineCommand,
    ): never {
        try {
            DB::purge();
            DB::reconnect();
            $backend = DB::selectOne(
                'select pg_backend_pid() as backend_pid',
            );
            $backendPid = $backend->backend_pid ?? null;

            if (! is_int($backendPid)) {
                throw new RuntimeException(
                    'The learning worker could not resolve its backend PID.',
                );
            }

            fwrite($pipe, sprintf("ready:%d\n", $backendPid));
            fflush($pipe);

            if (fgets($pipe) !== "go\n") {
                throw new RuntimeException(
                    'The learning worker received an invalid release signal.',
                );
            }

            $actor = User::query()->findOrFail($actorUserId);
            $context = new OrganizationContext(
                organizationId: $organizationId,
                actorUserId: $actorUserId,
                membershipId: $actorMembershipId,
                source: OrganizationContextSource::Membership,
            );
            $service = $this->app->make(
                DailyMissionAttemptService::class,
            );
            $result = $this->app
                ->make(OrganizationContextManager::class)
                ->run(
                    $context,
                    function () use (
                        $actor,
                        $attemptId,
                        $enrollmentId,
                        $idempotencyKey,
                        $missionId,
                        $offlineCommand,
                        $operation,
                        $service,
                    ): array {
                        if (
                            $operation === 'offline'
                            && is_array($offlineCommand)
                        ) {
                            return $this->app->make(
                                OfflineCompletionService::class,
                            )->complete(
                                learner: $actor,
                                bundleId: (string) $offlineCommand[
                                    'bundle_id'
                                ],
                                lease: (string) $offlineCommand['lease'],
                                clientAttemptId: (string) $offlineCommand[
                                    'client_attempt_id'
                                ],
                                checkId: (string) $offlineCommand[
                                    'check_id'
                                ],
                                selectedOptionId: (string) $offlineCommand[
                                    'selected_option_id'
                                ],
                                localRecording: null,
                                idempotencyKey: $idempotencyKey,
                            );
                        }

                        if ($operation === 'start') {
                            return $service->start(
                                learner: $actor,
                                enrollmentId: $enrollmentId,
                                missionId: $missionId,
                                idempotencyKey: $idempotencyKey,
                            );
                        }

                        if (
                            $operation !== 'submit'
                            || $attemptId === null
                        ) {
                            throw new RuntimeException(
                                'The learning worker operation is invalid.',
                            );
                        }

                        return $service->submit(
                            learner: $actor,
                            attemptId: $attemptId,
                            idempotencyKey: $idempotencyKey,
                        );
                    },
                );
            $payload = [
                'outcome' => match ($operation) {
                    'start' => 'started',
                    'offline' => 'completed',
                    default => 'submitted',
                },
                'idempotency_key' => $idempotencyKey,
                'result' => $result,
            ];
        } catch (LearningIdempotencyConflict $exception) {
            $payload = [
                'outcome' => 'rejected',
                'idempotency_key' => $idempotencyKey,
                'rejection_class' => $exception::class,
            ];
        } catch (LearningOperationRejected $exception) {
            $payload = [
                'outcome' => 'operation_rejected',
                'idempotency_key' => $idempotencyKey,
                'rejection_class' => $exception::class,
            ];
        } catch (Throwable $exception) {
            $this->writeWorkerResult($pipe, [
                'outcome' => 'error',
                'worker_exception' => $exception::class,
                'worker_message' => $exception->getMessage(),
            ]);

            exit(1);
        }

        $this->writeWorkerResult($pipe, $payload);

        exit(0);
    }

    /**
     * @return array{pid: int, pipe: resource, reaped: bool}
     */
    private function spawnPublicationWorker(
        string $organizationId,
        string $actorUserId,
        string $actorMembershipId,
        string $curriculumVersionId,
        string $idempotencyKey,
        ?string $activationAt = null,
        ?string $rollbackProgramId = null,
        ?string $rollbackTargetPublicationId = null,
        int $expectedHeadVersion = 0,
    ): array {
        $sockets = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );

        if ($sockets === false) {
            throw new RuntimeException(
                'The concurrent publication worker pipe could not be created.',
            );
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);

            throw new RuntimeException(
                'The concurrent publication worker could not be forked.',
            );
        }

        if ($pid === 0) {
            fclose($sockets[0]);
            $this->runPublicationWorker(
                pipe: $sockets[1],
                organizationId: $organizationId,
                actorUserId: $actorUserId,
                actorMembershipId: $actorMembershipId,
                curriculumVersionId: $curriculumVersionId,
                idempotencyKey: $idempotencyKey,
                activationAt: $activationAt,
                rollbackProgramId: $rollbackProgramId,
                rollbackTargetPublicationId: $rollbackTargetPublicationId,
                expectedHeadVersion: $expectedHeadVersion,
            );
        }

        fclose($sockets[1]);

        return [
            'pid' => $pid,
            'pipe' => $sockets[0],
            'reaped' => false,
        ];
    }

    /**
     * @param  resource  $pipe
     */
    private function runPublicationWorker(
        mixed $pipe,
        string $organizationId,
        string $actorUserId,
        string $actorMembershipId,
        string $curriculumVersionId,
        string $idempotencyKey,
        ?string $activationAt,
        ?string $rollbackProgramId,
        ?string $rollbackTargetPublicationId,
        int $expectedHeadVersion,
    ): never {
        try {
            DB::purge();
            DB::reconnect();

            fwrite($pipe, "ready\n");
            fflush($pipe);

            if (fgets($pipe) !== "go\n") {
                throw new RuntimeException(
                    'The publication worker received an invalid release signal.',
                );
            }

            $actor = User::query()->findOrFail($actorUserId);
            $context = new OrganizationContext(
                organizationId: $organizationId,
                actorUserId: $actorUserId,
                membershipId: $actorMembershipId,
                source: OrganizationContextSource::Membership,
            );
            $result = $this->app
                ->make(OrganizationContextManager::class)
                ->run(
                    $context,
                    function () use (
                        $activationAt,
                        $actor,
                        $curriculumVersionId,
                        $idempotencyKey,
                        $rollbackProgramId,
                        $rollbackTargetPublicationId,
                        $expectedHeadVersion,
                    ) {
                        if (
                            $rollbackProgramId !== null
                            && $rollbackTargetPublicationId !== null
                        ) {
                            return $this->app
                                ->make(CurriculumRollbackService::class)
                                ->rollback(
                                    actor: $actor,
                                    programId: $rollbackProgramId,
                                    targetPublicationId: $rollbackTargetPublicationId,
                                    expectedHeadVersion: $expectedHeadVersion,
                                    idempotencyKey: $idempotencyKey,
                                    reasonKey: 'concurrent_rollback',
                                );
                        }

                        if ($activationAt === null) {
                            return $this->app
                                ->make(CurriculumPublisher::class)
                                ->publish(
                                    actor: $actor,
                                    curriculumVersionId: $curriculumVersionId,
                                    expectedHeadVersion: $expectedHeadVersion,
                                    idempotencyKey: $idempotencyKey,
                                );
                        }

                        return $this->app
                            ->make(CurriculumPublicationScheduler::class)
                            ->schedule(
                                actor: $actor,
                                curriculumVersionId: $curriculumVersionId,
                                activationAt: CarbonImmutable::parse(
                                    $activationAt,
                                ),
                                expectedHeadVersion: $expectedHeadVersion,
                                idempotencyKey: $idempotencyKey,
                            );
                    },
                );
            $payload = [
                'outcome' => $rollbackProgramId !== null
                    ? 'rolled_back'
                    : ($activationAt === null ? 'published' : 'scheduled'),
                'publication_id' => $result->publicationId,
                'head_version' => $result->headVersion,
            ];
        } catch (CurriculumOperationRejected) {
            $payload = [
                'outcome' => 'rejected',
            ];
        } catch (Throwable $exception) {
            $this->writeWorkerResult($pipe, [
                'outcome' => 'error',
                'worker_exception' => $exception::class,
            ]);

            exit(1);
        }

        $this->writeWorkerResult($pipe, $payload);

        exit(0);
    }

    /**
     * @param  resource  $pipe
     * @param  array<string, mixed>  $payload
     */
    private function writeWorkerResult(mixed $pipe, array $payload): void
    {
        if (is_resource($pipe)) {
            fwrite(
                $pipe,
                json_encode($payload, JSON_THROW_ON_ERROR)."\n",
            );
            fflush($pipe);
            fclose($pipe);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function openPostgreSqlConnection(array $config): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            (string) ($config['host'] ?? '127.0.0.1'),
            (string) ($config['port'] ?? '5432'),
            (string) ($config['database'] ?? ''),
            (string) ($config['sslmode'] ?? 'prefer'),
        );

        return new PDO(
            $dsn,
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
    }

    /**
     * @param  list<int>  $backendPids
     */
    private function waitUntilLearningWorkersBlock(
        PDO $connection,
        array $backendPids,
    ): bool {
        if (count($backendPids) !== 2) {
            throw new RuntimeException(
                'Exactly two learning worker backend PIDs are required.',
            );
        }

        $waiting = $connection->prepare(
            <<<'SQL'
                select count(*)
                from pg_stat_activity
                where pid in (:first_pid, :second_pid)
                  and wait_event_type = 'Lock'
                SQL,
        );

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $connection->exec('select pg_stat_clear_snapshot()');
            $waiting->execute([
                'first_pid' => $backendPids[0],
                'second_pid' => $backendPids[1],
            ]);

            if ((int) $waiting->fetchColumn() === 2) {
                return true;
            }

            usleep(10_000);
        }

        return false;
    }

    private function waitUntilBothWorkersBlock(PDO $connection): bool
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $connection->exec('select pg_stat_clear_snapshot()');
            $waiting = $connection->query(
                <<<'SQL'
                    select count(*)
                    from pg_stat_activity
                    where pid <> pg_backend_pid()
                      and datname = current_database()
                      and wait_event_type = 'Lock'
                      and (
                        position('curriculum_versions' in query) > 0
                        or position('programs' in query) > 0
                        or position('membership_roles' in query) > 0
                        or position('role_permissions' in query) > 0
                      )
                    SQL,
            );

            if ($waiting === false) {
                throw new RuntimeException(
                    'PostgreSQL lock state could not be inspected.',
                );
            }

            if ((int) $waiting->fetchColumn() >= 2) {
                return true;
            }

            usleep(10_000);
        }

        return false;
    }
}

final readonly class ConcurrentFixedDailyMissionReader implements DailyMissionReader
{
    public function __construct(
        private DailyMissionDefinition $mission,
    ) {}

    public function resolve(
        User $learner,
        string $enrollmentId,
        string $missionId,
    ): DailyMissionDefinition {
        if (
            $enrollmentId !== $this->mission->enrollmentId
            || $missionId !== $this->mission->missionId
        ) {
            throw new LearningOperationRejected;
        }

        return $this->mission;
    }

    public function first(
        User $learner,
        string $enrollmentId,
    ): ?DailyMissionDefinition {
        return $enrollmentId === $this->mission->enrollmentId
            ? $this->mission
            : null;
    }
}
