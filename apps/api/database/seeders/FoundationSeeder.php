<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class FoundationSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const array PERMISSIONS = [
        'cohorts.manage' => 'Create and manage organization cohorts.',
        'cohorts.read' => 'View organization cohorts.',
        'enrollments.manage' => 'Create and manage organization enrollments.',
        'enrollments.read' => 'View organization enrollments.',
        'institution_invites.manage' => 'Issue and revoke institution invitations.',
        'memberships.manage' => 'Manage organization memberships.',
        'memberships.read' => 'View organization memberships.',
        'roles.manage' => 'Manage organization roles and permission grants.',
    ];

    public function run(): void
    {
        $now = now();

        DB::table('organizations')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'system_key' => Organization::DIRECT_SYSTEM_KEY,
            'name' => 'Global English Direct',
            'kind' => Organization::KIND_PLATFORM_DIRECT,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $organizationId = DB::table('organizations')
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->value('id');

        if (! is_string($organizationId)) {
            throw new RuntimeException('Unable to seed the Global English Direct organization.');
        }

        foreach (self::PERMISSIONS as $key => $description) {
            DB::table('permissions')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'key' => $key,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('roles')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'key' => Role::DIRECT_LEARNER_KEY,
            'name' => 'Learner',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
