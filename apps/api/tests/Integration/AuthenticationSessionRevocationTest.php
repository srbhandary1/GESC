<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Sessions\AuthenticationSessionService;
use App\Models\AuthenticationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SessionHandlerInterface;
use Tests\TestCase;

final class AuthenticationSessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_current_record_still_invalidates_the_request_session(): void
    {
        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $request = $this->requestWithSession('missing-record-storage-id');
        $originalStorageId = $request->session()->getId();
        $request->session()->put(
            AuthenticationSessionService::SESSION_KEY,
            (string) Str::ulid(),
        );

        $this->app
            ->make(AuthenticationSessionService::class)
            ->revokeCurrent($user, $request);

        self::assertNotSame($originalStorageId, $request->session()->getId());
        self::assertNull($request->session()->get('marker'));
        self::assertNull(
            $request->session()->get(AuthenticationSessionService::SESSION_KEY),
        );
    }

    public function test_corrupt_storage_ciphertext_cannot_prevent_authoritative_revocation(): void
    {
        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $request = $this->requestWithSession('corrupt-ciphertext-storage-id');
        $authenticationSession = $this->createAuthenticationSession(
            $user,
            'corrupt-storage-session-ciphertext',
        );
        $request->session()->put(
            AuthenticationSessionService::SESSION_KEY,
            (string) $authenticationSession->getKey(),
        );
        $originalStorageId = $request->session()->getId();

        $revoked = $this->app
            ->make(AuthenticationSessionService::class)
            ->revoke(
                $user,
                (string) $authenticationSession->getKey(),
                $request,
            );

        self::assertTrue($revoked);
        self::assertNotSame($originalStorageId, $request->session()->getId());
        self::assertNull($request->session()->get('marker'));
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $authenticationSession->getKey(),
            'revocation_reason' => 'user_revoked',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.session.revoked',
            'subject_id' => $authenticationSession->getKey(),
        ]);

        $auditPayload = json_encode(
            DB::table('audit_events')->get()->all(),
            JSON_THROW_ON_ERROR,
        );
        self::assertStringNotContainsString(
            'corrupt-storage-session-ciphertext',
            $auditPayload,
        );
    }

    public function test_storage_handler_failure_cannot_prevent_authoritative_revocation(): void
    {
        $storageId = 'handler-failure-storage-id';
        $failingHandler = new ThrowingSessionHandler;
        $sessionManager = new SessionManager($this->app);
        $sessionManager->extend(
            'failing',
            fn (): ThrowingSessionHandler => $failingHandler,
        );
        $sessionManager->setDefaultDriver('failing');
        $this->app->instance('session', $sessionManager);

        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $request = $this->requestWithSession($storageId);
        $authenticationSession = $this->createAuthenticationSession(
            $user,
            Crypt::encryptString($storageId),
        );
        $request->session()->put(
            AuthenticationSessionService::SESSION_KEY,
            (string) $authenticationSession->getKey(),
        );
        $originalStorageId = $request->session()->getId();

        $revoked = $this->app
            ->make(AuthenticationSessionService::class)
            ->revoke(
                $user,
                (string) $authenticationSession->getKey(),
                $request,
            );

        self::assertTrue($revoked);
        self::assertSame($storageId, $failingHandler->attemptedSessionId);
        self::assertNotSame($originalStorageId, $request->session()->getId());
        self::assertNull($request->session()->get('marker'));
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $authenticationSession->getKey(),
            'revocation_reason' => 'user_revoked',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.session.revoked',
            'subject_id' => $authenticationSession->getKey(),
        ]);
    }

    private function requestWithSession(string $storageId): Request
    {
        $store = new Store(
            'global-english-test',
            new ArraySessionHandler(120),
            $storageId,
        );
        $store->start();
        $store->put('marker', 'present');

        $request = Request::create('/test/authentication-session', 'POST');
        $request->setLaravelSession($store);

        return $request;
    }

    private function createAuthenticationSession(
        User $user,
        string $storageSessionCiphertext,
    ): AuthenticationSession {
        /** @var AuthenticationSession */
        return AuthenticationSession::query()->forceCreate([
            'user_id' => $user->getKey(),
            'storage_session_digest' => hash('sha256', (string) Str::uuid()),
            'storage_session_ciphertext' => $storageSessionCiphertext,
            'is_privileged' => false,
            'started_at' => now(),
            'last_seen_at' => now(),
            'idle_expires_at' => now()->addMinutes(120),
            'absolute_expires_at' => now()->addDays(7),
        ]);
    }
}

final class ThrowingSessionHandler implements SessionHandlerInterface
{
    public ?string $attemptedSessionId = null;

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        return '';
    }

    public function write(string $id, string $data): bool
    {
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->attemptedSessionId = $id;

        throw new RuntimeException('Simulated session storage failure.');
    }

    public function gc(int $max_lifetime): int
    {
        return 0;
    }
}
