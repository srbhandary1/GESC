<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\LessonPractice\DirectPathwayCommissioningAuditService;
use App\Curriculum\LessonPractice\DirectPathwayScopeResolver;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DirectPathwayCommissioningAuditTest extends TenancyIntegrationTestCase
{
    public function test_scope_resolver_uses_direct_natural_keys_not_restored_ulids(): void
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->firstOrFail();
        $user = $this->createUser();
        [$membership] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $organizationId = (string) $organization->getKey();
        $programId = (string) Str::ulid();
        $versionId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        $now = CarbonImmutable::now();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => DirectPathwayScopeResolver::PROGRAM_KEY,
            'internal_name' => 'Global Spoken English LPL pilot',
            'created_by_membership_id' => (string) $membership->getKey(),
            'created_by_attribution' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'key' => DirectPathwayScopeResolver::CURRICULUM_VERSION_KEY,
            'created_by_membership_id' => (string) $membership->getKey(),
            'created_by_attribution' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $organizationId,
            'key' => DirectPathwayScopeResolver::COMMISSIONER_ROLE_KEY,
            'name' => 'LPL Pilot Commissioner',
            'is_system' => false,
            'status' => Role::STATUS_ACTIVE,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('membership_roles')->insert([
            'organization_id' => $organizationId,
            'membership_id' => (string) $membership->getKey(),
            'role_id' => $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $scope = app(DirectPathwayScopeResolver::class)->resolve();

        self::assertSame($organizationId, $scope['organization_id']);
        self::assertSame($programId, $scope['program_id']);
        self::assertSame($versionId, $scope['curriculum_version_id']);
        self::assertSame((string) $membership->getKey(), $scope['actor_membership_id']);
        self::assertSame((string) $user->getKey(), $scope['actor_user_id']);
    }

    public function test_audit_reports_commissioning_pending_when_direct_program_has_no_publications(): void
    {
        $organization = Organization::query()
            ->where('system_key', 'global-english-direct')
            ->firstOrFail();
        [$membership] = $this->createMembership(
            $organization,
            $this->createUser(),
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $organizationId = (string) $organization->getKey();
        $programId = (string) Str::ulid();
        $now = CarbonImmutable::now();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => 'global-spoken-english-lpl-pilot',
            'internal_name' => 'Global Spoken English LPL pilot',
            'created_by_membership_id' => (string) $membership->getKey(),
            'created_by_attribution' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $result = app(DirectPathwayCommissioningAuditService::class)->audit();

        self::assertSame('LPL-05', $result['checkpoint']);
        self::assertSame('WAVE-7.1', $result['approval_reference']);
        self::assertSame($organizationId, $result['organization_id']);
        self::assertSame($programId, $result['program_id']);
        self::assertSame(17, $result['levels_configured']);
        self::assertFalse($result['all_complete']);
        self::assertSame(0, $result['levels_complete']);
        self::assertSame(
            17,
            count(array_filter(
                $result['levels'],
                static fn (array $row): bool => ($row['availability'] ?? '') === 'commissioning_pending',
            )),
        );
        self::assertSame(
            'blueprint_not_published',
            $result['levels'][0]['unavailable_reason'] ?? null,
        );
        self::assertFalse($result['waves']['wave_1_level_1']['all_complete']);
        self::assertFalse($result['waves']['wave_2_levels_2_3']['all_complete']);
        self::assertFalse($result['waves']['wave_3_levels_4_17']['all_complete']);
    }
}
