<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Identity\Exceptions\LocalOperatorIdentityBootstrapRejected;
use App\Identity\LocalOperatorIdentityBootstrapper;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * @phpstan-type Worker array{pid: int, pipe: resource, reaped: bool}
 */
final class ConcurrentLocalOperatorIdentityBootstrapTest extends TestCase
{
    use DatabaseTruncation;

    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The local identity bootstrap race gate requires pcntl.',
        );
        $this->originalEnvironment = $this->app->environment();
        $this->app['env'] = 'local';
        config([
            'authentication.otp.driver' => 'local',
            'authentication.identity.local_operator_bootstrap_enabled' => true,
        ]);
        $this->seed(FoundationSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->app['env'] = $this->originalEnvironment;

        parent::tearDown();
    }

    public function test_two_workers_have_exactly_one_identity_binding_winner(): void
    {
        [$userId, $membershipId] = $this->createDirectAdministrator();
        $phoneFile = $this->protectedInput($this->syntheticPhone());
        $databaseConfig = DB::connection()->getConfig();
        DB::disconnect();

        /** @var list<Worker> $workers */
        $workers = [];
        /** @var list<int> $backendPids */
        $backendPids = [];
        /** @var list<string> $outcomes */
        $outcomes = [];
        $lockConnection = null;
        $bothWorkersBlocked = false;

        try {
            $workers[] = $this->spawnWorker(
                $phoneFile,
                $userId,
                $membershipId,
            );
            $workers[] = $this->spawnWorker(
                $phoneFile,
                $userId,
                $membershipId,
            );

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 20);
                $ready = fgets($worker['pipe']);

                if (
                    ! is_string($ready)
                    || preg_match('/^ready:(\d+)\n$/', $ready, $matches) !== 1
                ) {
                    throw new RuntimeException(
                        'A local identity bootstrap worker did not become ready.',
                    );
                }

                $backendPids[] = (int) $matches[1];
            }

            $lockConnection = $this->openPostgreSqlConnection($databaseConfig);
            $lockConnection->beginTransaction();
            $lock = $lockConnection->prepare(
                'select id from users where id = :id for update',
            );
            $lock->execute(['id' => $userId]);

            if ($lock->fetchColumn() === false) {
                throw new RuntimeException(
                    'The local identity bootstrap race user is missing.',
                );
            }

            foreach ($workers as $worker) {
                if (fwrite($worker['pipe'], "go\n") === false) {
                    throw new RuntimeException(
                        'A local identity bootstrap worker could not be released.',
                    );
                }

                fflush($worker['pipe']);
            }

            $bothWorkersBlocked = $this->waitUntilWorkersBlock(
                $lockConnection,
                $backendPids,
            );
            $lockConnection->commit();

            foreach ($workers as $index => $worker) {
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException(
                        'A local identity bootstrap worker returned no result.',
                    );
                }

                /** @var array{outcome?: mixed, worker_exception?: mixed} $result */
                $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $outcome = $result['outcome'] ?? null;

                if (! is_string($outcome)) {
                    throw new RuntimeException(
                        'A local identity bootstrap worker returned invalid evidence.',
                    );
                }

                if ($outcome === 'error') {
                    $exception = $result['worker_exception'] ?? 'unknown';
                    throw new RuntimeException(sprintf(
                        'A local identity bootstrap worker failed with %s.',
                        is_string($exception) ? $exception : 'unknown',
                    ));
                }

                $outcomes[] = $outcome;
                $waited = pcntl_waitpid($worker['pid'], $processStatus);

                if (
                    $waited !== $worker['pid']
                    || pcntl_wexitstatus($processStatus) !== 0
                ) {
                    throw new RuntimeException(
                        'A local identity bootstrap worker exited unsuccessfully.',
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

            @unlink($phoneFile);
            DB::purge();
            DB::reconnect();
        }

        sort($outcomes);
        self::assertTrue(
            $bothWorkersBlocked,
            'Both workers must enter the PostgreSQL lock queue.',
        );
        self::assertSame(['bound', 'rejected'], $outcomes);
        self::assertSame(
            1,
            DB::table('user_identities')
                ->where('user_id', $userId)
                ->where('type', 'phone')
                ->where('status', 'verified')
                ->count(),
        );
        $this->assertDatabaseCount('user_identities', 1);
    }

    /** @return Worker */
    private function spawnWorker(
        string $phoneFile,
        string $userId,
        string $membershipId,
    ): array {
        $sockets = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );

        if ($sockets === false) {
            throw new RuntimeException(
                'The local identity bootstrap worker pipe could not be created.',
            );
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);
            throw new RuntimeException(
                'The local identity bootstrap worker could not be forked.',
            );
        }

        if ($pid === 0) {
            fclose($sockets[0]);
            $pipe = $sockets[1];
            $exitCode = 0;

            try {
                DB::purge();
                DB::reconnect();
                $backend = DB::selectOne('select pg_backend_pid() as pid');
                $backendPid = is_object($backend) ? $backend->pid ?? null : null;

                if (! is_int($backendPid)) {
                    throw new RuntimeException(
                        'The local identity bootstrap worker has no backend PID.',
                    );
                }

                fwrite($pipe, 'ready:'.$backendPid."\n");
                fflush($pipe);

                if (fgets($pipe) !== "go\n") {
                    throw new RuntimeException(
                        'The local identity bootstrap worker was not released.',
                    );
                }

                try {
                    $this->app
                        ->make(LocalOperatorIdentityBootstrapper::class)
                        ->bootstrap(
                            phoneFile: $phoneFile,
                            expectedUserId: $userId,
                            expectedMembershipId: $membershipId,
                            approvalReference: 'APP-0049',
                        );
                    $result = ['outcome' => 'bound'];
                } catch (LocalOperatorIdentityBootstrapRejected) {
                    $result = ['outcome' => 'rejected'];
                }
            } catch (Throwable $exception) {
                $result = [
                    'outcome' => 'error',
                    'worker_exception' => $exception::class,
                ];
                $exitCode = 1;
            }

            fwrite($pipe, json_encode($result, JSON_THROW_ON_ERROR)."\n");
            fflush($pipe);
            fclose($pipe);
            exit($exitCode);
        }

        fclose($sockets[1]);

        return ['pid' => $pid, 'pipe' => $sockets[0], 'reaped' => false];
    }

    /** @param array<string, mixed> $config */
    private function openPostgreSqlConnection(array $config): PDO
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '5432');
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $connection = new PDO(
            "pgsql:host={$host};port={$port};dbname={$database}",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        return $connection;
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
        $deadline = microtime(true) + 10;

        do {
            $statement->execute([
                'first_pid' => $backendPids[0],
                'second_pid' => $backendPids[1],
            ]);

            if ((int) $statement->fetchColumn() === 2) {
                return true;
            }

            usleep(25_000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /** @return array{string, string} */
    private function createDirectAdministrator(): array
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->sole();
        $roleId = DB::table('roles')
            ->where('organization_id', $organization->getKey())
            ->where('key', 'organization_admin')
            ->value('id');

        if (! is_string($roleId)) {
            throw new RuntimeException(
                'The seeded Direct administrator role is missing.',
            );
        }
        $user = User::query()->forceCreate([
            'display_name' => null,
            'status' => User::STATUS_ACTIVE,
        ]);
        $membershipId = (string) Str::ulid();
        $now = now();
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'membership_type' => Membership::TYPE_ORGANIZATION_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => $now,
            'ended_at' => null,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membershipId,
            'role_id' => $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [(string) $user->getKey(), $membershipId];
    }

    private function protectedInput(string $value): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gse-bootstrap-race-');

        if (! is_string($path)) {
            throw new RuntimeException(
                'Could not allocate a protected concurrency input.',
            );
        }

        chmod($path, 0600);
        file_put_contents($path, $value."\n");

        return $path;
    }

    private function syntheticPhone(): string
    {
        return '+'.'91'.'9100000049';
    }
}
