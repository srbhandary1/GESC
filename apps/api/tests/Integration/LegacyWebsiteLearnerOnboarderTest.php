<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Password\PasswordPolicy;
use App\Authentication\Password\PasswordPolicyViolation;
use App\Entitlements\DirectPlanDefaults;
use App\Models\PasswordCredential;
use App\Models\User;
use App\Tenancy\LegacyWebsiteLearnerOnboarder;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;

final class LegacyWebsiteLearnerOnboarderTest extends TenancyIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoundationSeeder::class);
        config()->set('authentication.mode', 'password_only');
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_policy_accepts_previous_website_phone_identifiers(): void
    {
        $policy = $this->app->make(PasswordPolicy::class);

        self::assertSame('7439091943', $policy->normalizeUsername(' 74390 91943 '));
        self::assertSame('+919876543210', $policy->normalizeUsername('+91 98765-43210'));
        self::assertSame('learner.one', $policy->normalizeUsername(' Learner.One '));
        self::assertSame(
            'learner.one',
            $policy->normalizeEstablishmentUsername(' Learner.One '),
        );

        try {
            $policy->normalizeUsername('12');
            self::fail('A too-short phone was accepted.');
        } catch (PasswordPolicyViolation) {
            // Expected.
        }

        try {
            $policy->normalizeEstablishmentUsername('7439091943');
            self::fail('A phone was accepted as a new username.');
        } catch (PasswordPolicyViolation) {
            // Expected.
        }
    }

    public function test_imported_bcrypt_hash_authenticates_the_same_phone_and_password(): void
    {
        $hash = password_hash('123', PASSWORD_BCRYPT, ['cost' => 4]);
        $onboarder = $this->app->make(LegacyWebsiteLearnerOnboarder::class);
        $summary = $onboarder->onboard([
            [
                'legacy_user_id' => '2149',
                'phone' => '7439091943',
                'name' => 'Test Learner',
                'password_hash' => $hash,
            ],
        ]);

        self::assertSame(1, $summary->provisioned);
        self::assertSame(1, $summary->credentialsImported);
        self::assertSame(1, $summary->granted);
        $user = User::query()->where('username', '7439091943')->sole();
        $credential = PasswordCredential::query()->whereKey($user->getKey())->sole();
        self::assertSame('bcrypt', $credential->algorithm);
        self::assertSame('Test Learner', $user->display_name);

        $signedIn = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/password/sign-ins', [
                'username' => '74390 91943',
                'password' => '123',
            ])
            ->assertOk()
            ->assertJsonPath('user.id', (string) $user->getKey());
        self::assertNotNull($signedIn->getCookie((string) config('session.cookie')));

        $rehashed = PasswordCredential::query()->whereKey($user->getKey())->sole();
        self::assertSame('argon2id', $rehashed->algorithm);

        $replay = $onboarder->onboard([
            [
                'legacy_user_id' => '2149',
                'phone' => '7439091943',
                'name' => 'Ignored',
                'password_hash' => $hash,
            ],
        ]);
        self::assertSame(1, $replay->replayed);
        self::assertSame(0, $replay->provisioned);
        self::assertSame(1, $replay->grantSkipped);
        self::assertSame(1, User::query()->count());
        self::assertSame(
            DirectPlanDefaults::MONTHLY_MODULES,
            json_decode((string) DB::table('learner_entitlements')->value('plan_snapshot'), true)['code'] ?? null,
        );
    }

    public function test_dry_run_does_not_write_users_or_entitlements(): void
    {
        $hash = password_hash('123', PASSWORD_BCRYPT, ['cost' => 4]);
        $summary = $this->app->make(LegacyWebsiteLearnerOnboarder::class)->onboard([
            [
                'legacy_user_id' => '9',
                'phone' => '9876543210',
                'password_hash' => $hash,
            ],
        ], true);

        self::assertTrue($summary->dryRun);
        self::assertSame(1, $summary->provisioned);
        self::assertSame(0, User::query()->count());
        self::assertSame(0, DB::table('learner_entitlements')->count());
    }

    public function test_duplicate_phones_in_the_manifest_are_skipped(): void
    {
        $hash = password_hash('123', PASSWORD_BCRYPT, ['cost' => 4]);
        $summary = $this->app->make(LegacyWebsiteLearnerOnboarder::class)->onboard([
            [
                'legacy_user_id' => '21',
                'phone' => '9876543210',
                'password_hash' => $hash,
            ],
            [
                'legacy_user_id' => '22',
                'phone' => '98765 43210',
                'password_hash' => $hash,
            ],
        ]);

        self::assertSame(1, $summary->provisioned);
        self::assertSame(1, $summary->skippedConflict);
        self::assertSame(1, User::query()->count());
    }
}
