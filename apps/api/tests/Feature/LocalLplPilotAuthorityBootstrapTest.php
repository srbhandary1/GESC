<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Curriculum\Commissioning\Exceptions\LocalLplPilotAuthorityBootstrapRejected;
use App\Curriculum\Commissioning\LocalLplPilotAuthorityBootstrapper;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LocalLplPilotAuthorityBootstrapTest extends TestCase
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
            'content_governance.local_lpl_pilot_authority_bootstrap_enabled' => true,
            'content_governance.consolidated_self_appointments.APP-0004.actor_user_id' => LocalLplPilotAuthorityBootstrapper::USER_ID,
        ]);
        $this->insertExactDirectOrganization();
        $this->seed(FoundationSeeder::class);
        $this->insertExactActorAndCleanAssets();
    }

    protected function tearDown(): void
    {
        $this->app['env'] = $this->originalEnvironment;

        parent::tearDown();
    }

    public function test_command_creates_only_the_exact_private_authority_state_and_replays(): void
    {
        $idempotencyKey = (string) Str::ulid();
        $before = $this->protectedCounts();
        $log = Log::spy();

        $exitCode = Artisan::call(
            'lpl:bootstrap-local-pilot-authority',
            $this->arguments($idempotencyKey),
        );
        $output = trim(Artisan::output());

        self::assertSame(0, $exitCode);
        /** @var array<string, mixed> $evidence */
        $evidence = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('created', $evidence['status'] ?? null);
        self::assertSame(
            LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
            $evidence['membership_id'] ?? null,
        );
        self::assertSame('local', $evidence['environment'] ?? null);
        self::assertSame('APP-0051', $evidence['approval_reference'] ?? null);
        self::assertSame('APP-0047', $evidence['owner_evidence_reference'] ?? null);
        self::assertTrue(Str::isUlid($evidence['program_id'] ?? null));
        self::assertTrue(Str::isUlid($evidence['role_id'] ?? null));
        self::assertTrue(Str::isUlid($evidence['appointment_id'] ?? null));
        self::assertStringNotContainsString(
            '6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa',
            $output,
        );

        $programId = (string) $evidence['program_id'];
        $roleId = (string) $evidence['role_id'];
        $appointmentId = (string) $evidence['appointment_id'];
        $this->assertDatabaseHas('programs', [
            'id' => $programId,
            'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            'key' => LocalLplPilotAuthorityBootstrapper::PROGRAM_KEY,
            'internal_name' => LocalLplPilotAuthorityBootstrapper::PROGRAM_NAME,
            'descriptor_version' => 1,
            'created_by_membership_id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
            'created_by_attribution' => 'lpl01_pilot_authority_bootstrap',
        ]);
        self::assertSame(
            0,
            DB::table('curriculum_versions')->where('program_id', $programId)->count(),
        );
        self::assertSame(
            0,
            DB::table('curriculum_publications')->where('program_id', $programId)->count(),
        );
        $this->assertDatabaseHas('roles', [
            'id' => $roleId,
            'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            'key' => LocalLplPilotAuthorityBootstrapper::ROLE_KEY,
            'name' => LocalLplPilotAuthorityBootstrapper::ROLE_NAME,
            'is_system' => false,
            'status' => Role::STATUS_ACTIVE,
            'version' => 1,
        ]);
        self::assertSame(
            ['curriculum.author', 'media.rights.approve'],
            DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_permissions.role_id', $roleId)
                ->orderBy('permissions.key')
                ->pluck('permissions.key')
                ->all(),
        );
        self::assertSame(
            [LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID],
            DB::table('membership_roles')
                ->where('role_id', $roleId)
                ->pluck('membership_id')
                ->all(),
        );
        $this->assertDatabaseHas('memberships', [
            'id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
            'membership_type' => Membership::TYPE_ORGANIZATION_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
            'version' => 2,
        ]);
        $this->assertDatabaseHas('authority_appointments', [
            'id' => $appointmentId,
            'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            'appointee_user_id' => LocalLplPilotAuthorityBootstrapper::USER_ID,
            'appointee_membership_id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
            'authority_kind' => 'rights_confirmer',
            'scope_type' => 'program',
            'program_id' => $programId,
            'qualification_basis' => 'APP-0047',
            'approval_evidence_key' => 'APP-0004',
            'valid_until' => null,
        ]);
        self::assertSame(
            ['appointed'],
            DB::table('authority_appointment_events')
                ->where('authority_appointment_id', $appointmentId)
                ->orderBy('effective_at')
                ->orderBy('id')
                ->pluck('event_type')
                ->all(),
        );
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            'actor_user_id' => LocalLplPilotAuthorityBootstrapper::USER_ID,
            'actor_membership_id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
            'action' => 'lpl01.pilot_authority_bootstrapped',
            'subject_type' => 'program',
            'subject_id' => $programId,
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            'event_type' => 'lpl01.pilot_authority_bootstrapped',
            'aggregate_type' => 'program',
            'aggregate_id' => $programId,
        ]);
        self::assertSame($before['users'], DB::table('users')->count());
        self::assertSame($before['memberships'], DB::table('memberships')->count());
        self::assertSame($before['user_identities'], DB::table('user_identities')->count());
        self::assertSame($before['media_assets'], DB::table('media_assets')->count());
        self::assertSame($before['media_rights_revisions'], DB::table('media_rights_revisions')->count());

        self::assertSame(
            0,
            Artisan::call(
                'lpl:bootstrap-local-pilot-authority',
                $this->arguments($idempotencyKey),
            ),
        );
        /** @var array<string, mixed> $replay */
        $replay = json_decode(
            trim(Artisan::output()),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('replayed', $replay['status'] ?? null);
        self::assertSame($programId, $replay['program_id'] ?? null);
        self::assertSame($roleId, $replay['role_id'] ?? null);
        self::assertSame($appointmentId, $replay['appointment_id'] ?? null);
        $this->assertDatabaseCount('authority_appointments', 1);
        $this->assertDatabaseCount('media_rights_revisions', 0);

        foreach (
            ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level
        ) {
            $log->shouldNotHaveReceived($level);
        }
    }

    public function test_environment_switch_references_identifiers_and_asset_state_fail_closed(): void
    {
        $bootstrapper = $this->app->make(
            LocalLplPilotAuthorityBootstrapper::class,
        );
        $cases = [
            ['environment' => 'production'],
            ['driver' => 'provider'],
            ['enabled' => false],
            ['organization' => (string) Str::ulid()],
            ['user' => (string) Str::ulid()],
            ['membership' => (string) Str::ulid()],
            ['approval' => 'APP-OTHER'],
            ['owner_evidence' => 'APP-OTHER'],
            ['idempotency_key' => 'not-an-idempotency-key'],
        ];

        foreach ($cases as $case) {
            $this->app['env'] = $case['environment'] ?? 'local';
            config([
                'authentication.otp.driver' => $case['driver'] ?? 'local',
                'content_governance.local_lpl_pilot_authority_bootstrap_enabled' => $case['enabled'] ?? true,
            ]);
            $this->expectRejection($bootstrapper, $case);
        }

        $this->app['env'] = 'local';
        config([
            'authentication.otp.driver' => 'local',
            'content_governance.local_lpl_pilot_authority_bootstrap_enabled' => true,
        ]);
        DB::table('media_asset_lifecycles')
            ->where('media_asset_id', '01KZNFYHCJSDWSPK08ZAC7G3AJ')
            ->update([
                'state' => 'retired',
                'version' => 2,
                'retired_by_membership_id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
                'retirement_reason_key' => 'created_in_error',
                'retired_at' => now(),
                'updated_at' => now(),
            ]);
        $this->expectRejection($bootstrapper, []);

        $this->assertDatabaseMissing('programs', [
            'key' => LocalLplPilotAuthorityBootstrapper::PROGRAM_KEY,
        ]);
        $this->assertDatabaseMissing('roles', [
            'key' => LocalLplPilotAuthorityBootstrapper::ROLE_KEY,
        ]);
        $this->assertDatabaseCount('authority_appointments', 0);
    }

    public function test_replay_rejects_authority_state_drift(): void
    {
        $idempotencyKey = (string) Str::ulid();
        $bootstrapper = $this->app->make(
            LocalLplPilotAuthorityBootstrapper::class,
        );
        $result = $bootstrapper->bootstrap(
            LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            LocalLplPilotAuthorityBootstrapper::USER_ID,
            LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
            'APP-0051',
            'APP-0047',
            $idempotencyKey,
        );
        $extraPermissionId = DB::table('permissions')
            ->where('key', 'media.library.read')
            ->value('id');
        self::assertIsString($extraPermissionId);
        DB::table('role_permissions')->insert([
            'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            'role_id' => $result->roleId,
            'permission_id' => $extraPermissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(LocalLplPilotAuthorityBootstrapRejected::class);
        $bootstrapper->bootstrap(
            LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            LocalLplPilotAuthorityBootstrapper::USER_ID,
            LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
            'APP-0051',
            'APP-0047',
            $idempotencyKey,
        );
    }

    public function test_command_definition_exposes_only_exact_identifier_and_evidence_inputs(): void
    {
        $definition = Artisan::all()['lpl:bootstrap-local-pilot-authority']
            ->getDefinition();

        foreach ([
            'organization',
            'user',
            'membership',
            'approval',
            'owner-evidence',
            'idempotency-key',
        ] as $option) {
            self::assertTrue($definition->hasOption($option));
        }

        foreach (['program', 'role', 'permission', 'asset', 'hash'] as $option) {
            self::assertFalse($definition->hasOption($option));
        }
    }

    private function insertExactDirectOrganization(): void
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
    }

    private function insertExactActorAndCleanAssets(): void
    {
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
            $uploadId = (string) Str::ulid();
            $candidateAssetId = (string) Str::ulid();
            $objectKey = 'private/lpl01-test-'.($index + 1);
            DB::table('media_assets')->insert([
                'id' => $assetId,
                'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
                'key' => 'lpl01-test-'.($index + 1),
                'asset_kind' => 'lesson_audio',
                'storage_object_key' => $objectKey,
                'mime_type' => 'audio/mpeg',
                'byte_size' => $bytes,
                'asset_hash' => $hash,
                'uploaded_by_membership_id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
                'uploader_attribution' => 'test',
                'uploaded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('media_uploads')->insert([
                'id' => $uploadId,
                'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
                'candidate_asset_id' => $candidateAssetId,
                'asset_kind' => 'lesson_audio',
                'safe_label' => 'lpl01-test-'.($index + 1),
                'state' => 'scan_clean',
                'version' => 3,
                'total_bytes' => $bytes,
                'whole_object_hash' => $hash,
                'part_size_bytes' => $bytes,
                'expected_part_count' => 1,
                'received_part_count' => 1,
                'quarantine_object_key' => null,
                'accepted_object_key' => $objectKey,
                'selected_scan_id' => null,
                'accepted_asset_id' => null,
                'created_by_membership_id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
                'decided_by_membership_id' => null,
                'decision_reason_key' => null,
                'expires_at' => now()->addHour(),
                'review_expires_at' => null,
                'decided_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('media_security_scans')->insert([
                'id' => $scanId,
                'organization_id' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
                'media_upload_id' => $uploadId,
                'media_asset_id' => null,
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
            DB::table('media_uploads')
                ->where('id', $uploadId)
                ->update([
                    'state' => 'accepted',
                    'version' => 4,
                    'selected_scan_id' => $scanId,
                    'accepted_asset_id' => $assetId,
                    'decided_by_membership_id' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
                    'decided_at' => now(),
                    'updated_at' => now(),
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

    /** @return array<string, string> */
    private function arguments(string $idempotencyKey): array
    {
        return [
            '--organization' => LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
            '--user' => LocalLplPilotAuthorityBootstrapper::USER_ID,
            '--membership' => LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
            '--approval' => 'APP-0051',
            '--owner-evidence' => 'APP-0047',
            '--idempotency-key' => $idempotencyKey,
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function expectRejection(
        LocalLplPilotAuthorityBootstrapper $bootstrapper,
        array $overrides,
    ): void {
        try {
            $bootstrapper->bootstrap(
                expectedOrganizationId: $overrides['organization'] ?? LocalLplPilotAuthorityBootstrapper::ORGANIZATION_ID,
                expectedUserId: $overrides['user'] ?? LocalLplPilotAuthorityBootstrapper::USER_ID,
                expectedMembershipId: $overrides['membership'] ?? LocalLplPilotAuthorityBootstrapper::MEMBERSHIP_ID,
                approvalReference: $overrides['approval'] ?? 'APP-0051',
                ownerEvidenceReference: $overrides['owner_evidence'] ?? 'APP-0047',
                idempotencyKey: $overrides['idempotency_key'] ?? (string) Str::ulid(),
            );
            self::fail('Expected the local LPL authority bootstrap to reject.');
        } catch (LocalLplPilotAuthorityBootstrapRejected $exception) {
            self::assertSame(
                'Local LPL pilot authority bootstrap rejected.',
                $exception->getMessage(),
            );
        }
    }

    /** @return array<string, int> */
    private function protectedCounts(): array
    {
        return collect([
            'users',
            'user_identities',
            'memberships',
            'media_assets',
            'media_rights_revisions',
        ])->mapWithKeys(static fn (string $table): array => [
            $table => DB::table($table)->count(),
        ])->all();
    }
}
