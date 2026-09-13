<?php

declare(strict_types=1);

namespace App\Operator\LearnerAdministration;

use App\Curriculum\CanonicalJson;
use App\Models\Enrollment;
use App\Models\Membership;
use App\Models\User;
use App\Operator\Exceptions\InvalidOperatorCursor;
use App\Operator\LearnerAdministration\Exceptions\LearnerAdministrationRejected;
use App\Operator\OperatorCursorCodec;
use App\Operator\OrganizationAdministration\OrganizationAdministrationCommandResult;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class LearnerAdministrationQuery
{
    private const int MAX_PAGE_SIZE = 25;

    public function __construct(
        private LearnerAdministrationAuthorization $authorization,
        private OperatorCursorCodec $cursors,
    ) {}

    /**
     * @param  array{status?: string, goal_key?: string, cohort_id?: string, q?: string}  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>, choices: array{cohorts: list<array<string, mixed>>}|null}
     */
    public function learners(
        User $actor,
        array $filters,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assert(
            $actor,
            LearnerAdministrationAuthorization::LEARNERS_READ,
        );
        $context = $this->authorization->contextFor($actor);
        $canReadEnrollments = $this->authorization->allows(
            $actor,
            LearnerAdministrationAuthorization::ENROLLMENTS_READ,
        );

        if (isset($filters['cohort_id'])) {
            $this->authorization->assert(
                $actor,
                LearnerAdministrationAuthorization::ENROLLMENTS_READ,
            );
            $this->assertActiveCohort(
                $context->organizationId,
                $filters['cohort_id'],
            );
        }

        $query = $this->learnerBaseQuery($context->organizationId);

        if (isset($filters['status'])) {
            $query->where('memberships.status', $filters['status']);
        }

        if (isset($filters['goal_key'])) {
            $query->where('learner_profile_current_goals.goal_key', $filters['goal_key']);
        }

        if (isset($filters['cohort_id'])) {
            $cohortId = $filters['cohort_id'];
            $query->whereExists(function (Builder $enrollments) use ($cohortId): void {
                $enrollments->selectRaw('1')
                    ->from('enrollments')
                    ->whereColumn('enrollments.organization_id', 'memberships.organization_id')
                    ->whereColumn('enrollments.learner_membership_id', 'memberships.id')
                    ->where('enrollments.cohort_id', $cohortId)
                    ->whereIn('enrollments.status', [
                        Enrollment::STATUS_PENDING_CURRICULUM,
                        Enrollment::STATUS_ACTIVE,
                    ]);
            });
        }

        if (isset($filters['q'])) {
            $term = '%'.mb_strtolower($filters['q']).'%';
            $query->where(function (Builder $search) use ($term, $canReadEnrollments): void {
                $search
                    ->whereRaw("LOWER(COALESCE(users.display_name, '')) LIKE ?", [$term])
                    ->orWhereRaw('LOWER(memberships.id) LIKE ?', [$term]);

                if ($canReadEnrollments) {
                    $search->orWhereExists(function (Builder $cohorts) use ($term): void {
                        $cohorts->selectRaw('1')
                            ->from('enrollments')
                            ->join('cohorts', function ($join): void {
                                $join
                                    ->on('cohorts.organization_id', '=', 'enrollments.organization_id')
                                    ->on('cohorts.id', '=', 'enrollments.cohort_id');
                            })
                            ->whereColumn('enrollments.organization_id', 'memberships.organization_id')
                            ->whereColumn('enrollments.learner_membership_id', 'memberships.id')
                            ->whereIn('enrollments.status', [
                                Enrollment::STATUS_PENDING_CURRICULUM,
                                Enrollment::STATUS_ACTIVE,
                            ])
                            ->where(function (Builder $match) use ($term): void {
                                $match
                                    ->whereRaw('LOWER(cohorts.key) LIKE ?', [$term])
                                    ->orWhereRaw('LOWER(cohorts.name) LIKE ?', [$term]);
                            });
                    });
                }
            });
        }

        [$rows, $next] = $this->page(
            $query,
            'learners',
            $context->organizationId,
            $filters,
            $limit,
            $cursor,
            $this->learnerColumns(),
            'memberships.id',
        );

        return [
            'data' => array_values($rows->map(
                fn (stdClass $row): array => $this->learnerProjection($actor, $row),
            )->all()),
            'next_cursor' => $next,
            'allowed_actions' => [],
            'choices' => $this->authorization->allows(
                $actor,
                LearnerAdministrationAuthorization::COHORTS_READ,
            ) ? [
                'cohorts' => $this->activeCohortChoices($context->organizationId),
            ] : null,
        ];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function learner(User $actor, string $membershipId): array
    {
        $this->authorization->assert(
            $actor,
            LearnerAdministrationAuthorization::LEARNERS_READ,
        );

        return ['data' => $this->learnerById($actor, $membershipId)];
    }

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>}
     */
    public function enrollments(
        User $actor,
        string $membershipId,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assertAll($actor, [
            LearnerAdministrationAuthorization::LEARNERS_READ,
            LearnerAdministrationAuthorization::ENROLLMENTS_READ,
        ]);
        $context = $this->authorization->contextFor($actor);
        $membership = $this->learnerMembership(
            $context->organizationId,
            $membershipId,
        );
        $query = $this->enrollmentBaseQuery(
            $context->organizationId,
            $membershipId,
        );
        $filters = ['learner_membership_id' => $membershipId];
        [$rows, $next] = $this->page(
            $query,
            'learner-enrollments',
            $context->organizationId,
            $filters,
            $limit,
            $cursor,
            $this->enrollmentColumns(),
            'enrollments.id',
        );
        $trainers = $this->authorization->allows(
            $actor,
            LearnerAdministrationAuthorization::TRAINERS_READ,
        )
            ? $this->trainersForCohorts(
                $context->organizationId,
                array_values($rows->pluck('cohort_id')
                    ->filter(static fn (mixed $id): bool => is_string($id))
                    ->unique()
                    ->values()
                    ->all()),
            )
            : null;

        return [
            'data' => array_values($rows->map(
                fn (stdClass $row): array => $this->enrollmentProjection(
                    $actor,
                    $row,
                    $membership,
                    $trainers,
                ),
            )->all()),
            'next_cursor' => $next,
            'allowed_actions' => [],
        ];
    }

    /**
     * @param  array{status?: string}  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, allowed_actions: list<string>, choices: array{roles: list<array<string, mixed>>, cohorts: list<array<string, mixed>>}}
     */
    public function invitations(
        User $actor,
        array $filters,
        int $limit,
        ?string $cursor,
    ): array {
        $this->authorization->assertAll(
            $actor,
            LearnerAdministrationAuthorization::INVITATION_PERMISSIONS,
        );
        $context = $this->authorization->contextFor($actor);
        $query = $this->invitationBaseQuery($context->organizationId);

        if (isset($filters['status'])) {
            $query->where('institution_invites.status', $filters['status']);
        }

        [$rows, $next] = $this->page(
            $query,
            'learner-invitations',
            $context->organizationId,
            $filters,
            $limit,
            $cursor,
            $this->invitationColumns(),
            'institution_invites.id',
        );

        return [
            'data' => array_values($rows->map(
                fn (stdClass $row): array => $this->invitationProjection($row),
            )->all()),
            'next_cursor' => $next,
            'allowed_actions' => ['learner_invitation.issue'],
            'choices' => [
                'roles' => $this->learnerSafeRoleChoices($context->organizationId),
                'cohorts' => $this->activeCohortChoices($context->organizationId),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function commandResult(
        User $actor,
        OrganizationAdministrationCommandResult $result,
    ): array {
        return match ($result->resultType) {
            'learner' => $this->learnerByIdForCommand($actor, $result->resultId),
            'learner_enrollment' => $this->enrollmentByIdForCommand($actor, $result->resultId),
            'learner_invitation' => $this->invitationByIdForCommand($actor, $result->resultId),
            default => throw new LearnerAdministrationRejected,
        };
    }

    private function learnerBaseQuery(string $organizationId): Builder
    {
        return DB::table('memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->leftJoin('learner_profiles', function ($join): void {
                $join
                    ->on('learner_profiles.organization_id', '=', 'memberships.organization_id')
                    ->on('learner_profiles.learner_membership_id', '=', 'memberships.id');
            })
            ->leftJoin('learner_profile_current_goals', function ($join): void {
                $join
                    ->on('learner_profile_current_goals.organization_id', '=', 'learner_profiles.organization_id')
                    ->on('learner_profile_current_goals.learner_profile_id', '=', 'learner_profiles.id')
                    ->on('learner_profile_current_goals.learner_membership_id', '=', 'memberships.id');
            })
            ->where('memberships.organization_id', $organizationId)
            ->where('memberships.membership_type', Membership::TYPE_LEARNER)
            ->selectSub(function (Builder $attempts): void {
                $attempts->from('learner_attempts')
                    ->whereColumn('learner_attempts.organization_id', 'memberships.organization_id')
                    ->whereColumn('learner_attempts.learner_membership_id', 'memberships.id')
                    ->selectRaw('COUNT(*)');
            }, 'attempt_count')
            ->selectSub(function (Builder $attempts): void {
                $attempts->from('learner_attempts')
                    ->whereColumn('learner_attempts.organization_id', 'memberships.organization_id')
                    ->whereColumn('learner_attempts.learner_membership_id', 'memberships.id')
                    ->selectRaw('MAX(COALESCE(submitted_at, started_at))');
            }, 'latest_attempt_at')
            ->selectSub(function (Builder $enrollments): void {
                $enrollments->from('enrollments')
                    ->whereColumn('enrollments.organization_id', 'memberships.organization_id')
                    ->whereColumn('enrollments.learner_membership_id', 'memberships.id')
                    ->whereIn('enrollments.status', [
                        Enrollment::STATUS_PENDING_CURRICULUM,
                        Enrollment::STATUS_ACTIVE,
                    ])
                    ->selectRaw('COUNT(*)');
            }, 'current_enrollment_count')
            ->addSelect($this->learnerColumns());
    }

    /**
     * @return list<string>
     */
    private function learnerColumns(): array
    {
        return [
            'memberships.id',
            'memberships.status',
            'memberships.joined_at',
            'memberships.ended_at',
            'memberships.version',
            'users.display_name',
            'learner_profiles.id as learner_profile_id',
            'learner_profiles.adult_confirmed_at',
            'learner_profiles.onboarding_completed_at',
            'learner_profile_current_goals.goal_key',
            'learner_profile_current_goals.version as current_goal_version',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function learnerProjection(User $actor, stdClass $row): array
    {
        $status = $this->string($row, 'status');
        $profileId = $this->nullableString($row, 'learner_profile_id');
        $goalKey = $this->nullableString($row, 'goal_key');
        $actions = [];

        if (
            $status === Membership::STATUS_ACTIVE
            && $profileId !== null
            && $goalKey !== null
            && $this->authorization->allows(
                $actor,
                LearnerAdministrationAuthorization::PROFILE_CORRECT,
            )
        ) {
            $actions[] = 'learner.goal_correct';
        }

        if ($this->authorization->allows(
            $actor,
            LearnerAdministrationAuthorization::LIFECYCLE_MANAGE,
        )) {
            if ($status === Membership::STATUS_ACTIVE) {
                $actions[] = 'learner.suspend';
            } elseif ($status === Membership::STATUS_SUSPENDED) {
                $actions[] = 'learner.reactivate';
            }
        }

        if (
            $status === Membership::STATUS_ACTIVE
            && $this->integer($row, 'current_enrollment_count') === 0
            && $this->authorization->allows(
                $actor,
                LearnerAdministrationAuthorization::ENROLLMENTS_MANAGE,
            )
        ) {
            $actions[] = 'learner_enrollment.create';
        }

        return [
            'membership_id' => $this->string($row, 'id'),
            'display_name' => $this->nullableString($row, 'display_name'),
            'status' => $status,
            'joined_at' => $this->date($row, 'joined_at'),
            'ended_at' => $this->date($row, 'ended_at'),
            'version' => $this->integer($row, 'version'),
            'profile' => $profileId === null ? null : [
                'id' => $profileId,
                'adult_confirmed' => $this->value($row, 'adult_confirmed_at') !== null,
                'onboarding_completed' => $this->value($row, 'onboarding_completed_at') !== null,
                'current_goal_key' => $goalKey,
                'current_goal_version' => $this->nullableInteger($row, 'current_goal_version'),
            ],
            'attempt_count' => $this->integer($row, 'attempt_count'),
            'latest_attempt_at' => $this->date($row, 'latest_attempt_at'),
            'allowed_actions' => $actions,
        ];
    }

    private function enrollmentBaseQuery(
        string $organizationId,
        string $membershipId,
    ): Builder {
        return DB::table('enrollments')
            ->leftJoin('cohorts', function ($join): void {
                $join
                    ->on('cohorts.organization_id', '=', 'enrollments.organization_id')
                    ->on('cohorts.id', '=', 'enrollments.cohort_id');
            })
            ->where('enrollments.organization_id', $organizationId)
            ->where('enrollments.learner_membership_id', $membershipId);
    }

    /**
     * @return list<string>
     */
    private function enrollmentColumns(): array
    {
        return [
            'enrollments.id',
            'enrollments.learner_membership_id',
            'enrollments.status',
            'enrollments.version',
            'enrollments.enrolled_at',
            'enrollments.activated_at',
            'enrollments.ended_at',
            'enrollments.cohort_id',
            'enrollments.curriculum_owner_organization_id',
            'enrollments.curriculum_version_id',
            'enrollments.curriculum_publication_id',
            'enrollments.curriculum_share_grant_id',
            'cohorts.key as cohort_key',
            'cohorts.name as cohort_name',
            'cohorts.status as cohort_status',
            'cohorts.version as cohort_version',
        ];
    }

    /**
     * @param  array<string, list<array{membership_id: string, display_name: string|null}>>|null  $trainers
     * @return array<string, mixed>
     */
    private function enrollmentProjection(
        User $actor,
        stdClass $row,
        stdClass $membership,
        ?array $trainers,
    ): array {
        $status = $this->string($row, 'status');
        $membershipActive = $this->string($membership, 'status') === Membership::STATUS_ACTIVE;
        $cohortId = $this->nullableString($row, 'cohort_id');
        $current = in_array($status, [
            Enrollment::STATUS_PENDING_CURRICULUM,
            Enrollment::STATUS_ACTIVE,
        ], true);
        $actions = [];

        if (
            $current
            && $membershipActive
            && $this->authorization->allows(
                $actor,
                LearnerAdministrationAuthorization::ENROLLMENTS_MANAGE,
            )
        ) {
            $actions[] = 'learner_enrollment.change_cohort';
        }

        if (
            $status === Enrollment::STATUS_PENDING_CURRICULUM
            && $membershipActive
            && $this->nullableString($row, 'curriculum_publication_id') === null
            && $this->authorization->allows(
                $actor,
                LearnerAdministrationAuthorization::CURRICULUM_ASSIGN,
            )
        ) {
            $actions[] = 'learner_enrollment.activate_curriculum';
        }

        if (
            $current
            && $this->authorization->allows(
                $actor,
                LearnerAdministrationAuthorization::ENROLLMENTS_MANAGE,
            )
        ) {
            $actions[] = 'learner_enrollment.withdraw';
        }

        return [
            'id' => $this->string($row, 'id'),
            'learner_membership_id' => $this->string($row, 'learner_membership_id'),
            'status' => $status,
            'version' => $this->integer($row, 'version'),
            'enrolled_at' => $this->date($row, 'enrolled_at'),
            'activated_at' => $this->date($row, 'activated_at'),
            'ended_at' => $this->date($row, 'ended_at'),
            'cohort' => $cohortId === null ? null : [
                'id' => $cohortId,
                'key' => $this->nullableString($row, 'cohort_key'),
                'name' => $this->nullableString($row, 'cohort_name'),
                'status' => $this->nullableString($row, 'cohort_status'),
                'version' => $this->nullableInteger($row, 'cohort_version'),
            ],
            'curriculum_pin' => $this->nullableString($row, 'curriculum_publication_id') === null
                ? null
                : [
                    'owner_organization_id' => $this->nullableString($row, 'curriculum_owner_organization_id'),
                    'version_id' => $this->nullableString($row, 'curriculum_version_id'),
                    'publication_id' => $this->nullableString($row, 'curriculum_publication_id'),
                    'share_grant_id' => $this->nullableString($row, 'curriculum_share_grant_id'),
                ],
            'trainers' => $trainers === null
                ? null
                : ($cohortId === null ? [] : ($trainers[$cohortId] ?? [])),
            'allowed_actions' => $actions,
        ];
    }

    private function invitationBaseQuery(string $organizationId): Builder
    {
        return DB::table('institution_invites')
            ->leftJoin('roles', function ($join): void {
                $join
                    ->on('roles.organization_id', '=', 'institution_invites.organization_id')
                    ->on('roles.id', '=', 'institution_invites.role_id');
            })
            ->leftJoin('cohorts', function ($join): void {
                $join
                    ->on('cohorts.organization_id', '=', 'institution_invites.organization_id')
                    ->on('cohorts.id', '=', 'institution_invites.cohort_id');
            })
            ->where('institution_invites.organization_id', $organizationId)
            ->where('institution_invites.membership_type', Membership::TYPE_LEARNER);
    }

    /**
     * @return list<string>
     */
    private function invitationColumns(): array
    {
        return [
            'institution_invites.id',
            'institution_invites.status',
            'institution_invites.version',
            'institution_invites.expires_at',
            'institution_invites.accepted_at',
            'institution_invites.revoked_at',
            'institution_invites.created_at',
            'institution_invites.updated_at',
            'institution_invites.role_id',
            'institution_invites.cohort_id',
            'roles.key as role_key',
            'roles.name as role_name',
            'roles.status as role_status',
            'roles.version as role_version',
            'cohorts.key as cohort_key',
            'cohorts.name as cohort_name',
            'cohorts.status as cohort_status',
            'cohorts.version as cohort_version',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationProjection(stdClass $row): array
    {
        $roleId = $this->nullableString($row, 'role_id');
        $cohortId = $this->nullableString($row, 'cohort_id');
        $status = $this->string($row, 'status');
        $expiresAt = $this->carbon($row, 'expires_at');

        return [
            'id' => $this->string($row, 'id'),
            'status' => $status,
            'version' => $this->integer($row, 'version'),
            'role' => $roleId === null ? null : [
                'id' => $roleId,
                'key' => $this->nullableString($row, 'role_key'),
                'name' => $this->nullableString($row, 'role_name'),
                'status' => $this->nullableString($row, 'role_status'),
                'version' => $this->nullableInteger($row, 'role_version'),
            ],
            'cohort' => $cohortId === null ? null : [
                'id' => $cohortId,
                'key' => $this->nullableString($row, 'cohort_key'),
                'name' => $this->nullableString($row, 'cohort_name'),
                'status' => $this->nullableString($row, 'cohort_status'),
                'version' => $this->nullableInteger($row, 'cohort_version'),
            ],
            'expires_at' => $expiresAt->toIso8601String(),
            'accepted_at' => $this->date($row, 'accepted_at'),
            'revoked_at' => $this->date($row, 'revoked_at'),
            'created_at' => $this->date($row, 'created_at'),
            'updated_at' => $this->date($row, 'updated_at'),
            'allowed_actions' => $status === 'pending' && $expiresAt->isFuture()
                ? ['learner_invitation.revoke']
                : [],
        ];
    }

    /**
     * @return list<array{id: string, key: string, name: string, version: int}>
     */
    private function learnerSafeRoleChoices(string $organizationId): array
    {
        return array_values(DB::table('roles')
            ->join('role_permissions', function ($join): void {
                $join
                    ->on('role_permissions.organization_id', '=', 'roles.organization_id')
                    ->on('role_permissions.role_id', '=', 'roles.id');
            })
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.organization_id', $organizationId)
            ->where('roles.status', 'active')
            ->groupBy('roles.id', 'roles.key', 'roles.name', 'roles.version')
            ->havingRaw('COUNT(*) = 1')
            ->havingRaw('MIN(permissions.key) = ?', ['learning.participate'])
            ->orderBy('roles.id')
            ->limit(self::MAX_PAGE_SIZE)
            ->get(['roles.id', 'roles.key', 'roles.name', 'roles.version'])
            ->map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'key' => $this->string($row, 'key'),
                'name' => $this->string($row, 'name'),
                'version' => $this->integer($row, 'version'),
            ])->all());
    }

    /**
     * @return list<array{id: string, key: string, name: string, version: int}>
     */
    private function activeCohortChoices(string $organizationId): array
    {
        return array_values(DB::table('cohorts')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(self::MAX_PAGE_SIZE)
            ->get(['id', 'key', 'name', 'version'])
            ->map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'key' => $this->string($row, 'key'),
                'name' => $this->string($row, 'name'),
                'version' => $this->integer($row, 'version'),
            ])->all());
    }

    /**
     * @param  list<string>  $cohortIds
     * @return array<string, list<array{membership_id: string, display_name: string|null}>>
     */
    private function trainersForCohorts(
        string $organizationId,
        array $cohortIds,
    ): array {
        if ($cohortIds === []) {
            return [];
        }

        $rows = DB::table('cohort_trainers')
            ->join('memberships', function ($join): void {
                $join
                    ->on('memberships.organization_id', '=', 'cohort_trainers.organization_id')
                    ->on('memberships.id', '=', 'cohort_trainers.trainer_membership_id');
            })
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('cohort_trainers.organization_id', $organizationId)
            ->whereIn('cohort_trainers.cohort_id', $cohortIds)
            ->where('memberships.membership_type', Membership::TYPE_TRAINER)
            ->where('memberships.status', Membership::STATUS_ACTIVE)
            ->orderBy('cohort_trainers.cohort_id')
            ->orderBy('memberships.id')
            ->get([
                'cohort_trainers.cohort_id',
                'memberships.id as membership_id',
                'users.display_name',
            ]);
        $map = [];

        foreach ($rows as $row) {
            $cohortId = $this->string($row, 'cohort_id');
            $map[$cohortId] ??= [];
            $map[$cohortId][] = [
                'membership_id' => $this->string($row, 'membership_id'),
                'display_name' => $this->nullableString($row, 'display_name'),
            ];
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function learnerById(User $actor, string $membershipId): array
    {
        $context = $this->authorization->contextFor($actor);
        $row = $this->learnerBaseQuery($context->organizationId)
            ->where('memberships.id', $membershipId)
            ->first($this->learnerColumns());

        if (! $row instanceof stdClass) {
            throw new LearnerAdministrationRejected;
        }

        return $this->learnerProjection($actor, $row);
    }

    /**
     * @return array<string, mixed>
     */
    private function learnerByIdForCommand(User $actor, string $membershipId): array
    {
        $this->assertAny($actor, [
            LearnerAdministrationAuthorization::LEARNERS_READ,
            LearnerAdministrationAuthorization::PROFILE_CORRECT,
            LearnerAdministrationAuthorization::LIFECYCLE_MANAGE,
            LearnerAdministrationAuthorization::ENROLLMENTS_MANAGE,
        ]);

        return $this->learnerById($actor, $membershipId);
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentByIdForCommand(User $actor, string $enrollmentId): array
    {
        $this->assertAny($actor, [
            LearnerAdministrationAuthorization::ENROLLMENTS_READ,
            LearnerAdministrationAuthorization::ENROLLMENTS_MANAGE,
            LearnerAdministrationAuthorization::CURRICULUM_ASSIGN,
        ]);
        $context = $this->authorization->contextFor($actor);
        $row = DB::table('enrollments')
            ->leftJoin('cohorts', function ($join): void {
                $join
                    ->on('cohorts.organization_id', '=', 'enrollments.organization_id')
                    ->on('cohorts.id', '=', 'enrollments.cohort_id');
            })
            ->where('enrollments.organization_id', $context->organizationId)
            ->where('enrollments.id', $enrollmentId)
            ->first($this->enrollmentColumns());

        if (! $row instanceof stdClass) {
            throw new LearnerAdministrationRejected;
        }

        $membership = $this->learnerMembership(
            $context->organizationId,
            $this->string($row, 'learner_membership_id'),
        );
        $cohortId = $this->nullableString($row, 'cohort_id');
        $trainers = $this->authorization->allows(
            $actor,
            LearnerAdministrationAuthorization::TRAINERS_READ,
        )
            ? $this->trainersForCohorts(
                $context->organizationId,
                $cohortId === null ? [] : [$cohortId],
            )
            : null;

        return $this->enrollmentProjection($actor, $row, $membership, $trainers);
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationByIdForCommand(User $actor, string $invitationId): array
    {
        $this->assertAny($actor, LearnerAdministrationAuthorization::INVITATION_PERMISSIONS);
        $context = $this->authorization->contextFor($actor);
        $row = $this->invitationBaseQuery($context->organizationId)
            ->where('institution_invites.id', $invitationId)
            ->first($this->invitationColumns());

        if (! $row instanceof stdClass) {
            throw new LearnerAdministrationRejected;
        }

        return $this->invitationProjection($row);
    }

    private function learnerMembership(
        string $organizationId,
        string $membershipId,
    ): stdClass {
        $membership = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->where('membership_type', Membership::TYPE_LEARNER)
            ->first(['id', 'status', 'version']);

        if (! $membership instanceof stdClass) {
            throw new LearnerAdministrationRejected;
        }

        return $membership;
    }

    private function assertActiveCohort(string $organizationId, string $cohortId): void
    {
        if (! DB::table('cohorts')
            ->where('organization_id', $organizationId)
            ->where('id', $cohortId)
            ->where('status', 'active')
            ->exists()) {
            throw new LearnerAdministrationRejected;
        }
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

        throw new LearnerAdministrationRejected;
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
        string $idColumn,
    ): array {
        $limit = max(1, min(self::MAX_PAGE_SIZE, $limit));
        $purpose = 'admin-learner.'.$resource.'.'.CanonicalJson::sha256($filters);

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

    private function value(stdClass $row, string $property): mixed
    {
        return $row->{$property} ?? null;
    }

    private function string(stdClass $row, string $property): string
    {
        return $this->nullableString($row, $property)
            ?? throw new LearnerAdministrationRejected;
    }

    private function nullableString(stdClass $row, string $property): ?string
    {
        $value = $this->value($row, $property);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integer(stdClass $row, string $property): int
    {
        return $this->nullableInteger($row, $property)
            ?? throw new LearnerAdministrationRejected;
    }

    private function nullableInteger(stdClass $row, string $property): ?int
    {
        $value = $this->value($row, $property);

        if ($value === null) {
            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        throw new LearnerAdministrationRejected;
    }

    private function carbon(stdClass $row, string $property): CarbonImmutable
    {
        $value = $this->value($row, $property);

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value);
        }

        throw new LearnerAdministrationRejected;
    }

    private function date(stdClass $row, string $property): ?string
    {
        $value = $this->value($row, $property);

        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->toIso8601String();
        }

        return is_string($value)
            ? CarbonImmutable::parse($value)->toIso8601String()
            : throw new LearnerAdministrationRejected;
    }
}
