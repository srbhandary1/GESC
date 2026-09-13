<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Password\PasswordRecoveryDeliveryAdapter;
use App\Authentication\Password\ProtectedLocalPasswordRecoveryDeliveryAdapter;
use App\Models\PasswordRecoveryChallenge;
use App\Models\User;
use App\Models\UserIdentity;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AccountSetupRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_excluded_target_establishes_credentials_through_one_manager_and_protected_delivery(): void
    {
        $this->seed(FoundationSeeder::class);
        config()->set('authentication.mode', 'password_only');
        $managerPassword = 'Correct manager battery 2026!';
        $manager = $this->readyUser('recovery.manager', $managerPassword);
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
        config()->set('platform.account_recovery.manager_user_ids', [
            (string) $manager->getKey(),
        ]);
        $this->insertCutover($manager, $target);
        $directory = '/tmp/global-english-protected-delivery-test-'.strtolower((string) Str::ulid());
        config()->set('authentication.password.recovery_delivery_driver', 'protected-local');
        config()->set('authentication.password.protected_delivery_path', $directory);
        $delivery = $this->app->make(ProtectedLocalPasswordRecoveryDeliveryAdapter::class);
        $this->app->instance(PasswordRecoveryDeliveryAdapter::class, $delivery);
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();

        try {
            $signedIn = $this->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/auth/password/sign-ins', [
                    'username' => 'recovery.manager',
                    'password' => $managerPassword,
                ])->assertOk();
            $cookie = $signedIn->getCookie((string) config('session.cookie'));
            self::assertNotNull($cookie);
            $this->withCookie($cookie->getName(), (string) $cookie->getValue());
            $this->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/platform/account-recovery/setups', [
                    'target_user_id' => (string) $target->getKey(),
                    'reason_key' => 'account_recovery.migration_setup',
                    'evidence_reference' => 'support-case:LOCAL-SETUP',
                ])
                ->assertForbidden()
                ->assertJson([
                    'type' => 'urn:global-english:problem:fresh-password-confirmation-required',
                    'status' => 403,
                    'message_key' => 'problem.fresh_password_confirmation_required',
                ]);
            self::assertFalse(
                DB::table('account_recovery_management_events')->exists(),
            );
            self::assertFalse(
                DB::table('password_recovery_challenges')
                    ->where('purpose', 'account_setup')
                    ->exists(),
            );
            $this->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/auth/password/confirmations', [
                    'password' => $managerPassword,
                ])->assertOk();
            $this->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/platform/account-recovery/setups', [
                    'target_user_id' => (string) $target->getKey(),
                    'reason_key' => 'account_recovery.migration_setup',
                    'evidence_reference' => 'support-case:LOCAL-SETUP',
                ])->assertCreated();

            $metadata = $delivery->latestEstablishmentMetadata();
            self::assertIsArray($metadata);
            self::assertArrayNotHasKey('token', $metadata);
            self::assertSame((string) $identity->display_hint, $metadata['recovery_hint']);
            $challengeId = $metadata['challenge_id'];
            $deliveryPath = $directory.'/'.$challengeId.'.json';
            self::assertSame(0600, fileperms($deliveryPath) & 0777);

            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/local/password-establishment', [
                    'challenge_id' => $challengeId,
                    'username' => 'local.operator',
                    'password' => 'Target chooses local battery 2026!',
                ])->assertNoContent();
            self::assertSame('local.operator', $target->fresh()?->username);
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

    private function insertCutover(User $manager, User $target): void
    {
        $now = now();
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
            'reason_key' => 'cutover_excluded.synthetic_fixture',
            'owner_user_id' => (string) $manager->getKey(),
            'manifest_digest' => str_repeat('a', 64),
            'reconciled_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
