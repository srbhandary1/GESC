<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class OrganizationAdministrationEventRecorder
{
    /**
     * @var list<string>
     */
    private const array EVENT_TYPES = [
        'branch.created',
        'branch.updated',
        'branch.archived',
        'cohort.created',
        'cohort.updated',
        'cohort.archived',
        'invitation.issued',
        'invitation.revoked',
        'role.created',
        'role.updated',
        'role.retired',
        'role.permission_granted',
        'role.permission_revoked',
        'membership.role_assigned',
        'membership.role_revoked',
        'membership.suspended',
        'membership.reactivated',
        'trainer.cohort_assigned',
        'trainer.cohort_revoked',
        'membership_session.revoked',
        'learner_goal.corrected',
        'learner_enrollment.created',
        'learner_enrollment.cohort_changed',
        'learner_enrollment.curriculum_activated',
        'learner_enrollment.withdrawn',
        'learner_membership.suspended',
        'learner_membership.reactivated',
        'learner_membership.role_removed',
        'learner_invitation.issued',
        'learner_invitation.revoked',
    ];

    /**
     * @param  array{
     *     membership_id?: string,
     *     role_id?: string,
     *     cohort_id?: string,
     *     branch_id?: string,
     *     invitation_id?: string,
     *     learner_profile_id?: string,
     *     enrollment_id?: string,
     *     permission_id?: string
     * }  $subjects
     */
    public function record(
        string $organizationId,
        string $actorMembershipId,
        string $commandId,
        string $eventType,
        array $subjects,
        CarbonInterface $effectiveAt,
        ?string $reasonKey = null,
    ): string {
        if (! in_array($eventType, self::EVENT_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported organization administration event type.');
        }

        $eventId = (string) Str::ulid();
        DB::table('organization_administration_events')->insert([
            'id' => $eventId,
            'organization_id' => $organizationId,
            'actor_membership_id' => $actorMembershipId,
            'event_type' => $eventType,
            'subject_membership_id' => $subjects['membership_id'] ?? null,
            'subject_role_id' => $subjects['role_id'] ?? null,
            'subject_cohort_id' => $subjects['cohort_id'] ?? null,
            'subject_branch_id' => $subjects['branch_id'] ?? null,
            'subject_invitation_id' => $subjects['invitation_id'] ?? null,
            'subject_learner_profile_id' => $subjects['learner_profile_id'] ?? null,
            'subject_enrollment_id' => $subjects['enrollment_id'] ?? null,
            'permission_id' => $subjects['permission_id'] ?? null,
            'command_id' => $commandId,
            'reason_key' => $reasonKey,
            'effective_at' => $effectiveAt,
            'created_at' => $effectiveAt,
        ]);

        return $eventId;
    }
}
