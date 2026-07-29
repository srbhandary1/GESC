<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\AuditEvent;
use App\Models\MobileRefreshToken;
use App\Models\MobileTokenFamily;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Bridge\User as PassportUser;
use Laravel\Passport\Client;
use League\OAuth2\Server\AuthorizationServer;
use PDO;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Tests\TestCase;
use Throwable;

final class ConcurrentMobileRefreshTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_refresh_reuse_revokes_the_entire_family(): void
    {
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(
            function_exists('pcntl_fork'),
            'The PostgreSQL concurrency gate requires the pcntl extension.',
        );

        $this->configurePassportKeys();

        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $client = $this->createPublicClient();
        $installationId = (string) Str::ulid();
        $refreshToken = $this->issueInitialRefreshToken(
            $user,
            $client,
            $installationId,
        );
        $trackedRefresh = MobileRefreshToken::query()->sole();
        $family = MobileTokenFamily::query()->sole();
        $databaseConfig = DB::connection()->getConfig();

        DB::disconnect();

        /** @var list<array{pid: int, pipe: resource, reaped: bool}> $workers */
        $workers = [];
        $lockConnection = null;
        $results = [];
        $bothWorkersBlocked = false;

        try {
            for ($worker = 0; $worker < 2; $worker++) {
                $workers[] = $this->spawnRefreshWorker(
                    $client,
                    $refreshToken,
                    $installationId,
                );
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipe'], 10);

                if (fgets($worker['pipe']) !== "ready\n") {
                    throw new RuntimeException('A concurrent refresh worker did not become ready.');
                }
            }

            $lockConnection = $this->openPostgreSqlConnection($databaseConfig);
            $lockConnection->beginTransaction();

            $lock = $lockConnection->prepare(
                'select id from mobile_refresh_tokens where id = :id for update',
            );
            $lock->execute(['id' => (string) $trackedRefresh->getKey()]);

            foreach ($workers as $worker) {
                if (fwrite($worker['pipe'], "go\n") === false) {
                    throw new RuntimeException('A concurrent refresh worker could not be released.');
                }

                fflush($worker['pipe']);
            }

            $bothWorkersBlocked = $this->waitUntilBothWorkersBlock($lockConnection);

            $lockConnection->commit();

            foreach ($workers as $index => $worker) {
                $line = fgets($worker['pipe']);

                if (! is_string($line)) {
                    throw new RuntimeException('A concurrent refresh worker returned no result.');
                }

                /** @var array{status?: mixed, error?: mixed, worker_exception?: mixed} $result */
                $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $results[] = $result;

                $waited = pcntl_waitpid($worker['pid'], $processStatus);

                if ($waited !== $worker['pid'] || pcntl_wexitstatus($processStatus) !== 0) {
                    throw new RuntimeException('A concurrent refresh worker failed.');
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

        self::assertTrue(
            $bothWorkersBlocked,
            sprintf(
                'Both refresh workers must block on the same PostgreSQL row; worker results: %s.',
                json_encode($results, JSON_THROW_ON_ERROR),
            ),
        );
        self::assertCount(2, $results);
        self::assertSame(
            [200, 400],
            collect($results)
                ->pluck('status')
                ->map(static fn (mixed $status): int => (int) $status)
                ->sort()
                ->values()
                ->all(),
        );
        self::assertSame(
            ['invalid_grant'],
            collect($results)
                ->pluck('error')
                ->filter()
                ->values()
                ->all(),
        );
        self::assertSame(
            'refresh_token_reuse',
            $family->fresh()?->revocation_reason,
        );

        $familyRefreshTokens = MobileRefreshToken::query()
            ->where('family_id', $family->getKey())
            ->get();

        self::assertCount(2, $familyRefreshTokens);
        self::assertSame(
            0,
            DB::table('oauth_access_tokens')
                ->whereIn('id', $familyRefreshTokens->pluck('access_token_id')->all())
                ->where('revoked', false)
                ->count(),
        );
        self::assertSame(
            0,
            DB::table('oauth_refresh_tokens')
                ->whereIn(
                    'id',
                    $familyRefreshTokens->pluck('passport_refresh_token_id')->all(),
                )
                ->where('revoked', false)
                ->count(),
        );
        self::assertSame(
            1,
            AuditEvent::query()
                ->where('action', 'authentication.mobile_credential.reuse_detected')
                ->where('subject_id', $family->getKey())
                ->count(),
        );
    }

    /**
     * @return array{pid: int, pipe: resource, reaped: bool}
     */
    private function spawnRefreshWorker(
        Client $client,
        string $refreshToken,
        string $installationId,
    ): array {
        $sockets = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );

        if ($sockets === false) {
            throw new RuntimeException('The concurrent refresh worker pipe could not be created.');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($sockets[0]);
            fclose($sockets[1]);

            throw new RuntimeException('The concurrent refresh worker could not be forked.');
        }

        if ($pid === 0) {
            fclose($sockets[0]);
            $this->runRefreshWorker(
                $sockets[1],
                (string) $client->getKey(),
                $refreshToken,
                $installationId,
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
    private function runRefreshWorker(
        mixed $pipe,
        string $clientId,
        string $refreshToken,
        string $installationId,
    ): never {
        try {
            DB::purge();
            DB::reconnect();

            fwrite($pipe, "ready\n");
            fflush($pipe);

            if (fgets($pipe) !== "go\n") {
                throw new RuntimeException('The refresh worker received an invalid release signal.');
            }

            $response = $this->post('/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'refresh_token' => $refreshToken,
                'installation_id' => $installationId,
            ]);

            fwrite($pipe, json_encode([
                'status' => $response->getStatusCode(),
                'error' => $response->json('error'),
            ], JSON_THROW_ON_ERROR)."\n");
            fflush($pipe);
            fclose($pipe);

            exit(0);
        } catch (Throwable $exception) {
            if (is_resource($pipe)) {
                fwrite($pipe, json_encode([
                    'status' => 500,
                    'worker_exception' => $exception::class,
                ], JSON_THROW_ON_ERROR)."\n");
                fflush($pipe);
                fclose($pipe);
            }

            exit(1);
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
                      and position('mobile_refresh_tokens' in query) > 0
                    SQL,
            );

            if ($waiting === false) {
                throw new RuntimeException('PostgreSQL lock state could not be inspected.');
            }

            if ((int) $waiting->fetchColumn() >= 2) {
                return true;
            }

            usleep(10_000);
        }

        return false;
    }

    private function issueInitialRefreshToken(
        User $user,
        Client $client,
        string $installationId,
    ): string {
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(
            base64_encode(hash('sha256', $verifier, true)),
            '+/',
            '-_',
        ), '=');
        $redirectUri = 'com.globalenglish.app:/oauth/callback';
        $authorizationRequest = Request::create(
            '/oauth/authorize',
            'GET',
            [
                'client_id' => $client->getKey(),
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'mobile:api',
                'state' => Str::random(40),
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
            ],
        );
        $server = $this->app->make(AuthorizationServer::class);
        $validated = $server->validateAuthorizationRequest(
            (new PsrHttpFactory)->createRequest($authorizationRequest),
        );
        $userId = (string) $user->getKey();

        if ($userId === '') {
            throw new RuntimeException('Passport requires a persisted user identifier.');
        }

        $validated->setUser(new PassportUser($userId));
        $validated->setAuthorizationApproved(true);
        $authorizationResponse = $server->completeAuthorizationRequest(
            $validated,
            $this->app->make(ResponseInterface::class),
        );
        $location = $authorizationResponse->getHeaderLine('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $code = $query['code'] ?? null;

        if (! is_string($code) || $code === '') {
            throw new RuntimeException('Passport did not issue an authorization code.');
        }

        $issued = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => $redirectUri,
            'code' => $code,
            'code_verifier' => $verifier,
            'installation_id' => $installationId,
        ])->assertOk();
        $refreshToken = $issued->json('refresh_token');

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new RuntimeException('Passport did not issue a refresh token.');
        }

        return $refreshToken;
    }

    private function createPublicClient(): Client
    {
        return Client::query()->create([
            'name' => 'Global English concurrent refresh client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
    }

    private function configurePassportKeys(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $privateKey));
        self::assertIsString($privateKey);

        $details = openssl_pkey_get_details($key);

        self::assertIsArray($details);
        self::assertIsString($details['key'] ?? null);

        config([
            'passport.private_key' => $privateKey,
            'passport.public_key' => $details['key'],
        ]);
    }
}
