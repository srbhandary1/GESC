<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Curriculum\Publication\CurriculumPublicationScheduler;
use App\Curriculum\Publication\CurriculumPublisher;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class ConcurrentCurriculumPublicationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
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
     *   actor_user_id: string,
     *   actor_membership_id: string,
     *   program_id: string,
     *   curriculum_version_id: string
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
        ];
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
     * @return array{pid: int, pipe: resource, reaped: bool}
     */
    private function spawnPublicationWorker(
        string $organizationId,
        string $actorUserId,
        string $actorMembershipId,
        string $curriculumVersionId,
        string $idempotencyKey,
        ?string $activationAt = null,
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
                    ) {
                        if ($activationAt === null) {
                            return $this->app
                                ->make(CurriculumPublisher::class)
                                ->publish(
                                    actor: $actor,
                                    curriculumVersionId: $curriculumVersionId,
                                    expectedHeadVersion: 0,
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
                                expectedHeadVersion: 0,
                                idempotencyKey: $idempotencyKey,
                            );
                    },
                );
            $payload = [
                'outcome' => $activationAt === null
                    ? 'published'
                    : 'scheduled',
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
     * @param  array<string, int|string>  $payload
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
