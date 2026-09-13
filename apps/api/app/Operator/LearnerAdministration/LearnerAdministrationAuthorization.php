<?php

declare(strict_types=1);

namespace App\Operator\LearnerAdministration;

use App\Models\User;
use App\Operator\LearnerAdministration\Exceptions\LearnerAdministrationRejected;
use App\Operator\OrganizationAdministration\OrganizationAdministrationAuthorization;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Collection;
use stdClass;

final readonly class LearnerAdministrationAuthorization
{
    public const string LEARNERS_READ = 'learners.read';

    public const string PROFILE_CORRECT = 'learners.profile.correct';

    public const string LIFECYCLE_MANAGE = 'learners.lifecycle.manage';

    public const string ENROLLMENTS_READ = 'enrollments.read';

    public const string ENROLLMENTS_MANAGE = 'enrollments.manage';

    public const string CURRICULUM_ASSIGN = 'enrollments.curriculum.assign';

    public const string INVITATIONS_MANAGE = 'institution_invites.manage';

    public const string ROLES_MANAGE = 'roles.manage';

    public const string COHORTS_READ = 'cohorts.read';

    public const string TRAINERS_READ = 'trainer_profiles.read';

    /**
     * @var list<string>
     */
    public const array INVITATION_PERMISSIONS = [
        self::LIFECYCLE_MANAGE,
        self::INVITATIONS_MANAGE,
        self::ROLES_MANAGE,
        self::COHORTS_READ,
    ];

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
            throw new LearnerAdministrationRejected;
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
