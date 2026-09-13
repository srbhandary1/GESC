<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Identity\Exceptions\LocalOperatorIdentityBootstrapRejected;
use App\Identity\IdentityIdentifierDigester;
use App\Identity\LocalOperatorIdentityBootstrapper;
use App\Identity\ProtectedLocalIdentityInputReader;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserIdentity;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LocalOperatorIdentityBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_command_binds_only_one_identity_and_returns_content_free_evidence(): void
    {
        [$userId, $membershipId] = $this->createDirectAdministrator();
        $phone = $this->syntheticPhone();
        $phoneFile = $this->protectedInput($phone);
        $before = $this->authorityCounts();
        $log = Log::spy();

        try {
            $exitCode = Artisan::call(
                'auth:bootstrap-local-operator-identity',
                [
                    '--phone-file' => $phoneFile,
                    '--user' => $userId,
                    '--membership' => $membershipId,
                    '--approval' => LocalOperatorIdentityBootstrapper::APPROVAL_REFERENCE,
                ],
            );
            $output = trim(Artisan::output());
        } finally {
            @unlink($phoneFile);
        }

        self::assertSame(0, $exitCode);
        /** @var array<string, mixed> $evidence */
        $evidence = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('bound', $evidence['status'] ?? null);
        self::assertSame($userId, $evidence['user_id'] ?? null);
        self::assertSame($membershipId, $evidence['membership_id'] ?? null);
        self::assertTrue(Str::isUlid($evidence['identity_id'] ?? null));
        self::assertSame('local', $evidence['environment'] ?? null);
        self::assertSame('APP-0049', $evidence['approval_reference'] ?? null);
        self::assertIsString($evidence['bound_at'] ?? null);
        self::assertStringNotContainsString($phone, $output);
        self::assertStringNotContainsString(
            $this->app->make(IdentityIdentifierDigester::class)->digest(
                'phone',
                $phone,
            ),
            $output,
        );

        self::assertSame($before, $this->authorityCounts());
        $this->assertDatabaseHas('user_identities', [
            'user_id' => $userId,
            'type' => 'phone',
            'status' => UserIdentity::STATUS_VERIFIED,
            'display_hint' => null,
        ]);
        $this->assertDatabaseCount('user_identities', 1);
        foreach (
            ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level
        ) {
            $log->shouldNotHaveReceived($level);
        }
    }

    public function test_command_is_one_shot_and_failure_output_is_generic(): void
    {
        [$userId, $membershipId] = $this->createDirectAdministrator();
        $phone = $this->syntheticPhone();
        $phoneFile = $this->protectedInput($phone);

        try {
            $arguments = [
                '--phone-file' => $phoneFile,
                '--user' => $userId,
                '--membership' => $membershipId,
                '--approval' => 'APP-0049',
            ];
            self::assertSame(
                0,
                Artisan::call('auth:bootstrap-local-operator-identity', $arguments),
            );
            self::assertSame(
                2,
                Artisan::call('auth:bootstrap-local-operator-identity', $arguments),
            );
            $output = trim(Artisan::output());
        } finally {
            @unlink($phoneFile);
        }

        self::assertSame(
            [
                'status' => 'blocked',
                'safe_error_code' => 'local_operator_identity_bootstrap_rejected',
            ],
            json_decode($output, true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString($phone, $output);
        $this->assertDatabaseCount('user_identities', 1);
    }

    public function test_environment_driver_switch_reference_and_ids_fail_closed(): void
    {
        [$userId, $membershipId] = $this->createDirectAdministrator();
        $phoneFile = $this->protectedInput($this->syntheticPhone());
        $bootstrapper = $this->app->make(
            LocalOperatorIdentityBootstrapper::class,
        );
        $cases = [
            ['environment' => 'production'],
            ['driver' => 'provider'],
            ['enabled' => false],
            ['approval' => 'APP-OTHER'],
            ['user' => 'not-a-ulid'],
            ['membership' => 'not-a-ulid'],
        ];

        try {
            foreach ($cases as $case) {
                $this->app['env'] = $case['environment'] ?? 'local';
                config([
                    'authentication.otp.driver' => $case['driver'] ?? 'local',
                    'authentication.identity.local_operator_bootstrap_enabled' => $case['enabled'] ?? true,
                ]);

                try {
                    $bootstrapper->bootstrap(
                        phoneFile: $phoneFile,
                        expectedUserId: $case['user'] ?? $userId,
                        expectedMembershipId: $case['membership'] ?? $membershipId,
                        approvalReference: $case['approval'] ?? 'APP-0049',
                    );
                    self::fail('Expected the local identity bootstrap to reject the case.');
                } catch (LocalOperatorIdentityBootstrapRejected $exception) {
                    self::assertSame(
                        'Local operator identity bootstrap rejected.',
                        $exception->getMessage(),
                    );
                }
            }
        } finally {
            @unlink($phoneFile);
            $this->app['env'] = 'local';
            config([
                'authentication.otp.driver' => 'local',
                'authentication.identity.local_operator_bootstrap_enabled' => true,
            ]);
        }

        $this->assertDatabaseCount('user_identities', 0);
    }

    public function test_identity_role_and_tenant_mismatches_fail_closed(): void
    {
        [$userId, $membershipId, $roleId] = $this->createDirectAdministrator();
        $phoneFile = $this->protectedInput($this->syntheticPhone());
        $bootstrapper = $this->app->make(
            LocalOperatorIdentityBootstrapper::class,
        );

        try {
            DB::table('membership_roles')
                ->where('membership_id', $membershipId)
                ->where('role_id', $roleId)
                ->delete();
            $this->expectBootstrapRejection(
                $bootstrapper,
                $phoneFile,
                $userId,
                $membershipId,
            );

            DB::table('membership_roles')->insert([
                'organization_id' => Organization::query()
                    ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
                    ->value('id'),
                'membership_id' => $membershipId,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            UserIdentity::query()->forceCreate([
                'user_id' => $userId,
                'type' => 'email',
                'identifier_digest' => str_repeat('e', 64),
                'display_hint' => null,
                'status' => UserIdentity::STATUS_VERIFIED,
                'verified_at' => now(),
            ]);
            $this->expectBootstrapRejection(
                $bootstrapper,
                $phoneFile,
                $userId,
                $membershipId,
            );
        } finally {
            @unlink($phoneFile);
        }

        $this->assertDatabaseCount('user_identities', 1);
        $this->assertDatabaseMissing('user_identities', ['type' => 'phone']);
    }

    public function test_duplicate_phone_digest_for_another_user_fails_closed(): void
    {
        [$userId, $membershipId] = $this->createDirectAdministrator();
        $phone = $this->syntheticPhone();
        $phoneFile = $this->protectedInput($phone);
        $other = User::query()->forceCreate([
            'display_name' => null,
            'status' => User::STATUS_ACTIVE,
        ]);
        UserIdentity::query()->forceCreate([
            'user_id' => $other->getKey(),
            'type' => 'phone',
            'identifier_digest' => $this->app
                ->make(IdentityIdentifierDigester::class)
                ->digest('phone', $phone),
            'display_hint' => null,
            'status' => UserIdentity::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        try {
            $this->expectBootstrapRejection(
                $this->app->make(LocalOperatorIdentityBootstrapper::class),
                $phoneFile,
                $userId,
                $membershipId,
            );
        } finally {
            @unlink($phoneFile);
        }

        self::assertSame(
            0,
            UserIdentity::query()->where('user_id', $userId)->count(),
        );
        $this->assertDatabaseCount('user_identities', 1);
    }

    public function test_protected_input_rejects_loose_permissions_symlinks_and_non_temp_files(): void
    {
        $reader = $this->app->make(ProtectedLocalIdentityInputReader::class);
        $phoneFile = $this->protectedInput($this->syntheticPhone());
        chmod($phoneFile, 0644);

        try {
            $this->expectReaderRejection($reader, $phoneFile);
            chmod($phoneFile, 0600);

            $link = $phoneFile.'-link';
            symlink($phoneFile, $link);
            try {
                $this->expectReaderRejection($reader, $link);
            } finally {
                @unlink($link);
            }

            $outside = base_path('local-operator-phone-test.tmp');
            file_put_contents($outside, $this->syntheticPhone());
            chmod($outside, 0600);
            try {
                $this->expectReaderRejection($reader, $outside);
            } finally {
                @unlink($outside);
            }
        } finally {
            @unlink($phoneFile);
        }
    }

    public function test_command_definition_has_no_raw_phone_argument_or_option(): void
    {
        $definition = Artisan::all()['auth:bootstrap-local-operator-identity']
            ->getDefinition();

        self::assertFalse($definition->hasArgument('phone'));
        self::assertFalse($definition->hasOption('phone'));
        self::assertTrue($definition->hasOption('phone-file'));
    }

    /** @return array{string, string, string} */
    private function createDirectAdministrator(): array
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->sole();
        $roleId = DB::table('roles')
            ->where('organization_id', $organization->getKey())
            ->where('key', 'organization_admin')
            ->value('id');
        self::assertIsString($roleId);
        $user = User::query()->forceCreate([
            'display_name' => null,
            'status' => User::STATUS_ACTIVE,
        ]);
        $membershipId = (string) Str::ulid();
        DB::table('memberships')->insert([
            'id' => $membershipId,
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'membership_type' => Membership::TYPE_ORGANIZATION_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
            'ended_at' => null,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membershipId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            (string) $user->getKey(),
            $membershipId,
            $roleId,
        ];
    }

    /** @return array<string, int> */
    private function authorityCounts(): array
    {
        return collect([
            'organizations',
            'users',
            'memberships',
            'roles',
            'membership_roles',
            'permissions',
            'authority_appointments',
            'media_uploads',
            'media_assets',
            'media_rights_revisions',
            'audit_events',
            'outbox_events',
        ])->mapWithKeys(
            static fn (string $table): array => [
                $table => DB::table($table)->count(),
            ],
        )->all();
    }

    private function protectedInput(string $value): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gse-local-operator-');

        if (! is_string($path)) {
            self::fail('Could not allocate a protected test input.');
        }

        chmod($path, 0600);
        file_put_contents($path, $value."\n");

        return $path;
    }

    private function syntheticPhone(): string
    {
        return '+'.'91'.'9100000049';
    }

    private function expectBootstrapRejection(
        LocalOperatorIdentityBootstrapper $bootstrapper,
        string $phoneFile,
        string $userId,
        string $membershipId,
    ): void {
        try {
            $bootstrapper->bootstrap(
                phoneFile: $phoneFile,
                expectedUserId: $userId,
                expectedMembershipId: $membershipId,
                approvalReference: 'APP-0049',
            );
            self::fail('Expected local operator identity bootstrap rejection.');
        } catch (LocalOperatorIdentityBootstrapRejected $exception) {
            self::assertSame(
                'Local operator identity bootstrap rejected.',
                $exception->getMessage(),
            );
        }
    }

    private function expectReaderRejection(
        ProtectedLocalIdentityInputReader $reader,
        string $path,
    ): void {
        try {
            $reader->read($path);
            self::fail('Expected protected input rejection.');
        } catch (LocalOperatorIdentityBootstrapRejected $exception) {
            self::assertSame(
                'Local operator identity bootstrap rejected.',
                $exception->getMessage(),
            );
        }
    }
}
