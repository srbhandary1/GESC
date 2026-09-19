<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resolves installation-local identifiers for the approved Direct pathway.
 *
 * The natural keys are authoritative; ULIDs are deliberately not portable
 * between the workstation, restored evidence databases and production.
 */
final class DirectPathwayScopeResolver
{
    public const string PROGRAM_KEY = 'global-spoken-english-lpl-pilot';

    public const string CURRICULUM_VERSION_KEY = 'lpl-pilot-private-draft-v1';

    public const string COMMISSIONER_ROLE_KEY = 'lpl-pilot-commissioner';

    /**
     * @return array{
     *   organization_id: string,
     *   program_id: string,
     *   curriculum_version_id: string,
     *   actor_membership_id: string,
     *   actor_user_id: string
     * }
     */
    public function resolve(): array
    {
        $organizationId = (string) DB::table('organizations')
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->where('kind', Organization::KIND_PLATFORM_DIRECT)
            ->where('status', Organization::STATUS_ACTIVE)
            ->value('id');
        $programId = $organizationId === '' ? '' : (string) DB::table('programs')
            ->where('organization_id', $organizationId)
            ->where('key', self::PROGRAM_KEY)
            ->value('id');
        $curriculumVersionId = $programId === '' ? '' : (string) DB::table('curriculum_versions')
            ->where('organization_id', $organizationId)
            ->where('program_id', $programId)
            ->where('key', self::CURRICULUM_VERSION_KEY)
            ->value('id');
        $roleId = $organizationId === '' ? '' : (string) DB::table('roles')
            ->where('organization_id', $organizationId)
            ->where('key', self::COMMISSIONER_ROLE_KEY)
            ->where('status', Role::STATUS_ACTIVE)
            ->value('id');
        $membership = $roleId === '' ? null : DB::table('memberships')
            ->join('membership_roles', function ($join): void {
                $join->on('membership_roles.organization_id', '=', 'memberships.organization_id')
                    ->on('membership_roles.membership_id', '=', 'memberships.id');
            })
            ->where('memberships.organization_id', $organizationId)
            ->where('membership_roles.role_id', $roleId)
            ->where('memberships.membership_type', Membership::TYPE_ORGANIZATION_ADMIN)
            ->where('memberships.status', Membership::STATUS_ACTIVE)
            ->select(['memberships.id', 'memberships.user_id'])
            ->first();

        $membershipId = is_object($membership) ? (string) $membership->id : '';
        $userId = is_object($membership) ? (string) $membership->user_id : '';
        if (
            $organizationId === ''
            || $programId === ''
            || $curriculumVersionId === ''
            || $membershipId === ''
            || $userId === ''
        ) {
            throw new RuntimeException('direct_pathway_scope_missing');
        }

        return [
            'organization_id' => $organizationId,
            'program_id' => $programId,
            'curriculum_version_id' => $curriculumVersionId,
            'actor_membership_id' => $membershipId,
            'actor_user_id' => $userId,
        ];
    }
}
