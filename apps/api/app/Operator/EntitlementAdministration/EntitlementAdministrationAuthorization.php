<?php

declare(strict_types=1);

namespace App\Operator\EntitlementAdministration;

use App\Models\User;
use App\Operator\EntitlementAdministration\Exceptions\EntitlementAdministrationRejected;
use App\Operator\OrganizationAdministration\OrganizationAdministrationAuthorization;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Collection;
use stdClass;

final readonly class EntitlementAdministrationAuthorization
{
    public const string PLANS_READ = 'entitlement_plans.read';

    public const string PLANS_MANAGE = 'entitlement_plans.manage';

    public const string ENTITLEMENTS_READ = 'entitlements.read';

    public const string ENTITLEMENTS_GRANT = 'entitlements.grant';

    public const string ENTITLEMENTS_ADJUST = 'entitlements.adjust';

    public const string ENTITLEMENTS_REVOKE = 'entitlements.revoke';

    public function __construct(
        private OrganizationAuthorization $authorization,
        private OrganizationAdministrationAuthorization $organizationAdministration,
    ) {}

    public function allows(User $actor, string $permission): bool
    {
        return $this->authorization->allows($actor, $permission);
    }

    public function assert(User $actor, string $permission): void
    {
        if (! $this->allows($actor, $permission)) {
            throw new EntitlementAdministrationRejected;
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    public function assertAll(User $actor, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->assert($actor, $permission);
        }
    }

    public function contextFor(User $actor): OrganizationContext
    {
        return $this->organizationAdministration->contextFor($actor);
    }

    public function actorMembership(User $actor, bool $lock = false): stdClass
    {
        return $this->organizationAdministration->actorMembership($actor, $lock);
    }

    /**
     * @return Collection<int, stdClass>
     */
    public function permissionRows(User $actor, bool $lock = false): Collection
    {
        return $this->organizationAdministration->permissionRows($actor, $lock);
    }
}
