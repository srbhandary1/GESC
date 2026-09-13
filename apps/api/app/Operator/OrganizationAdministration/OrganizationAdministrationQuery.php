<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration;

use App\Curriculum\CanonicalJson;
use App\Models\Membership;
use App\Models\User;
use App\Operator\Exceptions\InvalidOperatorCursor;
use App\Operator\OperatorCursorCodec;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationRejected;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class OrganizationAdministrationQuery
{
    private const int MAX_PAGE_SIZE = 25;

    /**
     * @var list<string>
     */
    private const array TERMINAL_APPOINTMENT_EVENTS = [
        'surrendered',
        'revoked',
        'revoked_for_cause',
        'expired',
    ];

    public function __construct(
        private OrganizationAdministrationAuthorization $authorization,
        private OperatorCursorCodec $cursors,
        private MembershipSelectedCredentialService $credentials,
    ) {}

    /**
     * @return array{data: array<string, mixed>}
     */
    public function organization(User $actor): array
    {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::ORGANIZATION_READ,
        );
        $context = $this->authorization->contextFor($actor);
        $row = DB::table('organizations')
            ->where('id', $context->organizationId)
            ->where('status', 'active')
            ->first(['id', 'name', 'kind', 'status', 'timezone']);

        if (! $row instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        return ['data' => [
            'id' => $this->string($row, 'id'),
            'name' => $this->string($row, 'name'),
            'kind' => $this->string($row, 'kind'),
            'status' => $this->string($row, 'status'),
            'timezone' => $this->string($row, 'timezone'),
            'allowed_actions' => [],
        ]];
    }

    /**
     * @param  array{status?: string}  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function branches(
        User $actor,
        array $filters,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::BRANCHES_READ,
        );
        $context = $this->authorization->contextFor($actor);
        $canManage = $this->authorization->allows(
            $actor,
            OrganizationAdministrationAuthorization::BRANCHES_MANAGE,
        );
        $query = DB::table('branches')
            ->where('organization_id', $context->organizationId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        [$rows, $next] = $this->page(
            $query,
            'branches',
            $context->organizationId,
            $filters,
            $limit,
            $cursor,
            ['id', 'key', 'name', 'status', 'version', 'archived_at', 'created_at', 'updated_at'],
        );

        return [
            'data' => array_values($rows->map(
                fn (stdClass $row): array => $this->branchProjection($row, $canManage),
            )->all()),
            'next_cursor' => $next,
            'allowed_actions' => $canManage ? ['branch.create'] : [],
        ];
    }

    /**
     * @param  array{status?: string, branch_id?: string}  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function cohorts(
        User $actor,
        array $filters,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::COHORTS_READ,
        );
        $context = $this->authorization->contextFor($actor);
        $canManage = $this->authorization->allows(
            $actor,
            OrganizationAdministrationAuthorization::COHORTS_MANAGE,
        );
        $query = DB::table('cohorts')
            ->leftJoin('branches', function ($join): void {
                $join
                    ->on('branches.organization_id', '=', 'cohorts.organization_id')
                    ->on('branches.id', '=', 'cohorts.branch_id');
            })
            ->where('cohorts.organization_id', $context->organizationId);

        if (isset($filters['status'])) {
            $query->where('cohorts.status', $filters['status']);
        }

        if (isset($filters['branch_id'])) {
            $query->where('cohorts.branch_id', $filters['branch_id']);
        }

        [$rows, $next] = $this->page(
            $query,
            'cohorts',
            $context->organizationId,
            $filters,
            $limit,
            $cursor,
            [
                'cohorts.id',
                'cohorts.key',
                'cohorts.name',
                'cohorts.status',
                'cohorts.version',
                'cohorts.branch_id',
                'cohorts.archived_at',
                'cohorts.created_at',
                'cohorts.updated_at',
                'branches.key as branch_key',
                'branches.name as branch_name',
                'branches.status as branch_status',
            ],
            'cohorts.id',
        );

        return [
            'data' => array_values($rows->map(
                fn (stdClass $row): array => $this->cohortProjection($row, $canManage),
            )->all()),
            'next_cursor' => $next,
            'allowed_actions' => $canManage ? ['cohort.create'] : [],
        ];
    }

    /**
     * @param  array{status?: string, display_name_prefix?: string}  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function memberships(
        User $actor,
        array $filters,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::MEMBERSHIPS_READ,
        );
        $context = $this->authorization->contextFor($actor);
        $query = DB::table('memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.organization_id', $context->organizationId)
            ->whereIn('memberships.membership_type', $this->staffTypes());
        $this->applyMembershipFilters($query, $filters);

        [$rows, $next] = $this->page(
            $query,
            'memberships',
            $context->organizationId,
            $filters,
            $limit,
            $cursor,
            [
                'memberships.id',
                'memberships.membership_type',
                'memberships.status',
                'memberships.joined_at',
                'memberships.ended_at',
                'memberships.version',
                'users.display_name',
            ],
            'memberships.id',
        );
        $roleMap = $this->rolesForMemberships(
            $context->organizationId,
            $this->ids($rows),
        );
        $actorMembershipId = (string) $context->membershipId;
        $canManageRoles = $this->authorization->allows(
            $actor,
            OrganizationAdministrationAuthorization::ROLES_MANAGE,
        );
        $canManageMemberships = $this->authorization->allows(
            $actor,
            OrganizationAdministrationAuthorization::MEMBERSHIPS_MANAGE,
        );

        return [
            'data' => array_values($rows->map(fn (stdClass $row): array => $this->membershipProjection(
                $row,
                $roleMap[$this->string($row, 'id')] ?? [],
                $actorMembershipId,
                $canManageRoles,
                $canManageMemberships,
            ))->all()),
            'next_cursor' => $next,
            'allowed_actions' => [],
        ];
    }

    /**
     * @param  array{status?: string}  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function invitations(
        User $actor,
        array $filters,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::INVITATIONS_MANAGE,
        );
        $context = $this->authorization->contextFor($actor);
        $query = DB::table('institution_invites')
            ->where('organization_id', $context->organizationId)
            ->whereIn('membership_type', $this->staffTypes());

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        [$rows, $next] = $this->page(
            $query,
            'institution-invites',
            $context->organizationId,
            $filters,
            $limit,
            $cursor,
            [
                'id',
                'membership_type',
                'status',
                'version',
                'expires_at',
                'accepted_membership_id',
                'accepted_at',
                'revoked_at',
                'created_at',
                'updated_at',
            ],
        );
        $mayReadMemberships = $this->authorization->allows(
            $actor,
            OrganizationAdministrationAuthorization::MEMBERSHIPS_READ,
        );

        return [
            'data' => array_values($rows->map(
                fn (stdClass $row): array => $this->invitationProjection(
                    $row,
                    $mayReadMemberships,
                ),
            )->all()),
            'next_cursor' => $next,
            'allowed_actions' => ['invitation.issue'],
        ];
    }

    /**
     * @param  array{status?: string}  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>, delegable_permissions: list<array{key: string, description: string}>}
     */
    public function roles(
        User $actor,
        array $filters,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::ROLES_MANAGE,
        );
        $context = $this->authorization->contextFor($actor);
        $query = DB::table('roles')
            ->where('organization_id', $context->organizationId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        [$rows, $next] = $this->page(
            $query,
            'roles',
            $context->organizationId,
            $filters,
            $limit,
            $cursor,
            ['id', 'key', 'name', 'is_system', 'status', 'version', 'retired_at', 'created_at', 'updated_at'],
        );
        $roleIds = $this->ids($rows);
        $permissions = $this->permissionsForRoles(
            $context->organizationId,
            $roleIds,
        );
        $assignmentCounts = $this->roleAssignmentCounts(
            $context->organizationId,
            $roleIds,
        );
        $actorRoleIds = DB::table('membership_roles')
            ->where('organization_id', $context->organizationId)
            ->where('membership_id', $context->membershipId)
            ->pluck('role_id')
            ->filter(static fn (mixed $id): bool => is_string($id))
            ->all();

        return [
            'data' => array_values($rows->map(function (stdClass $row) use (
                $permissions,
                $assignmentCounts,
                $actorRoleIds,
            ): array {
                $id = $this->string($row, 'id');

                return $this->roleProjection(
                    $row,
                    $permissions[$id] ?? [],
                    $assignmentCounts[$id] ?? 0,
                    in_array($id, $actorRoleIds, true),
                );
            })->all()),
            'next_cursor' => $next,
            'allowed_actions' => ['role.create'],
            'delegable_permissions' => array_values($this->authorization
                ->permissionRows($actor)
                ->map(static fn (stdClass $permission): array => [
                    'key' => is_string($permission->key ?? null)
                        ? $permission->key
                        : '',
                    'description' => is_string($permission->description ?? null)
                        ? $permission->description
                        : '',
                ])->all()),
        ];
    }

    /**
     * @param  array{status?: string, display_name_prefix?: string}  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function trainers(
        User $actor,
        array $filters,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::TRAINERS_READ,
        );
        $context = $this->authorization->contextFor($actor);
        $query = DB::table('memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.organization_id', $context->organizationId)
            ->where('memberships.membership_type', Membership::TYPE_TRAINER);
        $this->applyMembershipFilters($query, $filters);

        [$rows, $next] = $this->page(
            $query,
            'trainer-profiles',
            $context->organizationId,
            $filters,
            $limit,
            $cursor,
            [
                'memberships.id',
                'memberships.membership_type',
                'memberships.status',
                'memberships.joined_at',
                'memberships.ended_at',
                'memberships.version',
                'users.display_name',
            ],
            'memberships.id',
        );
        $membershipIds = $this->ids($rows);
        $roles = $this->rolesForMemberships($context->organizationId, $membershipIds);
        $cohorts = $this->cohortsForTrainers($context->organizationId, $membershipIds);
        $appointments = $this->appointmentsForTrainers(
            $context->organizationId,
            $membershipIds,
            false,
        );
        $canManage = $this->authorization->allows(
            $actor,
            OrganizationAdministrationAuthorization::TRAINERS_MANAGE,
        );
        $canAppoint = $this->authorization->allows(
            $actor,
            OrganizationAdministrationAuthorization::APPOINTMENTS_MANAGE,
        );

        return [
            'data' => array_values($rows->map(function (stdClass $row) use (
                $roles,
                $cohorts,
                $appointments,
                $canManage,
                $canAppoint,
            ): array {
                $id = $this->string($row, 'id');
                $actions = [];

                if ($canManage && $this->string($row, 'status') === Membership::STATUS_ACTIVE) {
                    $actions[] = 'trainer.assign_cohort';

                    if (($cohorts[$id] ?? []) !== []) {
                        $actions[] = 'trainer.revoke_cohort';
                    }
                }

                if ($canAppoint && $this->string($row, 'status') === Membership::STATUS_ACTIVE) {
                    $actions[] = 'appointment.create';
                }

                return [
                    ...$this->membershipProjection($row, $roles[$id] ?? [], '', false, false),
                    'cohorts' => $cohorts[$id] ?? [],
                    'appointments' => $appointments[$id] ?? [],
                    'allowed_actions' => $actions,
                ];
            })->all()),
            'next_cursor' => $next,
            'allowed_actions' => [],
        ];
    }

    /**
     * @param  array{authority_kind?: string, state?: string}  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function appointments(
        User $actor,
        string $membershipId,
        array $filters,
        int $limit,
        ?string $cursor,
    ): array {
        $context = $this->authorization->contextFor($actor);
        $manager = $this->authorization->allows(
            $actor,
            OrganizationAdministrationAuthorization::APPOINTMENTS_MANAGE,
        );
        $appointee = hash_equals((string) $context->membershipId, $membershipId);

        if (! $manager && ! $appointee) {
            throw new OrganizationAdministrationRejected;
        }

        $membership = DB::table('memberships')
            ->where('organization_id', $context->organizationId)
            ->where('id', $membershipId)
            ->where('membership_type', Membership::TYPE_TRAINER)
            ->first(['id', 'status']);

        if (! $membership instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        $query = DB::table('authority_appointments')
            ->where('organization_id', $context->organizationId)
            ->where('appointee_membership_id', $membershipId);

        if (isset($filters['authority_kind'])) {
            $query->where('authority_kind', $filters['authority_kind']);
        }

        [$rows, $next] = $this->page(
            $query,
            'authority-appointments-'.$membershipId,
            $context->organizationId,
            $filters,
            $limit,
            $cursor,
            [
                'id',
                'appointee_membership_id',
                'authority_kind',
                'scope_type',
                'program_id',
                'valid_from',
                'valid_until',
                'appointed_at',
                'created_at',
                'updated_at',
            ],
        );
        $events = $this->eventsForAppointments(
            $context->organizationId,
            $this->ids($rows),
        );
        $data = array_values($rows->map(function (stdClass $row) use (
            $events,
            $manager,
            $appointee,
        ): array {
            $id = $this->string($row, 'id');

            return $this->appointmentProjection(
                $row,
                $events[$id] ?? [],
                $manager,
                $appointee,
                false,
            );
        })->filter(function (array $appointment) use ($filters): bool {
            return ! isset($filters['state'])
                || $appointment['state'] === $filters['state'];
        })->all());

        return [
            'data' => $data,
            'next_cursor' => $next,
            'allowed_actions' => $manager
                && $this->string($membership, 'status') === Membership::STATUS_ACTIVE
                    ? ['appointment.create']
                    : [],
        ];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function appointment(
        User $actor,
        string $membershipId,
        string $appointmentId,
    ): array {
        $context = $this->authorization->contextFor($actor);
        $manager = $this->authorization->allows(
            $actor,
            OrganizationAdministrationAuthorization::APPOINTMENTS_MANAGE,
        );
        $appointee = hash_equals((string) $context->membershipId, $membershipId);

        if (! $manager && ! $appointee) {
            throw new OrganizationAdministrationRejected;
        }

        $row = DB::table('authority_appointments')
            ->join('memberships', function ($join): void {
                $join
                    ->on('memberships.organization_id', '=', 'authority_appointments.organization_id')
                    ->on('memberships.id', '=', 'authority_appointments.appointee_membership_id');
            })
            ->where('authority_appointments.organization_id', $context->organizationId)
            ->where('authority_appointments.appointee_membership_id', $membershipId)
            ->where('authority_appointments.id', $appointmentId)
            ->whereIn('memberships.membership_type', $this->staffTypes())
            ->first([
                'authority_appointments.id',
                'authority_appointments.appointee_membership_id',
                'authority_appointments.authority_kind',
                'authority_appointments.scope_type',
                'authority_appointments.program_id',
                'authority_appointments.valid_from',
                'authority_appointments.valid_until',
                'authority_appointments.qualification_basis',
                'authority_appointments.approval_evidence_key',
                'authority_appointments.appointed_at',
                'authority_appointments.created_at',
                'authority_appointments.updated_at',
            ]);

        if (! $row instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        $events = $this->eventsForAppointments(
            $context->organizationId,
            [$appointmentId],
        );

        return ['data' => $this->appointmentProjection(
            $row,
            $events[$appointmentId] ?? [],
            $manager,
            $appointee,
            true,
        )];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function membershipSessions(User $actor, string $membershipId): array
    {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::MEMBERSHIPS_READ,
        );
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::MEMBERSHIP_SESSIONS_REVOKE,
        );
        $context = $this->authorization->contextFor($actor);
        $membership = DB::table('memberships')
            ->where('organization_id', $context->organizationId)
            ->where('id', $membershipId)
            ->whereIn('membership_type', $this->staffTypes())
            ->first(['id', 'status', 'version']);

        if (! $membership instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        return ['data' => $this->membershipSessionProjection(
            $context->organizationId,
            $membership,
            (string) $context->membershipId,
            true,
        )];
    }

    /**
     * Command-result projections deliberately accept the corresponding read
     * or manage permission. A caller authorized to mutate may receive the
     * bounded representation of the resource it just changed.
     *
     * @return array<string, mixed>
     */
    public function commandResult(
        User $actor,
        OrganizationAdministrationCommandResult $result,
    ): array {
        return match ($result->resultType) {
            'branch' => $this->branchById($actor, $result->resultId),
            'cohort' => $this->cohortById($actor, $result->resultId),
            'institution_invitation' => $this->invitationById($actor, $result->resultId),
            'role' => $this->roleById($actor, $result->resultId),
            'membership' => $this->membershipById($actor, $result->resultId),
            'trainer_profile' => $this->trainerById($actor, $result->resultId),
            'authority_appointment', 'authority_appointment_event' => $this->appointmentByResult(
                $actor,
                $result->resultId,
                $result->resultType,
            ),
            'membership_sessions' => $this->membershipSessionsCommandResult(
                $actor,
                $result,
            ),
            default => throw new OrganizationAdministrationRejected,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     * @return array{Collection<int, stdClass>, string|null}
     */
    private function page(
        Builder $query,
        string $resource,
        string $organizationId,
        array $filters,
        int $limit,
        ?string $cursor,
        array $columns,
        string $idColumn = 'id',
    ): array {
        $limit = max(1, min(self::MAX_PAGE_SIZE, $limit));
        $purpose = 'admin-org.'.$resource.'.'.CanonicalJson::sha256($filters);

        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $purpose, $organizationId);
            $afterId = $position['id'] ?? null;

            if (! is_string($afterId) || $afterId === '') {
                throw new InvalidOperatorCursor;
            }

            $query->where($idColumn, '>', $afterId);
        }

        $rows = $query
            ->orderBy($idColumn)
            ->limit($limit + 1)
            ->get($columns);
        $hasMore = $rows->count() > $limit;

        if ($hasMore) {
            $rows = $rows->take($limit)->values();
        }

        $last = $rows->last();
        $next = $hasMore && $last instanceof stdClass
            ? $this->cursors->encode(
                $purpose,
                $organizationId,
                ['id' => $this->string($last, 'id')],
            )
            : null;

        return [$rows, $next];
    }

    /**
     * @return array<string, mixed>
     */
    private function branchProjection(stdClass $row, bool $canManage): array
    {
        $active = $this->string($row, 'status') === 'active';

        return [
            'id' => $this->string($row, 'id'),
            'key' => $this->string($row, 'key'),
            'name' => $this->string($row, 'name'),
            'status' => $this->string($row, 'status'),
            'version' => $this->integer($row, 'version'),
            'created_at' => $this->date($row, 'created_at'),
            'updated_at' => $this->date($row, 'updated_at'),
            'allowed_actions' => $canManage && $active
                ? ['branch.update', 'branch.archive']
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cohortProjection(stdClass $row, bool $canManage): array
    {
        $branchId = $this->nullableString($row, 'branch_id');
        $active = $this->string($row, 'status') === 'active';

        return [
            'id' => $this->string($row, 'id'),
            'key' => $this->string($row, 'key'),
            'name' => $this->string($row, 'name'),
            'status' => $this->string($row, 'status'),
            'branch' => $branchId === null ? null : [
                'id' => $branchId,
                'key' => $this->nullableString($row, 'branch_key'),
                'name' => $this->nullableString($row, 'branch_name'),
            ],
            'version' => $this->integer($row, 'version'),
            'created_at' => $this->date($row, 'created_at'),
            'updated_at' => $this->date($row, 'updated_at'),
            'allowed_actions' => $canManage && $active
                ? ['cohort.update', 'cohort.archive']
                : [],
        ];
    }

    /**
     * @param  list<array{id: string, key: string, name: string, is_system: bool}>  $roles
     * @return array<string, mixed>
     */
    private function membershipProjection(
        stdClass $row,
        array $roles,
        string $actorMembershipId,
        bool $canManageRoles,
        bool $canManageMemberships,
    ): array {
        $id = $this->string($row, 'id');
        $status = $this->string($row, 'status');
        $self = $actorMembershipId !== '' && hash_equals($actorMembershipId, $id);
        $actions = [];

        if (! $self && $status === Membership::STATUS_ACTIVE && $canManageRoles) {
            $actions[] = 'membership.assign_role';

            if ($roles !== []) {
                $actions[] = 'membership.revoke_role';
            }
        }

        if (! $self && $canManageMemberships) {
            if ($status === Membership::STATUS_ACTIVE) {
                $actions[] = 'membership.deactivate';
            } elseif ($status === Membership::STATUS_SUSPENDED) {
                $actions[] = 'membership.reactivate';
            }
        }

        return [
            'membership_id' => $id,
            'display_name' => $this->nullableString($row, 'display_name'),
            'membership_type' => $this->string($row, 'membership_type'),
            'status' => $status,
            'joined_at' => $this->date($row, 'joined_at'),
            'ended_at' => $this->date($row, 'ended_at'),
            'roles' => $roles,
            'version' => $this->integer($row, 'version'),
            'allowed_actions' => $actions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationProjection(
        stdClass $row,
        bool $mayReadMemberships,
    ): array {
        $status = $this->string($row, 'status');
        $expiresAt = $this->carbon($row, 'expires_at');
        $pending = $status === 'pending' && $expiresAt->isFuture();

        return [
            'id' => $this->string($row, 'id'),
            'membership_type' => $this->string($row, 'membership_type'),
            'status' => $status,
            'version' => $this->integer($row, 'version'),
            'expires_at' => $expiresAt->toIso8601String(),
            'accepted_membership_id' => $mayReadMemberships
                ? $this->nullableString($row, 'accepted_membership_id')
                : null,
            'accepted_at' => $this->date($row, 'accepted_at'),
            'created_at' => $this->date($row, 'created_at'),
            'updated_at' => $this->date($row, 'updated_at'),
            'allowed_actions' => $pending ? ['invitation.revoke'] : [],
        ];
    }

    /**
     * @param  list<string>  $permissionKeys
     * @return array<string, mixed>
     */
    private function roleProjection(
        stdClass $row,
        array $permissionKeys,
        int $assignmentCount,
        bool $assignedToActor,
    ): array {
        $activeCustom = ! $this->boolean($row, 'is_system')
            && $this->string($row, 'status') === 'active';
        $actions = [];

        if ($activeCustom && ! $assignedToActor) {
            $actions[] = 'role.update';

            if ($assignmentCount === 0) {
                $actions[] = 'role.retire';
            }
        }

        return [
            'id' => $this->string($row, 'id'),
            'key' => $this->string($row, 'key'),
            'name' => $this->string($row, 'name'),
            'is_system' => $this->boolean($row, 'is_system'),
            'status' => $this->string($row, 'status'),
            'permission_keys' => $permissionKeys,
            'assignment_count' => $assignmentCount,
            'version' => $this->integer($row, 'version'),
            'allowed_actions' => $actions,
        ];
    }

    /**
     * @param  list<stdClass>  $events
     * @return array<string, mixed>
     */
    private function appointmentProjection(
        stdClass $row,
        array $events,
        bool $manager,
        bool $appointee,
        bool $includePrivateDetail,
    ): array {
        $state = $this->appointmentState($events);
        $validUntil = $this->date($row, 'valid_until');

        if (
            ! in_array($state, self::TERMINAL_APPOINTMENT_EVENTS, true)
            && $validUntil !== null
            && CarbonImmutable::parse($validUntil)->lessThanOrEqualTo(
                CarbonImmutable::now(),
            )
        ) {
            $state = 'expired';
        }
        $actions = [];

        if ($manager && ! in_array($state, self::TERMINAL_APPOINTMENT_EVENTS, true)) {
            $actions[] = 'appointment.revoke';
        }

        if ($appointee && $state === 'appointed') {
            $actions[] = 'appointment.accept';
        }

        if ($appointee && $state === 'accepted') {
            $actions[] = 'appointment.surrender';
        }

        $publicState = match ($state) {
            'appointed' => 'pending',
            'accepted' => 'active',
            'surrendered' => 'surrendered',
            'expired' => 'expired',
            default => 'revoked',
        };
        $projection = [
            'id' => $this->string($row, 'id'),
            'authority_kind' => $this->string($row, 'authority_kind'),
            'scope_type' => $this->string($row, 'scope_type'),
            'program_id' => $this->nullableString($row, 'program_id'),
            'valid_from' => $this->date($row, 'valid_from'),
            'valid_until' => $validUntil,
            'state' => $publicState,
            'state_fingerprint' => CanonicalJson::sha256([
                'appointment_id' => $this->string($row, 'id'),
                'events' => array_map(fn (stdClass $event): array => [
                    'id' => $this->string($event, 'id'),
                    'event_type' => $this->string($event, 'event_type'),
                    'effective_at' => $this->date($event, 'effective_at'),
                ], $events),
            ]),
            'allowed_actions' => $actions,
        ];

        if ($includePrivateDetail) {
            $projection['qualification_basis'] = $this->string(
                $row,
                'qualification_basis',
            );
            $projection['approval_evidence_key'] = $this->string(
                $row,
                'approval_evidence_key',
            );
        }

        return $projection;
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipSessionProjection(
        string $organizationId,
        stdClass $membership,
        string $actorMembershipId,
        bool $canRevoke,
    ): array {
        $membershipId = $this->string($membership, 'id');
        $snapshot = $this->credentials->snapshot(
            $organizationId,
            $membershipId,
            $this->string($membership, 'status'),
            $this->integer($membership, 'version'),
        );
        $actions = [];

        if (
            $canRevoke
            && ! hash_equals($actorMembershipId, $membershipId)
            && $this->string($membership, 'status') === Membership::STATUS_ACTIVE
            && ($snapshot['web']->isNotEmpty() || $snapshot['mobile']->isNotEmpty())
        ) {
            $actions[] = 'membership_session.revoke';
        }

        return [
            'membership_id' => $membershipId,
            'active_web_session_count' => $snapshot['active_web_count'],
            'active_android_family_count' => $snapshot['active_mobile_count'],
            'latest_activity_at' => $snapshot['latest_activity_at'],
            'state_fingerprint' => $snapshot['fingerprint'],
            'allowed_actions' => $actions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function branchById(User $actor, string $id): array
    {
        $this->assertAny($actor, [
            OrganizationAdministrationAuthorization::BRANCHES_READ,
            OrganizationAdministrationAuthorization::BRANCHES_MANAGE,
        ]);
        $context = $this->authorization->contextFor($actor);
        $row = DB::table('branches')
            ->where('organization_id', $context->organizationId)
            ->where('id', $id)
            ->first(['id', 'key', 'name', 'status', 'version', 'archived_at', 'created_at', 'updated_at']);

        if (! $row instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        return $this->branchProjection(
            $row,
            $this->authorization->allows($actor, OrganizationAdministrationAuthorization::BRANCHES_MANAGE),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cohortById(User $actor, string $id): array
    {
        $this->assertAny($actor, [
            OrganizationAdministrationAuthorization::COHORTS_READ,
            OrganizationAdministrationAuthorization::COHORTS_MANAGE,
        ]);
        $context = $this->authorization->contextFor($actor);
        $row = DB::table('cohorts')
            ->leftJoin('branches', function ($join): void {
                $join
                    ->on('branches.organization_id', '=', 'cohorts.organization_id')
                    ->on('branches.id', '=', 'cohorts.branch_id');
            })
            ->where('cohorts.organization_id', $context->organizationId)
            ->where('cohorts.id', $id)
            ->first([
                'cohorts.id', 'cohorts.key', 'cohorts.name', 'cohorts.status',
                'cohorts.version', 'cohorts.branch_id', 'cohorts.archived_at',
                'cohorts.created_at', 'cohorts.updated_at',
                'branches.key as branch_key', 'branches.name as branch_name',
                'branches.status as branch_status',
            ]);

        if (! $row instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        return $this->cohortProjection(
            $row,
            $this->authorization->allows($actor, OrganizationAdministrationAuthorization::COHORTS_MANAGE),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationById(User $actor, string $id): array
    {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::INVITATIONS_MANAGE,
        );
        $context = $this->authorization->contextFor($actor);
        $row = DB::table('institution_invites')
            ->where('organization_id', $context->organizationId)
            ->where('id', $id)
            ->whereIn('membership_type', $this->staffTypes())
            ->first([
                'id', 'membership_type', 'status', 'version', 'expires_at',
                'accepted_membership_id', 'accepted_at', 'revoked_at', 'created_at',
                'updated_at',
            ]);

        if (! $row instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        return $this->invitationProjection(
            $row,
            $this->authorization->allows($actor, OrganizationAdministrationAuthorization::MEMBERSHIPS_READ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function roleById(User $actor, string $id): array
    {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::ROLES_MANAGE,
        );
        $context = $this->authorization->contextFor($actor);
        $row = DB::table('roles')
            ->where('organization_id', $context->organizationId)
            ->where('id', $id)
            ->first(['id', 'key', 'name', 'is_system', 'status', 'version', 'retired_at', 'created_at', 'updated_at']);

        if (! $row instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        $permissions = $this->permissionsForRoles($context->organizationId, [$id]);
        $counts = $this->roleAssignmentCounts($context->organizationId, [$id]);
        $assignedToActor = DB::table('membership_roles')
            ->where('organization_id', $context->organizationId)
            ->where('membership_id', $context->membershipId)
            ->where('role_id', $id)
            ->exists();

        return $this->roleProjection(
            $row,
            $permissions[$id] ?? [],
            $counts[$id] ?? 0,
            $assignedToActor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipById(User $actor, string $id): array
    {
        $this->assertAny($actor, [
            OrganizationAdministrationAuthorization::MEMBERSHIPS_READ,
            OrganizationAdministrationAuthorization::MEMBERSHIPS_MANAGE,
            OrganizationAdministrationAuthorization::ROLES_MANAGE,
            OrganizationAdministrationAuthorization::TRAINERS_READ,
            OrganizationAdministrationAuthorization::TRAINERS_MANAGE,
        ]);
        $context = $this->authorization->contextFor($actor);
        $row = DB::table('memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.organization_id', $context->organizationId)
            ->where('memberships.id', $id)
            ->whereIn('memberships.membership_type', $this->staffTypes())
            ->first([
                'memberships.id', 'memberships.membership_type', 'memberships.status',
                'memberships.joined_at', 'memberships.ended_at', 'memberships.version',
                'users.display_name',
            ]);

        if (! $row instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        $roles = $this->rolesForMemberships($context->organizationId, [$id]);

        return $this->membershipProjection(
            $row,
            $roles[$id] ?? [],
            (string) $context->membershipId,
            $this->authorization->allows($actor, OrganizationAdministrationAuthorization::ROLES_MANAGE),
            $this->authorization->allows($actor, OrganizationAdministrationAuthorization::MEMBERSHIPS_MANAGE),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function trainerById(User $actor, string $id): array
    {
        $this->assertAny($actor, [
            OrganizationAdministrationAuthorization::TRAINERS_READ,
            OrganizationAdministrationAuthorization::TRAINERS_MANAGE,
        ]);
        $base = $this->membershipById($actor, $id);
        $context = $this->authorization->contextFor($actor);
        $membership = DB::table('memberships')
            ->where('organization_id', $context->organizationId)
            ->where('id', $id)
            ->where('membership_type', Membership::TYPE_TRAINER)
            ->first(['id', 'status']);

        if (! $membership instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        $cohorts = $this->cohortsForTrainers($context->organizationId, [$id]);
        $appointments = $this->appointmentsForTrainers($context->organizationId, [$id], false);
        $actions = [];

        if (
            $this->authorization->allows($actor, OrganizationAdministrationAuthorization::TRAINERS_MANAGE)
            && $this->string($membership, 'status') === Membership::STATUS_ACTIVE
        ) {
            $actions[] = 'trainer.assign_cohort';

            if (($cohorts[$id] ?? []) !== []) {
                $actions[] = 'trainer.revoke_cohort';
            }
        }

        return [
            ...$base,
            'cohorts' => $cohorts[$id] ?? [],
            'appointments' => $appointments[$id] ?? [],
            'allowed_actions' => $actions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentByResult(
        User $actor,
        string $resultId,
        string $resultType,
    ): array {
        $context = $this->authorization->contextFor($actor);
        $appointmentId = $resultType === 'authority_appointment'
            ? $resultId
            : DB::table('authority_appointment_events')
                ->where('organization_id', $context->organizationId)
                ->where('id', $resultId)
                ->value('authority_appointment_id');

        if (! is_string($appointmentId)) {
            throw new OrganizationAdministrationRejected;
        }

        $row = DB::table('authority_appointments')
            ->where('organization_id', $context->organizationId)
            ->where('id', $appointmentId)
            ->first();

        if (! $row instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        $manager = $this->authorization->allows(
            $actor,
            OrganizationAdministrationAuthorization::APPOINTMENTS_MANAGE,
        );
        $appointee = hash_equals(
            (string) $context->membershipId,
            $this->string($row, 'appointee_membership_id'),
        );

        if (! $manager && ! $appointee) {
            throw new OrganizationAdministrationRejected;
        }

        $events = $this->eventsForAppointments($context->organizationId, [$appointmentId]);

        return $this->appointmentProjection(
            $row,
            $events[$appointmentId] ?? [],
            $manager,
            $appointee,
            true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipSessionsCommandResult(
        User $actor,
        OrganizationAdministrationCommandResult $result,
    ): array {
        $this->authorization->assert(
            $actor,
            OrganizationAdministrationAuthorization::MEMBERSHIP_SESSIONS_REVOKE,
        );
        $context = $this->authorization->contextFor($actor);
        $membership = DB::table('memberships')
            ->where('organization_id', $context->organizationId)
            ->where('id', $result->resultId)
            ->whereIn('membership_type', $this->staffTypes())
            ->first(['id', 'status', 'version']);

        if (! $membership instanceof stdClass) {
            throw new OrganizationAdministrationRejected;
        }

        return [
            ...$this->membershipSessionProjection(
                $context->organizationId,
                $membership,
                (string) $context->membershipId,
                true,
            ),
            'revoked_web_session_count' => $result->revokedWebSessionCount ?? 0,
            'revoked_android_family_count' => $result->revokedAndroidFamilyCount ?? 0,
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function assertAny(User $actor, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->authorization->allows($actor, $permission)) {
                return;
            }
        }

        throw new OrganizationAdministrationRejected;
    }

    /**
     * @param  array{status?: string, display_name_prefix?: string}  $filters
     */
    private function applyMembershipFilters(Builder $query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('memberships.status', $filters['status']);
        }

        if (isset($filters['display_name_prefix'])) {
            $query->whereRaw(
                'LOWER(users.display_name) LIKE ?',
                [mb_strtolower($filters['display_name_prefix']).'%'],
            );
        }
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<string>
     */
    private function ids(Collection $rows): array
    {
        return array_values(
            $rows->map(fn (stdClass $row): string => $this->string($row, 'id'))->all(),
        );
    }

    /**
     * @param  list<string>  $membershipIds
     * @return array<string, list<array{id: string, key: string, name: string, is_system: bool}>>
     */
    private function rolesForMemberships(
        string $organizationId,
        array $membershipIds,
    ): array {
        if ($membershipIds === []) {
            return [];
        }

        $rows = DB::table('membership_roles')
            ->join('roles', function ($join): void {
                $join
                    ->on('roles.organization_id', '=', 'membership_roles.organization_id')
                    ->on('roles.id', '=', 'membership_roles.role_id');
            })
            ->where('membership_roles.organization_id', $organizationId)
            ->whereIn('membership_roles.membership_id', $membershipIds)
            ->orderBy('roles.key')
            ->get([
                'membership_roles.membership_id',
                'roles.id',
                'roles.key',
                'roles.name',
                'roles.is_system',
            ]);
        $map = [];

        foreach ($rows as $row) {
            $map[$this->string($row, 'membership_id')][] = [
                'id' => $this->string($row, 'id'),
                'key' => $this->string($row, 'key'),
                'name' => $this->string($row, 'name'),
                'is_system' => $this->boolean($row, 'is_system'),
            ];
        }

        return $map;
    }

    /**
     * @param  list<string>  $roleIds
     * @return array<string, list<string>>
     */
    private function permissionsForRoles(
        string $organizationId,
        array $roleIds,
    ): array {
        if ($roleIds === []) {
            return [];
        }

        $rows = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.organization_id', $organizationId)
            ->whereIn('role_permissions.role_id', $roleIds)
            ->orderBy('permissions.key')
            ->get(['role_permissions.role_id', 'permissions.key']);
        $map = [];

        foreach ($rows as $row) {
            $map[$this->string($row, 'role_id')][] = $this->string($row, 'key');
        }

        return $map;
    }

    /**
     * @param  list<string>  $roleIds
     * @return array<string, int>
     */
    private function roleAssignmentCounts(
        string $organizationId,
        array $roleIds,
    ): array {
        if ($roleIds === []) {
            return [];
        }

        return DB::table('membership_roles')
            ->where('organization_id', $organizationId)
            ->whereIn('role_id', $roleIds)
            ->selectRaw('role_id, COUNT(*) AS aggregate')
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<string>  $membershipIds
     * @return array<string, list<array{id: string, key: string, name: string, status: string, version: int}>>
     */
    private function cohortsForTrainers(
        string $organizationId,
        array $membershipIds,
    ): array {
        if ($membershipIds === []) {
            return [];
        }

        $rows = DB::table('cohort_trainers')
            ->join('cohorts', function ($join): void {
                $join
                    ->on('cohorts.organization_id', '=', 'cohort_trainers.organization_id')
                    ->on('cohorts.id', '=', 'cohort_trainers.cohort_id');
            })
            ->where('cohort_trainers.organization_id', $organizationId)
            ->whereIn('cohort_trainers.trainer_membership_id', $membershipIds)
            ->orderBy('cohorts.key')
            ->get([
                'cohort_trainers.trainer_membership_id',
                'cohorts.id',
                'cohorts.key',
                'cohorts.name',
                'cohorts.status',
                'cohorts.version',
            ]);
        $map = [];

        foreach ($rows as $row) {
            $map[$this->string($row, 'trainer_membership_id')][] = [
                'id' => $this->string($row, 'id'),
                'key' => $this->string($row, 'key'),
                'name' => $this->string($row, 'name'),
                'status' => $this->string($row, 'status'),
                'version' => $this->integer($row, 'version'),
            ];
        }

        return $map;
    }

    /**
     * @param  list<string>  $membershipIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function appointmentsForTrainers(
        string $organizationId,
        array $membershipIds,
        bool $includePrivateDetail,
    ): array {
        if ($membershipIds === []) {
            return [];
        }

        $rows = DB::table('authority_appointments')
            ->where('organization_id', $organizationId)
            ->whereIn('appointee_membership_id', $membershipIds)
            ->orderBy('id')
            ->get();
        $events = $this->eventsForAppointments($organizationId, $this->ids($rows));
        $map = [];

        foreach ($rows as $row) {
            $id = $this->string($row, 'id');
            $membershipId = $this->string($row, 'appointee_membership_id');
            $map[$membershipId][] = $this->appointmentProjection(
                $row,
                $events[$id] ?? [],
                false,
                false,
                $includePrivateDetail,
            );
        }

        return $map;
    }

    /**
     * @param  list<string>  $appointmentIds
     * @return array<string, list<stdClass>>
     */
    private function eventsForAppointments(
        string $organizationId,
        array $appointmentIds,
    ): array {
        if ($appointmentIds === []) {
            return [];
        }

        $rows = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->whereIn('authority_appointment_id', $appointmentIds)
            ->orderBy('effective_at')
            ->orderBy('id')
            ->get(['id', 'authority_appointment_id', 'event_type', 'effective_at']);
        $map = [];

        foreach ($rows as $row) {
            $map[$this->string($row, 'authority_appointment_id')][] = $row;
        }

        return $map;
    }

    /**
     * @param  list<stdClass>  $events
     */
    private function appointmentState(array $events): string
    {
        $state = 'appointed';

        foreach ($events as $event) {
            $type = $this->string($event, 'event_type');

            if ($type !== 'appointed') {
                $state = $type;
            }
        }

        return $state;
    }

    /**
     * @return list<string>
     */
    private function staffTypes(): array
    {
        return [
            Membership::TYPE_TRAINER,
            Membership::TYPE_ORGANIZATION_ADMIN,
        ];
    }

    private function string(
        stdClass $row,
        string $property,
        string $default = '',
    ): string {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : $default;
    }

    private function nullableString(stdClass $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integer(stdClass $row, string $property): int
    {
        return (int) ($row->{$property} ?? 0);
    }

    private function boolean(stdClass $row, string $property): bool
    {
        return (bool) ($row->{$property} ?? false);
    }

    private function date(stdClass $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        if ($value === null) {
            return null;
        }

        return $this->carbon($row, $property)->toIso8601String();
    }

    private function carbon(stdClass $row, string $property): CarbonImmutable
    {
        $value = $row->{$property} ?? null;

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value);
        }

        throw new OrganizationAdministrationRejected;
    }
}
