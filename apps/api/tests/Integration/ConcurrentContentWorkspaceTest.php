<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Governance\ContentReviewCommands;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Operator\ContentWorkspace\ContentWorkspaceCommands;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceConflict;
use App\Operator\ContentWorkspace\Exceptions\ContentWorkspaceRejected;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Closure;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class ConcurrentContentWorkspaceTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The content-workspace concurrency gate requires pcntl.',
        );
        $this->seed(FoundationSeeder::class);
    }

    public function test_graph_source_and_variant_heads_serialize_forced_overlaps(): void
    {
        $fixture = $this->makeWorkspaceFixture();
        $graphRace = $this->runWorkspaceRace(
            $fixture,
            [
                [
                    'operation' => 'graph_save',
                    'payload' => [
                        'expected_workspace_version' => 2,
                        'graph' => $this->draftGraph('alpha'),
                        'idempotency_key' => 'workspace-graph-race-alpha',
                    ],
                ],
                [
                    'operation' => 'graph_save',
                    'payload' => [
                        'expected_workspace_version' => 2,
                        'graph' => $this->draftGraph('beta'),
                        'idempotency_key' => 'workspace-graph-race-beta',
                    ],
                ],
            ],
            function (PDO $connection) use ($fixture): void {
                $statement = $connection->prepare(
                    <<<'SQL'
                        select curriculum_version_id
                        from curriculum_workspaces
                        where organization_id = :organization_id
                          and curriculum_version_id = :curriculum_version_id
                        for update
                        SQL,
                );
                $statement->execute([
                    'organization_id' => $fixture['organization_id'],
                    'curriculum_version_id' => $fixture['curriculum_version_id'],
                ]);
            },
        );

        self::assertTrue($graphRace['both_blocked']);
        self::assertSame(
            ['conflict', 'saved'],
            collect($graphRace['results'])
                ->pluck('outcome')
                ->sort()
                ->values()
                ->all(),
        );
        self::assertSame(
            3,
            DB::table('curriculum_workspaces')
                ->where('organization_id', $fixture['organization_id'])
                ->where(
                    'curriculum_version_id',
                    $fixture['curriculum_version_id'],
                )
                ->value('version'),
        );
        self::assertSame(
            2,
            DB::table('curriculum_draft_graph_revisions')
                ->where('organization_id', $fixture['organization_id'])
                ->where(
                    'curriculum_version_id',
                    $fixture['curriculum_version_id'],
                )
                ->count(),
        );

        $englishRace = $this->runWorkspaceRace(
            $fixture,
            [
                [
                    'operation' => 'english_append',
                    'payload' => [
                        'expected_head_revision_id' => $fixture['source_revision_id'],
                        'expected_revision_number' => 1,
                        'text' => 'First concurrent English correction.',
                        'idempotency_key' => 'workspace-source-race-first',
                    ],
                ],
                [
                    'operation' => 'english_append',
                    'payload' => [
                        'expected_head_revision_id' => $fixture['source_revision_id'],
                        'expected_revision_number' => 1,
                        'text' => 'Second concurrent English correction.',
                        'idempotency_key' => 'workspace-source-race-second',
                    ],
                ],
            ],
            fn (PDO $connection) => $this->lockWorkspace(
                $connection,
                $fixture['organization_id'],
                $fixture['curriculum_version_id'],
            ),
        );

        self::assertTrue($englishRace['both_blocked']);
        self::assertSame(
            ['appended', 'conflict'],
            collect($englishRace['results'])
                ->pluck('outcome')
                ->sort()
                ->values()
                ->all(),
        );
        $sourceHead = DB::table('content_revision_heads as heads')
            ->join('content_revisions as revisions', function ($join): void {
                $join->on('revisions.id', '=', 'heads.content_revision_id');
            })
            ->where('heads.organization_id', $fixture['organization_id'])
            ->where('heads.content_document_id', $fixture['content_document_id'])
            ->sole([
                'revisions.id',
                'revisions.content_hash',
                'revisions.revision_number',
            ]);
        self::assertSame(2, $sourceHead->revision_number);
        self::assertSame(
            2,
            DB::table('content_revisions')
                ->where('organization_id', $fixture['organization_id'])
                ->where('content_document_id', $fixture['content_document_id'])
                ->count(),
        );

        $variantFixture = [
            ...$fixture,
            'source_revision_id' => (string) $sourceHead->id,
            'source_content_hash' => (string) $sourceHead->content_hash,
            'source_revision_number' => 2,
        ];
        $variantRace = $this->runWorkspaceRace(
            $variantFixture,
            [
                [
                    'operation' => 'kannada_append',
                    'payload' => [
                        'text' => 'ಮೊದಲ ಸಮಕಾಲೀನ ಕನ್ನಡ ತಿದ್ದುಪಡಿ.',
                        'idempotency_key' => 'workspace-variant-race-first',
                    ],
                ],
                [
                    'operation' => 'kannada_append',
                    'payload' => [
                        'text' => 'ಎರಡನೇ ಸಮಕಾಲೀನ ಕನ್ನಡ ತಿದ್ದುಪಡಿ.',
                        'idempotency_key' => 'workspace-variant-race-second',
                    ],
                ],
            ],
            fn (PDO $connection) => $this->lockWorkspace(
                $connection,
                $fixture['organization_id'],
                $fixture['curriculum_version_id'],
            ),
        );

        self::assertTrue($variantRace['both_blocked']);
        self::assertSame(
            ['conflict', 'translated'],
            collect($variantRace['results'])
                ->pluck('outcome')
                ->sort()
                ->values()
                ->all(),
        );
        self::assertSame(
            1,
            DB::table('localized_variants')
                ->where('organization_id', $fixture['organization_id'])
                ->where('source_revision_id', $sourceHead->id)
                ->count(),
        );
    }

    public function test_structure_submission_and_graph_edit_have_one_coherent_winner(): void
    {
        $fixture = $this->makeWorkspaceFixture(completeGraph: true);
        $race = $this->runWorkspaceRace(
            $fixture,
            [
                [
                    'operation' => 'review_submit',
                    'payload' => [
                        'expected_workspace_version' => 3,
                        'idempotency_key' => 'workspace-review-race-submit',
                    ],
                ],
                [
                    'operation' => 'graph_save',
                    'payload' => [
                        'expected_workspace_version' => 3,
                        'graph' => $this->completeGraph('revision'),
                        'idempotency_key' => 'workspace-review-race-edit',
                    ],
                ],
            ],
            fn (PDO $connection) => $this->lockWorkspace(
                $connection,
                $fixture['organization_id'],
                $fixture['curriculum_version_id'],
            ),
        );

        self::assertTrue($race['both_blocked']);
        self::assertContains(
            collect($race['results'])->pluck('outcome')->sort()->values()->all(),
            [
                ['conflict', 'saved'],
                ['conflict', 'submitted'],
            ],
        );
        $workspace = DB::table('curriculum_workspaces')
            ->where('organization_id', $fixture['organization_id'])
            ->where(
                'curriculum_version_id',
                $fixture['curriculum_version_id'],
            )
            ->sole(['state', 'version', 'submitted_graph_revision_id']);
        self::assertSame(4, $workspace->version);

        if ($workspace->state === 'in_review') {
            self::assertIsString($workspace->submitted_graph_revision_id);
            self::assertSame(
                1,
                DB::table('curriculum_graph_revisions')
                    ->where('organization_id', $fixture['organization_id'])
                    ->where(
                        'curriculum_version_id',
                        $fixture['curriculum_version_id'],
                    )
                    ->count(),
            );
        } else {
            self::assertSame('draft', $workspace->state);
            self::assertNull($workspace->submitted_graph_revision_id);
        }
    }

    public function test_exact_review_holds_appointment_against_invalidation(): void
    {
        $fixture = $this->makeWorkspaceFixture();
        $reviewer = $this->createUserAndMembership(
            $fixture['organization_id'],
            Membership::TYPE_TRAINER,
        );
        $this->grantPermissions(
            $fixture['organization_id'],
            $reviewer['membership_id'],
            ['curriculum.review.en'],
            'content-race-reviewer',
        );
        $appointmentId = $this->insertAcceptedAppointment(
            organizationId: $fixture['organization_id'],
            programId: $fixture['program_id'],
            appointeeUserId: $reviewer['user_id'],
            appointeeMembershipId: $reviewer['membership_id'],
            appointedByMembershipId: $fixture['actor_membership_id'],
        );
        $reviewerActor = User::query()->findOrFail($reviewer['user_id']);
        $reviewerContext = new OrganizationContext(
            organizationId: $fixture['organization_id'],
            actorUserId: $reviewer['user_id'],
            membershipId: $reviewer['membership_id'],
            source: OrganizationContextSource::Membership,
        );
        $invalidation = $this->openPostgreSqlConnection(
            DB::connection()->getConfig(),
        );
        $invalidation->exec("set lock_timeout = '250ms'");
        DB::beginTransaction();

        try {
            $reviewId = $this->app
                ->make(OrganizationContextManager::class)
                ->run(
                    $reviewerContext,
                    fn (): string => $this->app
                        ->make(ContentReviewCommands::class)
                        ->reviewEnglish(
                            actor: $reviewerActor,
                            sourceRevisionId: $fixture['source_revision_id'],
                            sourceContentHash: $fixture['source_content_hash'],
                            authorityAppointmentId: $appointmentId,
                            decision: 'approved',
                            idempotencyKey: 'content-review-appointment-race',
                            rationaleKey: 'meets_standard',
                        ),
                );

            self::assertMatchesRegularExpression(
                '/\A[0-9A-HJKMNP-TV-Z]{26}\z/',
                $reviewId,
            );
            $statement = $invalidation->prepare(
                <<<'SQL'
                    insert into authority_appointment_events (
                        id,
                        organization_id,
                        authority_appointment_id,
                        event_type,
                        actor_membership_id,
                        reason_key,
                        invalidates_prior_approvals,
                        effective_at,
                        created_at,
                        updated_at
                    ) values (
                        :id,
                        :organization_id,
                        :authority_appointment_id,
                        'revoked',
                        :actor_membership_id,
                        'concurrent_invalidation',
                        false,
                        :effective_at,
                        :created_at,
                        :updated_at
                    )
                    SQL,
            );

            try {
                $now = now()->toISOString();
                $statement->execute([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $fixture['organization_id'],
                    'authority_appointment_id' => $appointmentId,
                    'actor_membership_id' => $fixture['actor_membership_id'],
                    'effective_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                self::fail(
                    'Appointment invalidation must wait for the exact review transaction.',
                );
            } catch (PDOException $exception) {
                self::assertSame('55P03', $exception->getCode());
            }
        } finally {
            DB::rollBack();
        }

        self::assertSame(
            0,
            DB::table('content_reviews')
                ->where('organization_id', $fixture['organization_id'])
                ->where('authority_appointment_id', $appointmentId)
                ->count(),
        );
    }

    /**
     * @return array{
     *   organization_id: string,
     *   actor_user_id: string,
     *   actor_membership_id: string,
     *   program_id: string,
     *   curriculum_version_id: string,
     *   content_document_id: string,
     *   source_revision_id: string,
     *   source_content_hash: string,
     *   source_revision_number: int
     * }
     */
    private function makeWorkspaceFixture(bool $completeGraph = false): array
    {
        $organizationId = $this->requiredStringValue(
            'organizations',
            'id',
            ['system_key' => Organization::DIRECT_SYSTEM_KEY],
        );
        $actor = $this->createUserAndMembership(
            $organizationId,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantPermissions(
            $organizationId,
            $actor['membership_id'],
            ['curriculum.author', 'curriculum.translate.kn'],
            'content-race-author',
        );
        $user = User::query()->findOrFail($actor['user_id']);
        $context = new OrganizationContext(
            organizationId: $organizationId,
            actorUserId: $actor['user_id'],
            membershipId: $actor['membership_id'],
            source: OrganizationContextSource::Membership,
        );
        $suffix = strtolower(substr((string) Str::ulid(), -10));
        $commands = $this->app->make(ContentWorkspaceCommands::class);

        return $this->app
            ->make(OrganizationContextManager::class)
            ->run($context, function () use (
                $actor,
                $commands,
                $completeGraph,
                $organizationId,
                $suffix,
                $user,
            ): array {
                $created = $commands->createProgram(
                    actor: $user,
                    programKey: 'content-race-'.$suffix,
                    internalName: 'Concurrent content workspace',
                    versionKey: 'draft-1',
                    expectedVersion: 0,
                    idempotencyKey: 'content-race-create-'.$suffix,
                );
                $programId = $this->resultString(
                    $created->data,
                    'program_id',
                );
                $curriculumVersionId = $this->resultString(
                    $created->data,
                    'curriculum_version_id',
                );
                $document = $commands->createDocument(
                    actor: $user,
                    curriculumVersionId: $curriculumVersionId,
                    documentKey: 'race-instruction',
                    contentType: 'instruction',
                    learnerVisible: true,
                    payload: [
                        'schema' => 'global-english.governed-text.v1',
                        'text' => 'Initial concurrent content instruction.',
                    ],
                    expectedWorkspaceVersion: 1,
                    idempotencyKey: 'content-race-document-'.$suffix,
                );
                $fixture = [
                    'organization_id' => $organizationId,
                    'actor_user_id' => $actor['user_id'],
                    'actor_membership_id' => $actor['membership_id'],
                    'program_id' => $programId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'content_document_id' => $this->resultString(
                        $document->data,
                        'content_document_id',
                    ),
                    'source_revision_id' => $this->resultString(
                        $document->data,
                        'source_revision_id',
                    ),
                    'source_content_hash' => $this->resultString(
                        $document->data,
                        'source_content_hash',
                    ),
                    'source_revision_number' => 1,
                ];

                if ($completeGraph) {
                    $commands->saveGraph(
                        actor: $user,
                        curriculumVersionId: $curriculumVersionId,
                        expectedWorkspaceVersion: 2,
                        graph: $this->completeGraph('initial'),
                        idempotencyKey: 'content-race-complete-graph-'.$suffix,
                    );
                }

                return $fixture;
            });
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  list<array{operation: string, payload: array<string, mixed>}>  $commands
     * @return array{both_blocked: bool, results: list<array<string, mixed>>}
     */
    private function runWorkspaceRace(
        array $fixture,
        array $commands,
        Closure $lock,
    ): array {
        $databaseConfig = DB::connection()->getConfig();
        DB::disconnect();

        /** @var list<array{pid: int, pipe: resource, reaped: bool}> $workers */
        $workers = [];
        $lockConnection = null;
        $backendPids = [];
        $results = [];
        $bothBlocked = false;

        try {
            foreach ($commands as $command) {
                $workers[] = $this->spawnWorkspaceWorker($fixture, $command);
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 10);
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException(
                        'A content-workspace worker did not become ready.',
                    );
                }

                $ready = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $backendPid = is_array($ready) ? ($ready['backend_pid'] ?? null) : null;

                if (! is_int($backendPid)) {
                    throw new RuntimeException(
                        'A content-workspace worker returned no backend PID.',
                    );
                }

                $backendPids[] = $backendPid;
            }

            $lockConnection = $this->openPostgreSqlConnection($databaseConfig);
            $lockConnection->beginTransaction();
            $lock($lockConnection);

            foreach ($workers as $worker) {
                fwrite($worker['pipe'], "go\n");
                fflush($worker['pipe']);
            }

            $bothBlocked = $this->waitUntilWorkersBlock(
                $lockConnection,
                $backendPids,
            );
            $lockConnection->commit();

            foreach ($workers as $index => $worker) {
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException(
                        'A content-workspace worker returned no result.',
                    );
                }

                $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

                if (! is_array($result)) {
                    throw new RuntimeException(
                        'A content-workspace worker returned an invalid result.',
                    );
                }

                $results[] = $result;
                $waited = pcntl_waitpid($worker['pid'], $status);

                if (
                    $waited !== $worker['pid']
                    || pcntl_wexitstatus($status) !== 0
                ) {
                    throw new RuntimeException(
                        'A content-workspace worker failed.',
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

                $waited = pcntl_waitpid($worker['pid'], $status, WNOHANG);

                if ($waited === 0 && function_exists('posix_kill')) {
                    posix_kill($worker['pid'], SIGKILL);
                    pcntl_waitpid($worker['pid'], $status);
                }

                $workers[$index]['reaped'] = true;
            }

            DB::purge();
            DB::reconnect();
        }

        return [
            'both_blocked' => $bothBlocked,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array{operation: string, payload: array<string, mixed>}  $command
     * @return array{pid: int, pipe: resource, reaped: bool}
     */
    private function spawnWorkspaceWorker(array $fixture, array $command): array
    {
        $sockets = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );

        if ($sockets === false) {
            throw new RuntimeException('The workspace worker pipe failed.');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);
            throw new RuntimeException('The workspace worker fork failed.');
        }

        if ($pid === 0) {
            fclose($sockets[0]);
            $this->runWorkspaceWorker($sockets[1], $fixture, $command);
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
     * @param  array<string, mixed>  $fixture
     * @param  array{operation: string, payload: array<string, mixed>}  $command
     */
    private function runWorkspaceWorker(
        mixed $pipe,
        array $fixture,
        array $command,
    ): never {
        try {
            DB::purge();
            DB::reconnect();
            $backend = DB::selectOne('select pg_backend_pid() as pid');
            $backendPid = is_object($backend) ? ($backend->pid ?? null) : null;

            if (! is_int($backendPid)) {
                throw new RuntimeException('The worker backend PID is missing.');
            }

            $this->writeWorkerResult($pipe, ['backend_pid' => $backendPid], false);

            if (fgets($pipe) !== "go\n") {
                throw new RuntimeException('The workspace release signal is invalid.');
            }

            $actorUserId = $this->arrayString($fixture, 'actor_user_id');
            $actor = User::query()->findOrFail($actorUserId);
            $context = new OrganizationContext(
                organizationId: $this->arrayString($fixture, 'organization_id'),
                actorUserId: $actorUserId,
                membershipId: $this->arrayString(
                    $fixture,
                    'actor_membership_id',
                ),
                source: OrganizationContextSource::Membership,
            );
            $result = $this->app
                ->make(OrganizationContextManager::class)
                ->run(
                    $context,
                    fn () => $this->executeWorkspaceCommand(
                        $actor,
                        $fixture,
                        $command,
                    ),
                );
            $payload = [
                'outcome' => match ($command['operation']) {
                    'graph_save' => 'saved',
                    'english_append' => 'appended',
                    'kannada_append' => 'translated',
                    'review_submit' => 'submitted',
                    default => throw new RuntimeException(
                        'The workspace operation is unsupported.',
                    ),
                },
                'result_id' => $result->resultId,
            ];
        } catch (ContentWorkspaceConflict|ContentWorkspaceRejected|CurriculumOperationRejected $exception) {
            $payload = [
                'outcome' => 'conflict',
                'exception' => $exception::class,
            ];
        } catch (Throwable $exception) {
            $this->writeWorkerResult($pipe, [
                'outcome' => 'error',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            exit(1);
        }

        $this->writeWorkerResult($pipe, $payload);
        exit(0);
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array{operation: string, payload: array<string, mixed>}  $command
     */
    private function executeWorkspaceCommand(
        User $actor,
        array $fixture,
        array $command,
    ): mixed {
        $payload = $command['payload'];
        $commands = $this->app->make(ContentWorkspaceCommands::class);

        return match ($command['operation']) {
            'graph_save' => $commands->saveGraph(
                actor: $actor,
                curriculumVersionId: $this->arrayString(
                    $fixture,
                    'curriculum_version_id',
                ),
                expectedWorkspaceVersion: $this->arrayInt(
                    $payload,
                    'expected_workspace_version',
                ),
                graph: $this->arrayObject($payload, 'graph'),
                idempotencyKey: $this->arrayString(
                    $payload,
                    'idempotency_key',
                ),
            ),
            'english_append' => $commands->appendEnglish(
                actor: $actor,
                contentDocumentId: $this->arrayString(
                    $fixture,
                    'content_document_id',
                ),
                expectedHeadRevisionId: $this->arrayString(
                    $payload,
                    'expected_head_revision_id',
                ),
                expectedRevisionNumber: $this->arrayInt(
                    $payload,
                    'expected_revision_number',
                ),
                contentType: 'instruction',
                learnerVisible: true,
                payload: [
                    'schema' => 'global-english.governed-text.v1',
                    'text' => $this->arrayString($payload, 'text'),
                ],
                idempotencyKey: $this->arrayString(
                    $payload,
                    'idempotency_key',
                ),
            ),
            'kannada_append' => $commands->appendKannada(
                actor: $actor,
                sourceRevisionId: $this->arrayString(
                    $fixture,
                    'source_revision_id',
                ),
                sourceContentHash: $this->arrayString(
                    $fixture,
                    'source_content_hash',
                ),
                expectedSourceRevisionNumber: $this->arrayInt(
                    $fixture,
                    'source_revision_number',
                ),
                expectedHeadVariantId: null,
                expectedVariantRevisionNumber: 0,
                payload: [
                    'schema' => 'global-english.governed-text.v1',
                    'text' => $this->arrayString($payload, 'text'),
                ],
                idempotencyKey: $this->arrayString(
                    $payload,
                    'idempotency_key',
                ),
            ),
            'review_submit' => $commands->submitGraphReview(
                actor: $actor,
                curriculumVersionId: $this->arrayString(
                    $fixture,
                    'curriculum_version_id',
                ),
                expectedWorkspaceVersion: $this->arrayInt(
                    $payload,
                    'expected_workspace_version',
                ),
                idempotencyKey: $this->arrayString(
                    $payload,
                    'idempotency_key',
                ),
            ),
            default => throw new RuntimeException(
                'The workspace operation is unsupported.',
            ),
        };
    }

    private function lockWorkspace(
        PDO $connection,
        string $organizationId,
        string $curriculumVersionId,
    ): void {
        $statement = $connection->prepare(
            <<<'SQL'
                select curriculum_version_id
                from curriculum_workspaces
                where organization_id = :organization_id
                  and curriculum_version_id = :curriculum_version_id
                for update
                SQL,
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
        ]);
    }

    /** @param list<int> $backendPids */
    private function waitUntilWorkersBlock(
        PDO $connection,
        array $backendPids,
    ): bool {
        if (count($backendPids) !== 2) {
            throw new RuntimeException('Exactly two backend PIDs are required.');
        }

        $statement = $connection->prepare(
            <<<'SQL'
                select count(*)
                from pg_stat_activity
                where pid in (:first_pid, :second_pid)
                  and wait_event_type = 'Lock'
                SQL,
        );

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $connection->exec('select pg_stat_clear_snapshot()');
            $statement->execute([
                'first_pid' => $backendPids[0],
                'second_pid' => $backendPids[1],
            ]);

            if ((int) $statement->fetchColumn() === 2) {
                return true;
            }

            usleep(10_000);
        }

        return false;
    }

    /**
     * @param  resource  $pipe
     * @param  array<string, mixed>  $payload
     */
    private function writeWorkerResult(
        mixed $pipe,
        array $payload,
        bool $close = true,
    ): void {
        if (! is_resource($pipe)) {
            return;
        }

        fwrite($pipe, json_encode($payload, JSON_THROW_ON_ERROR)."\n");
        fflush($pipe);

        if ($close) {
            fclose($pipe);
        }
    }

    /** @param array<string, mixed> $config */
    private function openPostgreSqlConnection(array $config): PDO
    {
        return new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                (string) ($config['host'] ?? '127.0.0.1'),
                (string) ($config['port'] ?? '5432'),
                (string) ($config['database'] ?? ''),
                (string) ($config['sslmode'] ?? 'prefer'),
            ),
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
    }

    /**
     * @return array{user_id: string, membership_id: string}
     */
    private function createUserAndMembership(
        string $organizationId,
        string $membershipType,
    ): array {
        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
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

    /** @param list<string> $permissionKeys */
    private function grantPermissions(
        string $organizationId,
        string $membershipId,
        array $permissionKeys,
        string $roleKey,
    ): void {
        $roleId = (string) Str::ulid();
        $now = now();
        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $organizationId,
            'key' => $roleKey.'-'.strtolower(substr($roleId, -8)),
            'name' => 'Synthetic content concurrency role',
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

        foreach ($permissionKeys as $permissionKey) {
            DB::table('role_permissions')->insert([
                'organization_id' => $organizationId,
                'role_id' => $roleId,
                'permission_id' => $this->requiredStringValue(
                    'permissions',
                    'id',
                    ['key' => $permissionKey],
                ),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function insertAcceptedAppointment(
        string $organizationId,
        string $programId,
        string $appointeeUserId,
        string $appointeeMembershipId,
        string $appointedByMembershipId,
    ): string {
        $appointmentId = (string) Str::ulid();
        $now = now();
        DB::table('authority_appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $organizationId,
            'appointee_user_id' => $appointeeUserId,
            'appointee_membership_id' => $appointeeMembershipId,
            'authority_kind' => 'english_academic_reviewer',
            'scope_type' => 'program',
            'program_id' => $programId,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'qualification_basis' => 'Synthetic content concurrency evidence',
            'approval_evidence_key' => 'APP-TEST-CONTENT-CONCURRENCY',
            'appointed_by_membership_id' => $appointedByMembershipId,
            'appointed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authority_appointment_events')->insert([
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'appointed',
                'actor_membership_id' => $appointedByMembershipId,
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => now()->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'authority_appointment_id' => $appointmentId,
                'event_type' => 'accepted',
                'actor_membership_id' => $appointeeMembershipId,
                'reason_key' => null,
                'invalidates_prior_approvals' => false,
                'effective_at' => now()->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return $appointmentId;
    }

    /** @return array<string, mixed> */
    private function draftGraph(string $suffix): array
    {
        return [
            'schema' => 'global-english.curriculum-draft-graph.v2',
            'levels' => [[
                'key' => 'level-'.$suffix,
                'sequence' => 1,
            ]],
            'objectives' => [],
            'modules' => [],
            'lessons' => [],
            'items' => [],
            'delivery_bundles' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function completeGraph(string $suffix): array
    {
        return [
            'schema' => 'global-english.curriculum-draft-graph.v2',
            'levels' => [['key' => 'level-'.$suffix, 'sequence' => 1]],
            'objectives' => [[
                'key' => 'objective-'.$suffix,
                'outcome_code' => 'CAN_COMPLETE_'.strtoupper($suffix),
                'sequence' => 1,
                'prerequisite_keys' => [],
            ]],
            'modules' => [[
                'key' => 'module-'.$suffix,
                'level_key' => 'level-'.$suffix,
                'sequence' => 1,
                'estimated_minutes' => 10,
            ]],
            'lessons' => [[
                'key' => 'lesson-'.$suffix,
                'module_key' => 'module-'.$suffix,
                'sequence' => 1,
                'objective_keys' => ['objective-'.$suffix],
            ]],
            'items' => [[
                'key' => 'item-'.$suffix,
                'lesson_key' => 'lesson-'.$suffix,
                'content_document_key' => 'race-instruction',
                'item_type' => 'instruction',
                'sequence' => 1,
            ]],
            'delivery_bundles' => [[
                'key' => 'bundle-'.$suffix,
                'instruction_document_key' => 'race-instruction',
                'sequence' => 1,
                'item_keys' => ['item-'.$suffix],
            ]],
        ];
    }

    /** @param array<string, mixed> $values */
    private function arrayString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value)) {
            throw new RuntimeException("The {$key} value must be a string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function arrayInt(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (! is_int($value)) {
            throw new RuntimeException("The {$key} value must be an integer.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function arrayObject(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        if (! is_array($value) || array_is_list($value)) {
            throw new RuntimeException("The {$key} value must be an object.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function resultString(array $values, string $key): string
    {
        return $this->arrayString($values, $key);
    }

    /** @param array<string, string> $where */
    private function requiredStringValue(
        string $table,
        string $column,
        array $where,
    ): string {
        $value = DB::table($table)->where($where)->value($column);

        if (! is_string($value)) {
            throw new RuntimeException("Unable to resolve {$table}.{$column}.");
        }

        return $value;
    }
}
