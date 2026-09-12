<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Tenancy\OrganizationOwned;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $actor_membership_id
 * @property string $event_type
 * @property string|null $subject_membership_id
 * @property string|null $subject_role_id
 * @property string|null $subject_cohort_id
 * @property string|null $subject_branch_id
 * @property string|null $subject_invitation_id
 * @property string|null $subject_learner_profile_id
 * @property string|null $subject_enrollment_id
 * @property string|null $permission_id
 * @property string|null $command_id
 * @property string|null $reason_key
 * @property CarbonImmutable $effective_at
 * @property CarbonImmutable $created_at
 */
final class OrganizationAdministrationEvent extends Model implements OrganizationOwned
{
    use BelongsToOrganization, HasUlids;

    public const null UPDATED_AT = null;

    public const string BRANCH_CREATED = 'branch.created';

    public const string BRANCH_UPDATED = 'branch.updated';

    public const string BRANCH_ARCHIVED = 'branch.archived';

    public const string COHORT_CREATED = 'cohort.created';

    public const string COHORT_UPDATED = 'cohort.updated';

    public const string COHORT_ARCHIVED = 'cohort.archived';

    public const string INVITATION_ISSUED = 'invitation.issued';

    public const string INVITATION_REVOKED = 'invitation.revoked';

    public const string ROLE_CREATED = 'role.created';

    public const string ROLE_UPDATED = 'role.updated';

    public const string ROLE_RETIRED = 'role.retired';

    public const string ROLE_PERMISSION_GRANTED = 'role.permission_granted';

    public const string ROLE_PERMISSION_REVOKED = 'role.permission_revoked';

    public const string MEMBERSHIP_ROLE_ASSIGNED = 'membership.role_assigned';

    public const string MEMBERSHIP_ROLE_REVOKED = 'membership.role_revoked';

    public const string MEMBERSHIP_SUSPENDED = 'membership.suspended';

    public const string MEMBERSHIP_REACTIVATED = 'membership.reactivated';

    public const string TRAINER_COHORT_ASSIGNED = 'trainer.cohort_assigned';

    public const string TRAINER_COHORT_REVOKED = 'trainer.cohort_revoked';

    public const string MEMBERSHIP_SESSION_REVOKED = 'membership_session.revoked';

    public const string LEARNER_GOAL_CORRECTED = 'learner_goal.corrected';

    public const string LEARNER_ENROLLMENT_CREATED = 'learner_enrollment.created';

    public const string LEARNER_ENROLLMENT_COHORT_CHANGED = 'learner_enrollment.cohort_changed';

    public const string LEARNER_ENROLLMENT_CURRICULUM_ACTIVATED = 'learner_enrollment.curriculum_activated';

    public const string LEARNER_ENROLLMENT_WITHDRAWN = 'learner_enrollment.withdrawn';

    public const string LEARNER_MEMBERSHIP_SUSPENDED = 'learner_membership.suspended';

    public const string LEARNER_MEMBERSHIP_REACTIVATED = 'learner_membership.reactivated';

    public const string LEARNER_MEMBERSHIP_ROLE_REMOVED = 'learner_membership.role_removed';

    public const string LEARNER_INVITATION_ISSUED = 'learner_invitation.issued';

    public const string LEARNER_INVITATION_REVOKED = 'learner_invitation.revoked';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
