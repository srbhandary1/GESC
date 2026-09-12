<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration;

use App\Models\Membership;
use App\Models\User;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationRejected;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\PermissionCatalogue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class OrganizationAdministrationAuthorization
{
    public const string ORGANIZATION_READ = 'organization.settings.read';

    public const string BRANCHES_READ = 'branches.read';

    public const string BRANCHES_MANAGE = 'branches.manage';

    public const string COHORTS_READ = 'cohorts.read';

    public const string COHORTS_MANAGE = 'cohorts.manage';

    public const string MEMBERSHIPS_READ = 'memberships.read';

    public const string MEMBERSHIPS_MANAGE = 'memberships.manage';

    public const string INVITATIONS_MANAGE = 'institution_invites.manage';

    public const string ROLES_MANAGE = 'roles.manage';

    public const string TRAINERS_READ = 'trainer_profiles.read';

    public const string TRAINERS_MANAGE = 'trainer_profiles.manage';

    public const string APPOINTMENTS_MANAGE = 'curriculum.authority.manage';

    public const string MEMBERSHIP_SESSIONS_REVOKE = 'membership_sessions.revoke';

    /**
     * @var list<string>
     */
    public const array PEOPLE_PANEL_PERMISSIONS = [
        self::ORGANIZATION_READ,
        self::BRANCHES_READ,
        self::COHORTS_READ,
        self::MEMBERSHIPS_READ,
        self::INVITATIONS_MANAGE,
        self::ROLES_MANAGE,
        self::TRAINERS_READ,
        self::APPOINTMENTS_MANAGE,
        self::MEMBERSHIP_SESSIONS_REVOKE,
    ];

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
    ) {}

    public function allows(User $actor, string $permission): bool
    {
        return $this->authorization->allows($actor, $permission);
    }

    public function assert(User $actor, string $permission): void
    {
        if (! $this->allows($actor, $permission)) {
            throw new OrganizationAdministrationRejected;
        }
    }

    public function contextFor(User $actor): OrganizationContext
    {
        $context = $this->contexts->current();

        if (
            $context->membershipId === null
            || ! hash_equals($context->actorUserId, (string) $actor->getKey())
            || $actor->status !== User::STATUS_ACTIVE
        ) {
            throw new OrganizationAdministrationRejected;
        }

        return $context;
    }

    public function actorMembership(User $actor, bool $lock = false): stdClass
    {
        $context = $this->contextFor($actor);
        $query = DB::table('memberships')
            ->where('organization_id', $context->organizationId)
            ->where('id', $context->membershipId)
            ->where('user_id', $actor->getKey())
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereIn('membership_type', [
                Membership::TYPE_TRAINER,
                Membership::TYPE_ORGANIZATION_ADMIN,
            ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        $membership = $query->first([
            'id',
            'organization_id',
            'user_id',
            'membership_type',
            'status',
            'version',
        ]);

        if (! $membership instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        return $membership;
    }

    /**
     * @return Collection<int, stdClass>
     */
    public function permissionRows(User $actor, bool $lock = false): Collection
    {
        $membership = $this->actorMembership($actor, $lock);
        $query = DB::table('membership_roles')
            ->join('roles', function ($join): void {
                $join
                    ->on('roles.organization_id', '=', 'membership_roles.organization_id')
                    ->on('roles.id', '=', 'membership_roles.role_id');
            })
            ->join('role_permissions', function ($join): void {
                $join
                    ->on('role_permissions.organization_id', '=', 'roles.organization_id')
                    ->on('role_permissions.role_id', '=', 'roles.id');
            })
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('membership_roles.organization_id', $membership->organization_id)
            ->where('membership_roles.membership_id', $membership->id)
            ->where('roles.status', 'active')
            ->whereIn('permissions.key', PermissionCatalogue::keys())
            ->orderBy('permissions.key')
            ->orderBy('permissions.id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get([
            'permissions.id',
            'permissions.key',
            'permissions.description',
        ])->unique('id')->values();
    }

    public function hasSelfAppointment(User $actor): bool
    {
        $context = $this->contextFor($actor);

        return DB::table('authority_appointments')
            ->where('organization_id', $context->organizationId)
            ->where('appointee_membership_id', $context->membershipId)
            ->where('appointee_user_id', $actor->getKey())
            ->exists();
    }
}
