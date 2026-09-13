<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Http\Requests\Operator\LearnerAdministration\ActivateLearnerEnrollmentRequest;
use App\Http\Requests\Operator\LearnerAdministration\ChangeLearnerEnrollmentCohortRequest;
use App\Http\Requests\Operator\LearnerAdministration\CorrectLearnerGoalRequest;
use App\Http\Requests\Operator\LearnerAdministration\CreateLearnerEnrollmentRequest;
use App\Http\Requests\Operator\LearnerAdministration\IssueLearnerInvitationRequest;
use App\Http\Requests\Operator\LearnerAdministration\ListLearnerAdministrationRequest;
use App\Http\Requests\Operator\LearnerAdministration\VersionedLearnerAdministrationRequest;
use App\Models\User;
use App\Operator\Exceptions\InvalidOperatorCursor;
use App\Operator\LearnerAdministration\Exceptions\LearnerAdministrationRejected;
use App\Operator\LearnerAdministration\LearnerAdministrationCommands;
use App\Operator\LearnerAdministration\LearnerAdministrationQuery;
use App\Operator\OrganizationAdministration\OrganizationAdministrationCommandResult;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class OperatorLearnerAdministrationController extends Controller
{
    public function __construct(
        private readonly LearnerAdministrationQuery $queries,
        private readonly LearnerAdministrationCommands $commands,
    ) {}

    public function learners(ListLearnerAdministrationRequest $request): JsonResponse
    {
        return $this->read(fn (): array => $this->queries->learners(
            $this->actor($request),
            $this->filters($request, ['status', 'goal_key', 'cohort_id', 'q']),
            $this->limit($request),
            $this->cursor($request),
        ));
    }

    public function learner(
        ListLearnerAdministrationRequest $request,
        string $learner,
    ): JsonResponse {
        return $this->read(fn (): array => $this->queries->learner(
            $this->actor($request),
            $learner,
        ));
    }

    public function enrollments(
        ListLearnerAdministrationRequest $request,
        string $learner,
    ): JsonResponse {
        return $this->read(fn (): array => $this->queries->enrollments(
            $this->actor($request),
            $learner,
            $this->limit($request),
            $this->cursor($request),
        ));
    }

    public function invitations(ListLearnerAdministrationRequest $request): JsonResponse
    {
        return $this->read(fn (): array => $this->queries->invitations(
            $this->actor($request),
            $this->filters($request, ['status']),
            $this->limit($request),
            $this->cursor($request),
        ));
    }

    public function issueInvitation(IssueLearnerInvitationRequest $request): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->issueInvitation(
                actor: $this->actor($request),
                roleId: (string) $request->validated('role_id'),
                expectedRoleVersion: (int) $request->validated('expected_role_version'),
                cohortId: $this->nullableString($request->validated('cohort_id')),
                expectedCohortVersion: $this->nullableInteger(
                    $request->validated('expected_cohort_version'),
                ),
                expiresInHours: (int) $request->validated('expires_in_hours'),
                expectedVersion: (int) $request->validated('expected_version'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ), true, true);
    }

    public function revokeInvitation(
        VersionedLearnerAdministrationRequest $request,
        string $invitation,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->revokeInvitation(
                $this->actor($request),
                $invitation,
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function correctGoal(
        CorrectLearnerGoalRequest $request,
        string $learner,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->correctGoal(
                $this->actor($request),
                $learner,
                (string) $request->validated('goal_key'),
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function createEnrollment(
        CreateLearnerEnrollmentRequest $request,
        string $learner,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->createEnrollment(
                actor: $this->actor($request),
                membershipId: $learner,
                expectedMembershipVersion: (int) $request->validated('expected_membership_version'),
                cohortId: $this->nullableString($request->validated('cohort_id')),
                expectedCohortVersion: $this->nullableInteger(
                    $request->validated('expected_cohort_version'),
                ),
                expectedVersion: (int) $request->validated('expected_version'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ), true);
    }

    public function changeEnrollmentCohort(
        ChangeLearnerEnrollmentCohortRequest $request,
        string $enrollment,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->changeEnrollmentCohort(
                actor: $this->actor($request),
                enrollmentId: $enrollment,
                cohortId: $this->nullableString($request->validated('cohort_id')),
                expectedCohortVersion: $this->nullableInteger(
                    $request->validated('expected_cohort_version'),
                ),
                expectedVersion: (int) $request->validated('expected_version'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function activateEnrollment(
        ActivateLearnerEnrollmentRequest $request,
        string $enrollment,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->activateEnrollment(
                actor: $this->actor($request),
                enrollmentId: $enrollment,
                publicationId: (string) $request->validated('publication_id'),
                shareGrantId: $this->nullableString($request->validated('share_grant_id')),
                expectedVersion: (int) $request->validated('expected_version'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function withdrawEnrollment(
        VersionedLearnerAdministrationRequest $request,
        string $enrollment,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->withdrawEnrollment(
                $this->actor($request),
                $enrollment,
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function suspendLearner(
        VersionedLearnerAdministrationRequest $request,
        string $learner,
    ): JsonResponse {
        return $this->changeLearnerStatus($request, $learner, true);
    }

    public function reactivateLearner(
        VersionedLearnerAdministrationRequest $request,
        string $learner,
    ): JsonResponse {
        return $this->changeLearnerStatus($request, $learner, false);
    }

    private function changeLearnerStatus(
        VersionedLearnerAdministrationRequest $request,
        string $learner,
        bool $suspend,
    ): JsonResponse {
        return $this->command(function () use ($request, $learner, $suspend): OrganizationAdministrationCommandResult {
            $arguments = [
                $this->actor($request),
                $learner,
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            ];

            return $suspend
                ? $this->commands->suspendMembership(...$arguments)
                : $this->commands->reactivateMembership(...$arguments);
        });
    }

    /**
     * @param  Closure(): array<string, mixed>  $query
     */
    private function read(Closure $query): JsonResponse
    {
        try {
            $data = $query();
        } catch (InvalidOperatorCursor) {
            throw ValidationException::withMessages([
                'cursor' => ['The cursor is invalid or expired.'],
            ]);
        }

        return response()->json($data)
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @param  Closure(): OrganizationAdministrationCommandResult  $command
     */
    private function command(
        Closure $command,
        bool $createdOnFirst = false,
        bool $includeActivationCode = false,
    ): JsonResponse {
        $result = $command();
        $user = request()->user();

        if (! $user instanceof User) {
            throw new LearnerAdministrationRejected;
        }

        $payload = [
            'data' => $this->queries->commandResult($user, $result),
            'replayed' => $result->replayed,
        ];

        if ($includeActivationCode) {
            $payload['activation_code'] = $result->activationCode;
        }

        return response()->json(
            $payload,
            $createdOnFirst && ! $result->replayed
                ? Response::HTTP_CREATED
                : Response::HTTP_OK,
        )->header('Cache-Control', 'private, no-store');
    }

    private function actor(Request $request): User
    {
        return AuthenticatedPrincipal::fromRequest($request)->user;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function filters(
        ListLearnerAdministrationRequest $request,
        array $keys,
    ): array {
        $filters = [];

        foreach ($keys as $key) {
            $value = $request->validated($key);

            if (is_string($value)) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    private function limit(ListLearnerAdministrationRequest $request): int
    {
        return (int) ($request->validated('limit') ?? 25);
    }

    private function cursor(ListLearnerAdministrationRequest $request): ?string
    {
        return $this->nullableString($request->validated('cursor'));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }
}
