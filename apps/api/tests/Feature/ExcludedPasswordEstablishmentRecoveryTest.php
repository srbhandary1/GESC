<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Password\PasswordRecoveryDeliveryAdapter;
use App\Authentication\Password\PasswordRecoveryFailed;
use App\Authentication\Password\PasswordRecoveryService;
use App\Authentication\Password\PrivilegedPasswordRecoveryService;
use App\Authentication\Password\ProtectedLocalPasswordRecoveryDeliveryAdapter;
use App\Authentication\Password\RecoveryTokenDigester;
use App\Models\PasswordRecoveryChallenge;
use App\Models\User;
use App\Models\UserIdentity;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ExcludedPasswordEstablishmentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoundationSeeder::class);
        config()->set('authentication.mode', 'password_only');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_excluded_target_establishes_credentials_through_protected_local_delivery(): void
    {
        CarbonImmutable::setTestNow('2026-08-11T10:00:00+00:00');
        $firstActor = $this->readyUser('actor.one', 'Correct horse battery 2026!');
        $secondActor = $this->readyUser('actor.two', 'Another correct battery 2026!');
        /** @var User $target */
        $target = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        /** @var UserIdentity $identity */
        $identity = UserIdentity::query()->forceCreate([
            'user_id' => $target->getKey(),
            'type' => 'phone',
            'identifier_digest' => hash('sha256', '+919000000000'),
            'display_hint' => '+91******0000',
            'status' => UserIdentity::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
        config()->set('platform.identity_recovery.actor_user_ids', [
            (string) $firstActor->getKey(),
            (string) $secondActor->getKey(),
        ]);
        DB::table('password_authentication_cutovers')->insert([
            'scope_key' => 'global',
            'status' => 'password_only',
            'manifest_digest' => str_repeat('a', 64),
            'evidence_digest' => str_repeat('b', 64),
            'active_user_count' => 3,
            'credential_ready_count' => 2,
            'excluded_user_count' => 1,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('password_cutover_exclusions')->insert([
            'user_id' => (string) $target->getKey(),
            'cutover_scope_key' => 'global',
            'reason_key' => 'cutover_excluded.synthetic_fixture',
            'owner_user_id' => (string) $firstActor->getKey(),
            'manifest_digest' => str_repeat('a', 64),
            'reconciled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $directory = '/tmp/global-english-protected-delivery-test-'.strtolower((string) Str::ulid());
        config()->set('authentication.password.recovery_delivery_driver', 'protected-local');
        config()->set('authentication.password.protected_delivery_path', $directory);
        $delivery = $this->app->make(ProtectedLocalPasswordRecoveryDeliveryAdapter::class);
        $this->app->instance(PasswordRecoveryDeliveryAdapter::class, $delivery);
        $request = Request::create('/internal/excluded-recovery', 'POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->setLaravelSession($this->app->make('session')->driver());
        $service = $this->app->make(PrivilegedPasswordRecoveryService::class);
        $evidence = 'support-case:SQLITE-EXCLUDED';

        try {
            $case = $service->open(
                $firstActor,
                (string) $target->getKey(),
                'identity_recovery.verified_support_case',
                $evidence,
                $request,
            );
            $caseId = $case['id'];
            self::assertIsString($caseId);
            $service->approve($firstActor, $caseId, $evidence, $request);
            $service->approve($secondActor, $caseId, $evidence, $request);

            try {
                $service->complete($firstActor, $caseId, $evidence, $request);
                self::fail('The mandatory recovery delay was bypassed.');
            } catch (PasswordRecoveryFailed) {
                // Expected.
            }

            CarbonImmutable::setTestNow(now()->addHours(24));
            $service->complete($firstActor, $caseId, $evidence, $request);
            $metadata = $delivery->latestEstablishmentMetadata();
            self::assertIsArray($metadata);
            self::assertArrayNotHasKey('token', $metadata);
            self::assertSame((string) $identity->display_hint, $metadata['recovery_hint']);
            $challengeId = $metadata['challenge_id'];
            $deliveryPath = $directory.'/'.$challengeId.'.json';
            self::assertSame(0600, fileperms($deliveryPath) & 0777);

            $recovered = $this->app->make(PasswordRecoveryService::class)
                ->consumeExcludedEstablishment(
                    $challengeId,
                    'sqlite.operator',
                    $delivery->tokenFor($challengeId),
                    'Target chooses sqlite battery 2026!',
                    $request,
                );
            $delivery->forget($challengeId);
            self::assertSame('sqlite.operator', $recovered->username);
            self::assertNotNull($recovered->credential_cutover_at);
            self::assertSame(
                PasswordRecoveryChallenge::STATUS_CONSUMED,
                PasswordRecoveryChallenge::query()->findOrFail($challengeId)->status,
            );
            self::assertFileDoesNotExist($deliveryPath);
        } finally {
            foreach (glob($directory.'/*.json') ?: [] as $path) {
                unlink($path);
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function test_expired_establishment_is_rejected_and_persistently_consumes_an_attempt(): void
    {
        CarbonImmutable::setTestNow('2026-08-11T10:00:00+00:00');
        $actor = $this->readyUser('expiry.actor', 'Expiry correct battery 2026!');
        /** @var User $target */
        $target = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $now = now();
        DB::table('password_authentication_cutovers')->insert([
            'scope_key' => 'global',
            'status' => 'password_only',
            'manifest_digest' => str_repeat('c', 64),
            'evidence_digest' => str_repeat('d', 64),
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
            'reason_key' => 'cutover_excluded.synthetic_fixture',
            'owner_user_id' => (string) $actor->getKey(),
            'manifest_digest' => str_repeat('c', 64),
            'reconciled_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $caseId = (string) Str::ulid();
        DB::table('privileged_password_recovery_cases')->insert([
            'id' => $caseId,
            'target_user_id' => (string) $target->getKey(),
            'requested_by_user_id' => (string) $actor->getKey(),
            'reason_key' => 'identity_recovery.verified_support_case',
            'recovery_kind' => PasswordRecoveryChallenge::PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT,
            'evidence_reference' => 'support-case:expiry-fixture',
            'evidence_digest' => str_repeat('e', 64),
            'status' => 'completed',
            'version' => 1,
            'requested_at' => $now->copy()->subDays(2),
            'not_before_at' => $now->copy()->subDay(),
            'expires_at' => $now->copy()->addDays(6),
            'completed_at' => $now->copy()->subHour(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challengeId = (string) Str::ulid();
        $digester = $this->app->make(RecoveryTokenDigester::class);
        PasswordRecoveryChallenge::query()->forceCreate([
            'id' => $challengeId,
            'user_id' => (string) $target->getKey(),
            'recovery_identity_id' => null,
            'purpose' => PasswordRecoveryChallenge::PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT,
            'privileged_recovery_case_id' => $caseId,
            'lookup_digest' => $digester->digest(
                'password-establishment-lookup',
                (string) $target->getKey(),
            ),
            'token_digest' => $digester->digest('password-recovery-token', $token),
            'status' => PasswordRecoveryChallenge::STATUS_PENDING,
            'failed_attempts' => 0,
            'delivery_outcome' => 'accepted',
            'expires_at' => $now->copy()->subSecond(),
        ]);
        $request = Request::create('/auth/password/establishment-recoveries', 'POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        try {
            $this->app->make(PasswordRecoveryService::class)
                ->consumeExcludedEstablishment(
                    $challengeId,
                    'too.late',
                    $token,
                    'Expired recovery battery 2026!',
                    $request,
                );
            self::fail('An expired excluded-account challenge was accepted.');
        } catch (PasswordRecoveryFailed) {
            // Expected.
        }

        $expired = PasswordRecoveryChallenge::query()->findOrFail($challengeId);
        self::assertSame(PasswordRecoveryChallenge::STATUS_EXPIRED, $expired->status);
        self::assertSame(1, $expired->failed_attempts);
        self::assertNull($target->fresh()?->username);
        self::assertFalse($target->passwordCredential()->exists());
    }

    private function readyUser(string $username, string $password): User
    {
        /** @var User $user */
        $user = User::query()->forceCreate(['status' => User::STATUS_ACTIVE]);
        $request = Request::create('/internal/password-establishment', 'POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $this->app->make(PasswordCredentialService::class)
            ->establish($user, $username, $password, $request);

        return $user->fresh() ?? $user;
    }
}
