<?php

declare(strict_types=1);

namespace App\Curriculum\Governance;

use App\Audit\AuditEventRecorder;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use App\Models\User;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationAuthorization;
use App\Tenancy\OrganizationContextManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class AuthorityAppointmentCommands
{
    private const string MANAGE_PERMISSION = 'curriculum.authority.manage';

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAuthorization $authorization,
        private AuthenticationSessionService $authenticationSessions,
        private GovernanceCommandIdempotency $idempotency,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function appoint(
        User $actor,
        string $appointeeUserId,
        string $appointeeMembershipId,
        AuthorityKind $authorityKind,
        string $scopeType,
        ?string $programId,
        CarbonInterface $validFrom,
        ?CarbonInterface $validUntil,
        string $qualificationBasis,
        string $approvalEvidenceKey,
        string $idempotencyKey,
    ): string {
        GovernanceInput::ulid($appointeeUserId);
        GovernanceInput::ulid($appointeeMembershipId);
        $qualificationBasis = GovernanceInput::text(
            $qualificationBasis,
            1_000,
        );
        $approvalEvidenceKey = GovernanceInput::evidenceReference(
            $approvalEvidenceKey,
        );
        GovernanceInput::idempotencyKey($idempotencyKey);
        $programId = $this->validatedScope($scopeType, $programId);

        if (
            $validUntil !== null
            && $validUntil->lessThanOrEqualTo($validFrom)
        ) {
            throw new CurriculumOperationRejected;
        }

        return DB::transaction(function () use (
            $actor,
            $appointeeUserId,
            $appointeeMembershipId,
            $authorityKind,
            $scopeType,
            $programId,
            $validFrom,
            $validUntil,
            $qualificationBasis,
            $approvalEvidenceKey,
            $idempotencyKey,
        ): string {
            $this->assertManager($actor);
            $context = $this->contexts->current();

            if (
                hash_equals((string) $actor->getKey(), $appointeeUserId)
                && ! $this->trustedConsolidatedSelfAppointment(
                    actorUserId: (string) $actor->getKey(),
                    authorityKind: $authorityKind,
                    qualificationBasis: $qualificationBasis,
                    approvalEvidenceKey: $approvalEvidenceKey,
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $appointeeExists = DB::table('memberships')
                ->join('users', 'users.id', '=', 'memberships.user_id')
                ->where('memberships.organization_id', $context->organizationId)
                ->where('memberships.id', $appointeeMembershipId)
                ->where('memberships.user_id', $appointeeUserId)
                ->where('memberships.status', Membership::STATUS_ACTIVE)
                ->where('users.status', User::STATUS_ACTIVE)
                ->lockForUpdate()
                ->exists();

            if (
                ! $appointeeExists
                || (
                    $programId !== null
                    && ! DB::table('programs')
                        ->where('organization_id', $context->organizationId)
                        ->where('id', $programId)
                        ->lockForUpdate()
                        ->exists()
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $now = Date::now();
            $operation = 'curriculum.authority.appoint';
            $fingerprint = $this->idempotency->fingerprint([
                'appointee_membership_id' => $appointeeMembershipId,
                'appointee_user_id' => $appointeeUserId,
                'approval_evidence_key' => $approvalEvidenceKey,
                'authority_kind' => $authorityKind->value,
                'operation' => $operation,
                'program_id' => $programId,
                'qualification_basis' => $qualificationBasis,
                'scope_type' => $scopeType,
                'valid_from' => $this->instant($validFrom),
                'valid_until' => $validUntil === null
                    ? null
                    : $this->instant($validUntil),
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointee',
                aggregateId: $appointeeMembershipId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            $appointmentId = (string) Str::ulid();
            $eventId = (string) Str::ulid();

            DB::table('authority_appointments')->insert([
                'id' => $appointmentId,
                'organization_id' => $context->organizationId,
                'appointee_user_id' => $appointeeUserId,
                'appointee_membership_id' => $appointeeMembershipId,
                'authority_kind' => $authorityKind->value,
                'scope_type' => $scopeType,
                'program_id' => $programId,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'qualification_basis' => $qualificationBasis,
                'approval_evidence_key' => $approvalEvidenceKey,
                'appointed_by_membership_id' => $context->membershipId,
                'appointed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertEvent(
                eventId: $eventId,
                appointmentId: $appointmentId,
                eventType: 'appointed',
                actorMembershipId: (string) $context->membershipId,
                effectiveAt: $now,
            );
            $this->recordEvidence(
                action: 'curriculum.authority.appointed',
                eventType: 'curriculum.authority.appointed',
                appointmentId: $appointmentId,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointee',
                aggregateId: $appointeeMembershipId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'authority_appointment',
                resultId: $appointmentId,
                completedAt: $now,
            );

            return $appointmentId;
        }, 3);
    }

    public function accept(
        User $actor,
        string $appointmentId,
        string $idempotencyKey,
    ): string {
        GovernanceInput::ulid($appointmentId);
        GovernanceInput::idempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $appointmentId,
            $idempotencyKey,
        ): string {
            $context = $this->contexts->current();
            $appointment = $this->lockedAppointment($appointmentId);
            $now = Date::now();

            if (
                $context->membershipId === null
                || ! hash_equals((string) $actor->getKey(), $context->actorUserId)
                || ! hash_equals(
                    $this->stringProperty($appointment, 'appointee_user_id'),
                    (string) $actor->getKey(),
                )
                || ! hash_equals(
                    $this->stringProperty(
                        $appointment,
                        'appointee_membership_id',
                    ),
                    $context->membershipId,
                )
                || ! $this->activeMembership(
                    $context->organizationId,
                    (string) $actor->getKey(),
                    $context->membershipId,
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $operation = 'curriculum.authority.accept';
            $fingerprint = $this->idempotency->fingerprint([
                'appointment_id' => $appointmentId,
                'operation' => $operation,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointment',
                aggregateId: $appointmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            if (
                (
                    $appointment->valid_until !== null
                    && $now->greaterThanOrEqualTo(
                        $this->dateProperty($appointment, 'valid_until'),
                    )
                )
                || $this->hasEvent(
                    $appointmentId,
                    [
                        'accepted',
                        'surrendered',
                        'revoked',
                        'revoked_for_cause',
                        'expired',
                    ],
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $eventId = (string) Str::ulid();
            $this->insertEvent(
                eventId: $eventId,
                appointmentId: $appointmentId,
                eventType: 'accepted',
                actorMembershipId: $context->membershipId,
                effectiveAt: $now,
            );
            $this->recordEvidence(
                action: 'curriculum.authority.accepted',
                eventType: 'curriculum.authority.accepted',
                appointmentId: $appointmentId,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointment',
                aggregateId: $appointmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'authority_appointment_event',
                resultId: $eventId,
                completedAt: $now,
            );

            return $eventId;
        }, 3);
    }

    public function surrender(
        User $actor,
        string $appointmentId,
        string $idempotencyKey,
    ): string {
        GovernanceInput::ulid($appointmentId);
        GovernanceInput::idempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $appointmentId,
            $idempotencyKey,
        ): string {
            $context = $this->contexts->current();
            $appointment = $this->lockedAppointment($appointmentId);

            if (
                $context->membershipId === null
                || ! hash_equals((string) $actor->getKey(), $context->actorUserId)
                || ! hash_equals(
                    $this->stringProperty($appointment, 'appointee_user_id'),
                    (string) $actor->getKey(),
                )
                || ! hash_equals(
                    $this->stringProperty(
                        $appointment,
                        'appointee_membership_id',
                    ),
                    $context->membershipId,
                )
                || ! $this->activeMembership(
                    $context->organizationId,
                    (string) $actor->getKey(),
                    $context->membershipId,
                )
                || ! $this->hasEvent($appointmentId, ['accepted'])
            ) {
                throw new CurriculumOperationRejected;
            }

            $now = Date::now();
            $operation = 'curriculum.authority.surrender';
            $fingerprint = $this->idempotency->fingerprint([
                'appointment_id' => $appointmentId,
                'operation' => $operation,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointment',
                aggregateId: $appointmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            if (
                $this->hasEvent(
                    $appointmentId,
                    [
                        'surrendered',
                        'revoked',
                        'revoked_for_cause',
                        'expired',
                    ],
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $eventId = (string) Str::ulid();
            $this->insertEvent(
                eventId: $eventId,
                appointmentId: $appointmentId,
                eventType: 'surrendered',
                actorMembershipId: $context->membershipId,
                effectiveAt: $now,
            );
            $this->recordEvidence(
                action: 'curriculum.authority.surrendered',
                eventType: 'curriculum.authority.surrendered',
                appointmentId: $appointmentId,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointment',
                aggregateId: $appointmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'authority_appointment_event',
                resultId: $eventId,
                completedAt: $now,
            );

            return $eventId;
        }, 3);
    }

    public function revoke(
        User $actor,
        string $appointmentId,
        string $reasonKey,
        string $idempotencyKey,
        ?CarbonInterface $effectiveAt = null,
    ): string {
        GovernanceInput::ulid($appointmentId);
        $reasonKey = GovernanceInput::key($reasonKey);
        GovernanceInput::idempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $appointmentId,
            $reasonKey,
            $idempotencyKey,
            $effectiveAt,
        ): string {
            $this->assertManager($actor);
            $context = $this->contexts->current();
            $this->lockedAppointment($appointmentId);
            $now = Date::now();
            $operation = 'curriculum.authority.revoke';
            $fingerprint = $this->idempotency->fingerprint([
                'appointment_id' => $appointmentId,
                'effective_at' => $effectiveAt === null
                    ? 'immediate'
                    : $this->instant($effectiveAt),
                'operation' => $operation,
                'reason_key' => $reasonKey,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointment',
                aggregateId: $appointmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            $effectiveAt ??= $now;

            if (
                $effectiveAt->lessThan($now)
                || $effectiveAt->greaterThan($now)
                || $this->hasEvent(
                    $appointmentId,
                    [
                        'surrendered',
                        'revoked',
                        'revoked_for_cause',
                        'expired',
                    ],
                )
            ) {
                throw new CurriculumOperationRejected;
            }

            $eventId = (string) Str::ulid();
            $this->insertEvent(
                eventId: $eventId,
                appointmentId: $appointmentId,
                eventType: 'revoked',
                actorMembershipId: (string) $context->membershipId,
                effectiveAt: $effectiveAt,
                reasonKey: $reasonKey,
            );
            $this->recordEvidence(
                action: 'curriculum.authority.revoked',
                eventType: 'curriculum.authority.revoked',
                appointmentId: $appointmentId,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointment',
                aggregateId: $appointmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'authority_appointment_event',
                resultId: $eventId,
                completedAt: $now,
            );

            return $eventId;
        }, 3);
    }

    public function recordExpiry(
        User $actor,
        string $appointmentId,
        string $idempotencyKey,
    ): string {
        GovernanceInput::ulid($appointmentId);
        GovernanceInput::idempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $appointmentId,
            $idempotencyKey,
        ): string {
            $this->assertManager($actor);
            $context = $this->contexts->current();
            $appointment = $this->lockedAppointment($appointmentId);

            if ($appointment->valid_until === null) {
                throw new CurriculumOperationRejected;
            }

            $validUntil = $this->dateProperty($appointment, 'valid_until');
            $now = Date::now();
            $operation = 'curriculum.authority.record_expiry';
            $fingerprint = $this->idempotency->fingerprint([
                'appointment_id' => $appointmentId,
                'effective_at' => $this->instant($validUntil),
                'operation' => $operation,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointment',
                aggregateId: $appointmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            if (
                $now->lessThan($validUntil)
                || $this->hasEvent($appointmentId, ['expired'])
            ) {
                throw new CurriculumOperationRejected;
            }

            $eventId = (string) Str::ulid();
            $this->insertEvent(
                eventId: $eventId,
                appointmentId: $appointmentId,
                eventType: 'expired',
                actorMembershipId: (string) $context->membershipId,
                effectiveAt: $validUntil,
                reasonKey: 'appointment_term_elapsed',
            );
            $this->recordEvidence(
                action: 'curriculum.authority.expired',
                eventType: 'curriculum.authority.expired',
                appointmentId: $appointmentId,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointment',
                aggregateId: $appointmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'authority_appointment_event',
                resultId: $eventId,
                completedAt: $now,
            );

            return $eventId;
        }, 3);
    }

    public function revokeForCause(
        User $actor,
        string $appointmentId,
        string $authenticationSessionId,
        string $reasonKey,
        string $idempotencyKey,
        ?CarbonInterface $effectiveAt = null,
    ): string {
        GovernanceInput::ulid($appointmentId);
        GovernanceInput::ulid($authenticationSessionId);
        $reasonKey = GovernanceInput::key($reasonKey);
        GovernanceInput::idempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $appointmentId,
            $authenticationSessionId,
            $reasonKey,
            $idempotencyKey,
            $effectiveAt,
        ): string {
            $this->assertManager($actor);
            $context = $this->contexts->current();
            $this->lockedAppointment($appointmentId);
            $now = Date::now();
            $operation = 'curriculum.authority.revoke_for_cause';
            $fingerprint = $this->idempotency->fingerprint([
                'appointment_id' => $appointmentId,
                'authentication_session_id' => $authenticationSessionId,
                'effective_at' => $effectiveAt === null
                    ? 'immediate'
                    : $this->instant($effectiveAt),
                'operation' => $operation,
                'reason_key' => $reasonKey,
            ]);
            $replay = $this->idempotency->replay(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointment',
                aggregateId: $appointmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay !== null) {
                return $replay;
            }

            $effectiveAt ??= $now;

            if (
                $effectiveAt->greaterThan($now)
                || $this->hasEvent($appointmentId, ['revoked_for_cause'])
            ) {
                throw new CurriculumOperationRejected;
            }

            $this->assertFreshPrivilegedMfa(
                $actor,
                $authenticationSessionId,
                $now,
            );

            $eventId = (string) Str::ulid();
            $this->insertEvent(
                eventId: $eventId,
                appointmentId: $appointmentId,
                eventType: 'revoked_for_cause',
                actorMembershipId: (string) $context->membershipId,
                effectiveAt: $effectiveAt,
                reasonKey: $reasonKey,
                invalidatesPriorApprovals: true,
            );
            $this->recordEvidence(
                action: 'curriculum.authority.revoked_for_cause',
                eventType: 'curriculum.authority.revoked_for_cause',
                appointmentId: $appointmentId,
            );
            $this->idempotency->record(
                actor: $actor,
                operation: $operation,
                aggregateType: 'authority_appointment',
                aggregateId: $appointmentId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'authority_appointment_event',
                resultId: $eventId,
                completedAt: $now,
            );

            return $eventId;
        }, 3);
    }

    private function validatedScope(
        string $scopeType,
        ?string $programId,
    ): ?string {
        if ($scopeType === 'organization' && $programId === null) {
            return null;
        }

        if ($scopeType === 'program' && $programId !== null) {
            return GovernanceInput::ulid($programId);
        }

        throw new CurriculumOperationRejected;
    }

    private function assertManager(User $actor): void
    {
        if (
            ! $this->authorization->allows($actor, self::MANAGE_PERMISSION)
            || ! DB::table('users')
                ->where('id', $actor->getKey())
                ->where('status', User::STATUS_ACTIVE)
                ->exists()
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function trustedConsolidatedSelfAppointment(
        string $actorUserId,
        AuthorityKind $authorityKind,
        string $qualificationBasis,
        string $approvalEvidenceKey,
    ): bool {
        $evidence = config(
            "content_governance.consolidated_self_appointments.{$approvalEvidenceKey}",
        );

        if (! is_array($evidence)) {
            return false;
        }

        $trustedUserId = $evidence['actor_user_id'] ?? null;
        $authorityKinds = $evidence['authority_kinds'] ?? null;
        $qualificationBases = $evidence['qualification_bases'] ?? [];

        if (
            ! is_string($trustedUserId)
            || ! hash_equals($trustedUserId, $actorUserId)
            || ! is_array($authorityKinds)
            || ! in_array($authorityKind->value, $authorityKinds, true)
            || ! is_array($qualificationBases)
        ) {
            return false;
        }

        $expectedQualification = $qualificationBases[$authorityKind->value]
            ?? null;

        return $expectedQualification === null
            || (
                is_string($expectedQualification)
                && hash_equals($expectedQualification, $qualificationBasis)
            );
    }

    private function lockedAppointment(string $appointmentId): stdClass
    {
        $context = $this->contexts->current();
        $appointment = DB::table('authority_appointments')
            ->where('organization_id', $context->organizationId)
            ->where('id', $appointmentId)
            ->lockForUpdate()
            ->first();

        if (! $appointment instanceof stdClass) {
            throw new CurriculumOperationRejected;
        }

        return $appointment;
    }

    /**
     * @param  list<string>  $eventTypes
     */
    private function hasEvent(
        string $appointmentId,
        array $eventTypes,
    ): bool {
        return DB::table('authority_appointment_events')
            ->where(
                'organization_id',
                $this->contexts->current()->organizationId,
            )
            ->where('authority_appointment_id', $appointmentId)
            ->whereIn('event_type', $eventTypes)
            ->exists();
    }

    private function activeMembership(
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
            ->lockForUpdate()
            ->exists();
    }

    private function assertFreshPrivilegedMfa(
        User $actor,
        string $authenticationSessionId,
        CarbonInterface $now,
    ): void {
        $session = AuthenticationSession::query()
            ->whereKey($authenticationSessionId)
            ->where('user_id', $actor->getKey())
            ->lockForUpdate()
            ->first();

        if (
            ! $session instanceof AuthenticationSession
            || ! $session->is_privileged
            || $session->revoked_at !== null
            || $session->idle_expires_at->lessThanOrEqualTo($now)
            || $session->absolute_expires_at->lessThanOrEqualTo($now)
            || ! $this->authenticationSessions->hasFreshMfa($session)
        ) {
            throw new CurriculumOperationRejected;
        }
    }

    private function insertEvent(
        string $eventId,
        string $appointmentId,
        string $eventType,
        string $actorMembershipId,
        CarbonInterface $effectiveAt,
        ?string $reasonKey = null,
        bool $invalidatesPriorApprovals = false,
    ): void {
        $now = Date::now();

        DB::table('authority_appointment_events')->insert([
            'id' => $eventId,
            'organization_id' => $this->contexts->current()->organizationId,
            'authority_appointment_id' => $appointmentId,
            'event_type' => $eventType,
            'actor_membership_id' => $actorMembershipId,
            'reason_key' => $reasonKey,
            'invalidates_prior_approvals' => $invalidatesPriorApprovals,
            'effective_at' => $effectiveAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function recordEvidence(
        string $action,
        string $eventType,
        string $appointmentId,
    ): void {
        $context = $this->contexts->current();

        $this->audit->recordTenant(
            $action,
            'authority_appointment',
            $appointmentId,
        );
        $this->outbox->recordTenant(
            $eventType,
            'authority_appointment',
            $appointmentId,
            [
                'organization_id' => $context->organizationId,
                'membership_id' => (string) $context->membershipId,
            ],
        );
    }

    private function stringProperty(
        stdClass $record,
        string $property,
    ): string {
        $value = $record->{$property} ?? null;

        if (! is_string($value)) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function dateProperty(
        stdClass $record,
        string $property,
    ): CarbonInterface {
        $value = $record->{$property} ?? null;

        if (! is_string($value)) {
            throw new CurriculumOperationRejected;
        }

        return Date::parse($value);
    }

    private function instant(CarbonInterface $value): string
    {
        return CarbonImmutable::instance($value)
            ->utc()
            ->format('Y-m-d\TH:i:s.u\Z');
    }
}
