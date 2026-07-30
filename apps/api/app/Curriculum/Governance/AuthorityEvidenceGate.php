<?php

declare(strict_types=1);

namespace App\Curriculum\Governance;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class AuthorityEvidenceGate
{
    public function __construct(
        private OrganizationContextManager $contexts,
    ) {}

    public function acceptedForNewEvidence(
        User $actor,
        string $appointmentId,
        AuthorityKind $authorityKind,
        ?string $programId,
        CarbonInterface $at,
    ): AcceptedAuthorityAppointment {
        GovernanceInput::ulid($appointmentId);

        if ($programId !== null) {
            GovernanceInput::ulid($programId);
        }

        $context = $this->contexts->current();
        $appointment = DB::table('authority_appointments')
            ->where('organization_id', $context->organizationId)
            ->where('id', $appointmentId)
            ->where('authority_kind', $authorityKind->value)
            ->where('valid_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query
                    ->whereNull('valid_until')
                    ->orWhere('valid_until', '>', $at);
            })
            ->lockForUpdate()
            ->first([
                'appointee_user_id',
                'appointee_membership_id',
                'scope_type',
                'program_id',
            ]);

        if (
            ! $appointment instanceof stdClass
            || ! is_string($appointment->appointee_user_id)
            || ! is_string($appointment->appointee_membership_id)
            || ! is_string($appointment->scope_type)
            || $context->membershipId === null
            || ! hash_equals(
                (string) $actor->getKey(),
                $context->actorUserId,
            )
            || ! hash_equals(
                $appointment->appointee_user_id,
                (string) $actor->getKey(),
            )
            || ! hash_equals(
                $appointment->appointee_membership_id,
                $context->membershipId,
            )
            || ! $this->scopeAllows(
                $appointment->scope_type,
                is_string($appointment->program_id)
                    ? $appointment->program_id
                    : null,
                $programId,
            )
            || ! $this->appointeeIsActive(
                $context->organizationId,
                $appointment->appointee_user_id,
                $appointment->appointee_membership_id,
            )
        ) {
            throw new CurriculumOperationRejected;
        }

        $acceptance = DB::table('authority_appointment_events')
            ->where('organization_id', $context->organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->where('event_type', 'accepted')
            ->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first(['id']);
        $terminated = DB::table('authority_appointment_events')
            ->where('organization_id', $context->organizationId)
            ->where('authority_appointment_id', $appointmentId)
            ->whereIn(
                'event_type',
                [
                    'surrendered',
                    'revoked',
                    'revoked_for_cause',
                    'expired',
                ],
            )
            ->where('effective_at', '<=', $at)
            ->exists();

        if (
            ! $acceptance instanceof stdClass
            || ! is_string($acceptance->id)
            || $terminated
        ) {
            throw new CurriculumOperationRejected;
        }

        return new AcceptedAuthorityAppointment(
            appointmentId: $appointmentId,
            acceptanceEventId: $acceptance->id,
            appointeeMembershipId: $appointment->appointee_membership_id,
        );
    }

    private function appointeeIsActive(
        string $organizationId,
        string $userId,
        string $membershipId,
    ): bool {
        return DB::table('memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.organization_id', $organizationId)
            ->where('memberships.id', $membershipId)
            ->where('memberships.user_id', $userId)
            ->where('memberships.status', Membership::STATUS_ACTIVE)
            ->where('users.status', User::STATUS_ACTIVE)
            ->exists();
    }

    private function scopeAllows(
        string $scopeType,
        ?string $appointmentProgramId,
        ?string $requiredProgramId,
    ): bool {
        if ($scopeType === 'organization') {
            return $appointmentProgramId === null;
        }

        return $scopeType === 'program'
            && $appointmentProgramId !== null
            && $requiredProgramId !== null
            && hash_equals($appointmentProgramId, $requiredProgramId);
    }
}
