<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\Commissioning\LocalLplPilotAuthorityBootstrapper;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
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
final class ConcurrentLocalLplPilotAuthorityBootstrapTest extends TestCase
{
    use DatabaseTruncation;

    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The local LPL authority race gate requires pcntl.',
        );
        $this->originalEnvironment = $this->app->environment();
        $this->app['env'] = 'local';
        config([
            'authentication.otp.driver' => 'local',
            'content_governance.local_lpl_pilot_authority_bootstrap_enabled' => true,
            'content_governance.consolidated_self_appointments.APP-0004.actor_user_id' => LocalLplPilotAuthorityBootstrapper::USER_ID,
        ]);
        $this->insertExactFoundation();
    }

    protected function tearDown(): void
    {
        $this->app['env'] = $this->originalEnvironment;

        parent::tearDown();
    }

    public function test_two_workers_create_one_exact_authority_graph_and_one_replay(): void
    {
        $idempotencyKey = (string) Str::ulid();
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
            $workers[] = $this->spawnWorker($idempotencyKey);
            $workers[] = $this->spawnWorker($idempotencyKey);

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 20);
                $ready = fgets($worker['pipe']);

                if (
                    ! is_string($ready)
                    || preg_match('/^ready:(\d+)\n$/', $ready, $matches) !== 1
                ) {
                    throw new RuntimeException(
                        'An LPL authority bootstrap worker did not become ready.',
                    );
                }

                $backendPids[] = (int) $matches[1];
            }

            $lockConnection = $this->openPostgreSqlConnection($databaseConfig);
            $lockConnection->beginTransaction();
            $lock = $lockConnection->prepare(
                'select id from organizations where id = :id for update',
            );
            $lock->execute([
                'id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            ]);

            if ($lock->fetchColumn() === false) {
                throw new RuntimeException('The exact Direct organization is missing.');
            }

            foreach ($workers as $worker) {
                if (fwrite($worker['pipe'], "go\n") === false) {
                    throw new RuntimeException(
                        'An LPL authority bootstrap worker could not be released.',
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
                        'An LPL authority bootstrap worker returned no result.',
                    );
                }

                /** @var array{outcome?: mixed, worker_exception?: mixed} $result */
                $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $outcome = $result['outcome'] ?? null;

                if (! is_string($outcome)) {
                    throw new RuntimeException(
                        'An LPL authority bootstrap worker returned invalid evidence.',
                    );
                }

                if ($outcome === 'error') {
                    throw new RuntimeException(sprintf(
                        'An LPL authority bootstrap worker failed with %s.',
                        is_string($result['worker_exception'] ?? null)
                            ? $result['worker_exception']
                            : 'unknown',
                    ));
                }

                $outcomes[] = $outcome;
                $waited = pcntl_waitpid($worker['pid'], $processStatus);

                if (
                    $waited !== $worker['pid']
                    || pcntl_wexitstatus($processStatus) !== 0
                ) {
                    throw new RuntimeException(
                        'An LPL authority bootstrap worker exited unsuccessfully.',
                    );
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
        self::assertTrue(
            $bothWorkersBlocked,
            'Both workers must enter the PostgreSQL organization lock queue.',
        );
        self::assertSame(['created', 'replayed'], $outcomes);
        self::assertSame(
            1,
            DB::table('programs')
                ->where('key', LocalLplPilotAuthorityBootstrapper::PROGRAM_KEY)
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('roles')
                ->where('key', LocalLplPilotAuthorityBootstrapper::ROLE_KEY)
                ->count(),
        );
        $this->assertDatabaseCount('authority_appointments', 1);
        self::assertSame(
            1,
            DB::table('organization_administration_commands')
                ->where('operation', 'lpl01.pilot_authority.bootstrap')
                ->whereNotNull('completed_at')
                ->count(),
        );
    }

    /** @return Worker */
    private function spawnWorker(string $idempotencyKey): array
    {
        $sockets = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );

        if ($sockets === false) {
            throw new RuntimeException('The worker pipe could not be created.');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);
            throw new RuntimeException('The worker could not be forked.');
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
                    throw new RuntimeException('The worker has no backend PID.');
                }

                fwrite($pipe, 'ready:'.$backendPid."\n");
                fflush($pipe);

                if (fgets($pipe) !== "go\n") {
                    throw new RuntimeException('The worker was not released.');
                }

                $result = $this->app
                    ->make(LocalLplPilotAuthorityBootstrapper::class)
                    ->bootstrap(
                        LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
                        LocalLplPilotAuthorityBootstrapper::USER_ID,
                        LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
                        'APP-0051',
                        'APP-0047',
                        $idempotencyKey,
                    );
                $outcome = ['outcome' => $result->replayed ? 'replayed' : 'created'];
            } catch (Throwable $exception) {
                $outcome = [
                    'outcome' => 'error',
                    'worker_exception' => $exception::class,
                ];
                $exitCode = 1;
            }

            fwrite($pipe, json_encode($outcome, JSON_THROW_ON_ERROR)."\n");
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

    private function insertExactFoundation(): void
    {
        DB::table('organizations')->insert([
            'id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            'system_key' => Organization::DIRECT_SYSTEM_KEY,
            'name' => 'Global English Direct',
            'kind' => Organization::KIND_PLATFORM_DIRECT,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seed(FoundationSeeder::class);
        DB::table('users')->insert([
            'id' => LocalLplPilotAuthorityBootstrapper::USER_ID,
            'display_name' => null,
            'status' => User::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('memberships')->insert([
            'id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
            'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            'user_id' => LocalLplPilotAuthorityBootstrapper::USER_ID,
            'membership_type' => Membership::TYPE_ORGANIZATION_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
            'ended_at' => null,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $administratorRoleId = DB::table('roles')
            ->where('organization_id', LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID)
            ->where('key', Role::DIRECT_ORGANIZATION_ADMIN_KEY)
            ->value('id');
        self::assertIsString($administratorRoleId);
        DB::table('membership_roles')->insert([
            'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            'membership_id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
            'role_id' => $administratorRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assets = [
            ['01KZNFYHCJSDWSPK08ZAC7G3AJ', '6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa', 4_968_408, 310_526],
            ['01KZNFYJ1M911SB8YVH9P4EZZ0', '75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7', 7_357_881, 459_868],
            ['01KZNFYJVSZRPZCEPTTQ4QTMNT', '8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70', 2_157_215, 134_826],
            ['01KZNFYK75V9WF8R5VCWRRZHNN', '4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e', 4_276_686, 267_293],
        ];

        foreach ($assets as $index => [$assetId, $hash, $bytes, $duration]) {
            $scanId = (string) Str::ulid();
            DB::table('media_assets')->insert([
                'id' => $assetId,
                'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
                'key' => 'lpl01-race-'.($index + 1),
                'asset_kind' => 'lesson_audio',
                'storage_object_key' => 'private/lpl01-race-'.($index + 1),
                'mime_type' => 'audio/mpeg',
                'byte_size' => $bytes,
                'asset_hash' => $hash,
                'uploaded_by_membership_id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
                'uploader_attribution' => 'test',
                'uploaded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('media_security_scans')->insert([
                'id' => $scanId,
                'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
                'media_upload_id' => null,
                'media_asset_id' => $assetId,
                'asset_kind' => 'lesson_audio',
                'object_hash' => $hash,
                'byte_size' => $bytes,
                'inspection_outcome' => 'passed',
                'malware_outcome' => 'clean',
                'detected_mime_type' => 'audio/mpeg',
                'duration_milliseconds' => $duration,
                'inspector_name' => 'ffprobe',
                'inspector_policy_version' => 'admin-media-inspection-v1',
                'scanner_name' => 'clamav-instream',
                'scanner_engine_version' => 'test',
                'scanner_policy_version' => 'admin-media-malware-v1',
                'technical_metadata_hash' => hash('sha256', 'scan-'.$assetId),
                'inspected_at' => now(),
                'scanned_at' => now(),
                'created_at' => now(),
            ]);
            DB::table('media_asset_lifecycles')->insert([
                'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
                'media_asset_id' => $assetId,
                'asset_kind' => 'lesson_audio',
                'state' => 'active',
                'version' => 1,
                'selected_scan_id' => $scanId,
                'predecessor_media_asset_id' => null,
                'managed_by_membership_id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
                'retired_by_membership_id' => null,
                'retirement_reason_key' => null,
                'managed_at' => now(),
                'retired_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
