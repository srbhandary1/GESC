<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Password\PasswordPolicyViolation;
use App\Authentication\Password\PasswordRecoveryFailed;
use App\Authentication\Password\PasswordRecoveryService;
use App\Authentication\Password\RecoveryTokenDigester;
use App\Models\PasswordRecoveryChallenge;
use App\Models\User;
use App\Models\UserIdentity;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/** @phpstan-type Worker array{pid: int, pipe: resource, reaped: bool} */
final class ConcurrentAccountSetupTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The account-setup race gate requires pcntl.',
        );
        $this->seed(FoundationSeeder::class);
    }

    public function test_two_concurrent_establishment_submissions_have_exactly_one_winner(): void
    {
        [$targetId, $challengeId, $token] = $this->fixture();
        $databaseConfig = DB::connection()->getConfig();
        DB::disconnect();
        /** @var list<Worker> $workers */
        $workers = [];
        /** @var list<int> $backendPids */
        $backendPids = [];
        /** @var list<string> $outcomes */
        $outcomes = [];
        $lockConnection = null;
        $bothBlocked = false;

        try {
            $workers[] = $this->spawnWorker(
                $challengeId,
                $token,
                'concurrent.first',
                'First concurrent recovery battery 2026!',
            );
            $workers[] = $this->spawnWorker(
                $challengeId,
                $token,
                'concurrent.second',
                'Second concurrent recovery battery 2026!',
            );

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 20);
                $ready = fgets($worker['pipe']);

                if (! is_string($ready)
                    || preg_match('/^ready:(\d+)\n$/', $ready, $matches) !== 1) {
                    throw new RuntimeException('A recovery worker did not become ready.');
                }

                $backendPids[] = (int) $matches[1];
            }

            $lockConnection = $this->openPostgreSqlConnection($databaseConfig);
            $lockConnection->beginTransaction();
            $lock = $lockConnection->prepare(
                'select id from password_recovery_challenges where id = :id for update',
            );
            $lock->execute(['id' => $challengeId]);

            if ($lock->fetchColumn() === false) {
                throw new RuntimeException('The recovery race challenge is missing.');
            }

            foreach ($workers as $worker) {
                if (fwrite($worker['pipe'], "go\n") === false) {
                    throw new RuntimeException('A recovery worker could not be released.');
                }

                fflush($worker['pipe']);
            }

            $bothBlocked = $this->waitUntilWorkersBlock($lockConnection, $backendPids);
            $lockConnection->commit();

            foreach ($workers as $index => $worker) {
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException('A recovery worker returned no result.');
                }

                /** @var array{outcome?: mixed, worker_exception?: mixed} $result */
                $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $outcome = $result['outcome'] ?? null;

                if (! is_string($outcome)) {
                    throw new RuntimeException('A recovery worker returned invalid evidence.');
                }

                if ($outcome === 'error') {
                    $exception = $result['worker_exception'] ?? 'unknown';
                    throw new RuntimeException(sprintf(
                        'A recovery worker failed with %s.',
                        is_string($exception) ? $exception : 'unknown',
                    ));
                }

                $outcomes[] = $outcome;
                $waited = pcntl_waitpid($worker['pid'], $processStatus);

                if ($waited !== $worker['pid'] || pcntl_wexitstatus($processStatus) !== 0) {
                    throw new RuntimeException('A recovery worker exited unsuccessfully.');
                }

                $workers[$index]['reaped'] = true;
            }
        } finally {
            if ($lockConnection instanceof PDO && $lockConnection->inTransaction()) {
                $lockConnection->rollBack();
            }

            foreach ($workers as $index => $worker) {
                if (is_resource($worker['pipe'])) {
                    fclose($worker['pipe']);
                }

                if ($worker['reaped']) {
                    continue;
                }

                $waited = pcntl_waitpid($worker['pid'], $processStatus, WNOHANG);

                if ($waited === 0 && function_exists('posix_kill')) {
                    posix_kill($worker['pid'], SIGKILL);
                    pcntl_waitpid($worker['pid'], $processStatus);
                }

                $workers[$index]['reaped'] = true;
            }

            DB::purge();
            DB::reconnect();
        }

        sort($outcomes);
        self::assertTrue($bothBlocked, 'Both workers must enter the PostgreSQL lock queue.');
        self::assertSame(['established', 'rejected'], $outcomes);
        self::assertContains(
            User::query()->findOrFail($targetId)->username,
            ['concurrent.first', 'concurrent.second'],
        );
        self::assertSame(1, DB::table('password_credentials')->where('user_id', $targetId)->count());
        self::assertSame(
            PasswordRecoveryChallenge::STATUS_CONSUMED,
            PasswordRecoveryChallenge::query()->findOrFail($challengeId)->status,
        );
        self::assertSame(
            1,
            DB::table('password_security_events')
                ->where('user_id', $targetId)
                ->where('event_type', 'password.account_setup_completed')
                ->count(),
        );
    }

    /** @return array{string, string, string} */
    private function fixture(): array
    {
        $now = now();
        /** @var User $actor */
        $actor = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $request = Request::create('/internal/password-establishment', 'POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $this->app->make(PasswordCredentialService::class)
            ->establish($actor, 'race.actor', 'Race actor correct battery 2026!', $request);
        /** @var User $target */
        $target = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        /** @var UserIdentity $identity */
        $identity = UserIdentity::query()->forceCreate([
            'user_id' => (string) $target->getKey(),
            'type' => 'email',
            'identifier_digest' => hash('sha256', 'race@example.test'),
            'display_hint' => 'r***@example.test',
            'status' => UserIdentity::STATUS_VERIFIED,
            'verified_at' => $now,
        ]);
        DB::table('password_authentication_cutovers')->insert([
            'scope_key' => 'global',
            'status' => 'password_only',
            'manifest_digest' => str_repeat('a', 64),
            'evidence_digest' => str_repeat('b', 64),
            'active_user_count' => 2,
            'credential_ready_count' => 1,
            'excluded_user_count' => 1,
            'completed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('password_cutover_exclusions')->insert([
            'user_id' => (string) $target->getKey(),
            'cutover_scope_key' => 'global',
            'reason_key' => 'cutover_excluded.concurrent_fixture',
            'owner_user_id' => (string) $actor->getKey(),
            'manifest_digest' => str_repeat('a', 64),
            'reconciled_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $managementEventId = (string) Str::ulid();
        DB::table('account_recovery_management_events')->insert([
            'id' => $managementEventId,
            'target_user_id' => (string) $target->getKey(),
            'actor_user_id' => (string) $actor->getKey(),
            'action' => 'credential_setup_requested',
            'reason_key' => 'account_recovery.migration_setup',
            'evidence_digest' => str_repeat('c', 64),
            'prior_identity_id' => null,
            'replacement_identity_id' => null,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $challengeId = (string) Str::ulid();
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $digester = $this->app->make(RecoveryTokenDigester::class);
        PasswordRecoveryChallenge::query()->forceCreate([
            'id' => $challengeId,
            'user_id' => (string) $target->getKey(),
            'recovery_identity_id' => (string) $identity->getKey(),
            'purpose' => PasswordRecoveryChallenge::PURPOSE_ACCOUNT_SETUP,
            'privileged_recovery_case_id' => null,
            'account_recovery_management_event_id' => $managementEventId,
            'lookup_digest' => $digester->digest(
                'password-establishment-lookup',
                (string) $target->getKey(),
            ),
            'token_digest' => $digester->digest('password-recovery-token', $token),
            'status' => PasswordRecoveryChallenge::STATUS_PENDING,
            'failed_attempts' => 0,
            'delivery_outcome' => 'accepted',
            'expires_at' => $now->copy()->addMinutes(15),
        ]);

        return [(string) $target->getKey(), $challengeId, $token];
    }

    /** @return Worker */
    private function spawnWorker(
        string $challengeId,
        string $token,
        string $username,
        string $password,
    ): array {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($sockets === false) {
            throw new RuntimeException('The recovery worker pipe could not be created.');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);
            throw new RuntimeException('The recovery worker could not be forked.');
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
                    throw new RuntimeException('The recovery worker has no backend PID.');
                }

                fwrite($pipe, 'ready:'.$backendPid."\n");
                fflush($pipe);

                if (fgets($pipe) !== "go\n") {
                    throw new RuntimeException('The recovery worker was not released.');
                }

                $request = Request::create('/auth/password/setups', 'POST');
                $request->server->set('REMOTE_ADDR', '127.0.0.1');

                try {
                    $this->app->make(PasswordRecoveryService::class)
                        ->consumeAccountSetup(
                            $challengeId,
                            $username,
                            $token,
                            $password,
                            $request,
                        );
                    $result = ['outcome' => 'established'];
                } catch (PasswordRecoveryFailed|PasswordPolicyViolation) {
                    $result = ['outcome' => 'rejected'];
                }
            } catch (Throwable $exception) {
                $result = ['outcome' => 'error', 'worker_exception' => $exception::class];
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
        return new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                (string) ($config['host'] ?? '127.0.0.1'),
                (string) ($config['port'] ?? '5432'),
                (string) ($config['database'] ?? ''),
            ),
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    /** @param list<int> $backendPids */
    private function waitUntilWorkersBlock(PDO $connection, array $backendPids): bool
    {
        if (count($backendPids) !== 2) {
            throw new RuntimeException('Exactly two recovery backend PIDs are required.');
        }

        $statement = $connection->prepare(
            'select count(*) from pg_stat_activity where pid in (:first, :second) and wait_event_type = \'Lock\'',
        );
        $deadline = microtime(true) + 10;

        do {
            $statement->execute(['first' => $backendPids[0], 'second' => $backendPids[1]]);

            if ((int) $statement->fetchColumn() === 2) {
                return true;
            }

            usleep(25_000);
        } while (microtime(true) < $deadline);

        return false;
    }
}
