<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Http\Requests\Operator\OrganizationAdministration\ChangeMembershipRoleRequest;
use App\Http\Requests\Operator\OrganizationAdministration\ChangeTrainerCohortRequest;
use App\Http\Requests\Operator\OrganizationAdministration\CreateAuthorityAppointmentRequest;
use App\Http\Requests\Operator\OrganizationAdministration\CreateBranchRequest;
use App\Http\Requests\Operator\OrganizationAdministration\CreateCohortRequest;
use App\Http\Requests\Operator\OrganizationAdministration\CreateRoleRequest;
use App\Http\Requests\Operator\OrganizationAdministration\IssueInvitationRequest;
use App\Http\Requests\Operator\OrganizationAdministration\ListOrganizationAdministrationRequest;
use App\Http\Requests\Operator\OrganizationAdministration\RevokeAuthorityAppointmentRequest;
use App\Http\Requests\Operator\OrganizationAdministration\StateFingerprintCommandRequest;
use App\Http\Requests\Operator\OrganizationAdministration\UpdateBranchRequest;
use App\Http\Requests\Operator\OrganizationAdministration\UpdateCohortRequest;
use App\Http\Requests\Operator\OrganizationAdministration\UpdateRoleRequest;
use App\Http\Requests\Operator\OrganizationAdministration\VersionedCommandRequest;
use App\Models\User;
use App\Operator\Exceptions\InvalidOperatorCursor;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationRejected;
use App\Operator\OrganizationAdministration\OrganizationAdministrationCommandResult;
use App\Operator\OrganizationAdministration\OrganizationAdministrationCommands;
use App\Operator\OrganizationAdministration\OrganizationAdministrationQuery;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class OperatorOrganizationAdministrationController extends Controller
{
    public function __construct(
        private readonly OrganizationAdministrationQuery $queries,
        private readonly OrganizationAdministrationCommands $commands,
    ) {}

    public function organization(Request $request): JsonResponse
    {
        return $this->read(
            fn (): array => $this->queries->organization($this->actor($request)),
        );
    }

    public function branches(ListOrganizationAdministrationRequest $request): JsonResponse
    {
        return $this->read(fn (): array => $this->queries->branches(
            $this->actor($request),
            $this->filters($request, ['status']),
            $this->limit($request),
            $this->cursor($request),
        ));
    }

    public function createBranch(CreateBranchRequest $request): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->createBranch(
                $this->actor($request),
                (string) $request->validated('key'),
                (string) $request->validated('name'),
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ), true);
    }

    public function updateBranch(
        UpdateBranchRequest $request,
        string $branch,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->updateBranch(
                $this->actor($request),
                $branch,
                (string) $request->validated('name'),
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function archiveBranch(
        VersionedCommandRequest $request,
        string $branch,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->archiveBranch(
                $this->actor($request),
                $branch,
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function cohorts(ListOrganizationAdministrationRequest $request): JsonResponse
    {
        return $this->read(fn (): array => $this->queries->cohorts(
            $this->actor($request),
            $this->filters($request, ['status', 'branch_id']),
            $this->limit($request),
            $this->cursor($request),
        ));
    }

    public function createCohort(CreateCohortRequest $request): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->createCohort(
                $this->actor($request),
                (string) $request->validated('key'),
                (string) $request->validated('name'),
                $this->nullableString($request->validated('branch_id')),
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ), true);
    }

    public function updateCohort(
        UpdateCohortRequest $request,
        string $cohort,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->updateCohort(
                $this->actor($request),
                $cohort,
                (string) $request->validated('name'),
                $this->nullableString($request->validated('branch_id')),
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function archiveCohort(
        VersionedCommandRequest $request,
        string $cohort,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->archiveCohort(
                $this->actor($request),
                $cohort,
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function memberships(ListOrganizationAdministrationRequest $request): JsonResponse
    {
        return $this->read(fn (): array => $this->queries->memberships(
            $this->actor($request),
            $this->filters($request, ['status', 'display_name_prefix']),
            $this->limit($request),
            $this->cursor($request),
        ));
    }

    public function createMembership(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:4', 'max:64'],
            'password' => ['required', 'string', 'min:15', 'max:128'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'membership_type' => ['required', 'string', 'in:organization_admin,trainer'],
            'role_id' => ['nullable', 'string', 'ulid'],
            'expected_version' => ['required', 'integer', 'in:0'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);
        $headerKey = $request->header('Idempotency-Key');
        $idempotencyKey = is_string($headerKey) && trim($headerKey) !== ''
            ? trim($headerKey)
            : (string) ($validated['idempotency_key'] ?? '');

        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => ['An idempotency key is required.'],
            ]);
        }

        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->createStaffAccount(
                $this->actor($request),
                (string) $validated['username'],
                (string) $validated['password'],
                $this->nullableString($validated['display_name'] ?? null),
                (string) $validated['membership_type'],
                $this->nullableString($validated['role_id'] ?? null),
                (int) $validated['expected_version'],
                $idempotencyKey,
            )
        ), true);
    }

    public function assignMembershipRole(
        ChangeMembershipRoleRequest $request,
        string $membership,
    ): JsonResponse {
        return $this->changeMembershipRole($request, $membership, true);
    }

    public function revokeMembershipRole(
        ChangeMembershipRoleRequest $request,
        string $membership,
    ): JsonResponse {
        return $this->changeMembershipRole($request, $membership, false);
    }

    public function deactivateMembership(
        VersionedCommandRequest $request,
        string $membership,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->deactivateMembership(
                $this->actor($request),
                $membership,
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function reactivateMembership(
        VersionedCommandRequest $request,
        string $membership,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->reactivateMembership(
                $this->actor($request),
                $membership,
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function invitations(ListOrganizationAdministrationRequest $request): JsonResponse
    {
        return $this->read(fn (): array => $this->queries->invitations(
            $this->actor($request),
            $this->filters($request, ['status']),
            $this->limit($request),
            $this->cursor($request),
        ));
    }

    public function issueInvitation(IssueInvitationRequest $request): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->issueInvitation(
                $this->actor($request),
                (string) $request->validated('membership_type'),
                (int) ($request->validated('expires_in_hours') ?? 72),
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ), true, true);
    }

    public function revokeInvitation(
        VersionedCommandRequest $request,
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

    public function roles(ListOrganizationAdministrationRequest $request): JsonResponse
    {
        return $this->read(fn (): array => $this->queries->roles(
            $this->actor($request),
            $this->filters($request, ['status']),
            $this->limit($request),
            $this->cursor($request),
        ));
    }

    public function createRole(CreateRoleRequest $request): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->createRole(
                $this->actor($request),
                (string) $request->validated('key'),
                (string) $request->validated('name'),
                $this->stringList($request->validated('permission_keys')),
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ), true);
    }

    public function updateRole(
        UpdateRoleRequest $request,
        string $role,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->updateRole(
                $this->actor($request),
                $role,
                (string) $request->validated('name'),
                $this->stringList($request->validated('permission_keys')),
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function retireRole(
        VersionedCommandRequest $request,
        string $role,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->retireRole(
                $this->actor($request),
                $role,
                (int) $request->validated('expected_version'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function trainers(ListOrganizationAdministrationRequest $request): JsonResponse
    {
        return $this->read(fn (): array => $this->queries->trainers(
            $this->actor($request),
            $this->filters($request, ['status', 'display_name_prefix']),
            $this->limit($request),
            $this->cursor($request),
        ));
    }

    public function assignTrainerCohort(
        ChangeTrainerCohortRequest $request,
        string $membership,
    ): JsonResponse {
        return $this->changeTrainerCohort($request, $membership, true);
    }

    public function revokeTrainerCohort(
        ChangeTrainerCohortRequest $request,
        string $membership,
    ): JsonResponse {
        return $this->changeTrainerCohort($request, $membership, false);
    }

    public function appointments(
        ListOrganizationAdministrationRequest $request,
        string $membership,
    ): JsonResponse {
        return $this->read(fn (): array => $this->queries->appointments(
            $this->actor($request),
            $membership,
            $this->filters($request, ['authority_kind', 'state']),
            $this->limit($request),
            $this->cursor($request),
        ));
    }

    public function createAppointment(
        CreateAuthorityAppointmentRequest $request,
        string $membership,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->createAppointment(
                actor: $this->actor($request),
                membershipId: $membership,
                authorityKind: (string) $request->validated('authority_kind'),
                scopeType: (string) $request->validated('scope_type'),
                programId: $this->nullableString($request->validated('program_id')),
                validFrom: CarbonImmutable::parse((string) $request->validated('valid_from')),
                validUntil: $this->nullableDate($request->validated('valid_until')),
                qualificationBasis: (string) $request->validated('qualification_basis'),
                approvalEvidenceKey: (string) $request->validated('approval_evidence_key'),
                expectedMembershipVersion: (int) $request->validated('expected_membership_version'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ), true);
    }

    public function appointment(
        Request $request,
        string $membership,
        string $appointment,
    ): JsonResponse {
        return $this->read(fn (): array => $this->queries->appointment(
            $this->actor($request),
            $membership,
            $appointment,
        ));
    }

    public function acceptAppointment(
        StateFingerprintCommandRequest $request,
        string $membership,
        string $appointment,
    ): JsonResponse {
        return $this->changeAppointment(
            $request,
            $membership,
            $appointment,
            'accept',
        );
    }

    public function surrenderAppointment(
        StateFingerprintCommandRequest $request,
        string $membership,
        string $appointment,
    ): JsonResponse {
        return $this->changeAppointment(
            $request,
            $membership,
            $appointment,
            'surrender',
        );
    }

    public function revokeAppointment(
        RevokeAuthorityAppointmentRequest $request,
        string $membership,
        string $appointment,
    ): JsonResponse {
        return $this->changeAppointment(
            $request,
            $membership,
            $appointment,
            'revoke',
        );
    }

    public function membershipSessions(
        Request $request,
        string $membership,
    ): JsonResponse {
        return $this->read(fn (): array => $this->queries->membershipSessions(
            $this->actor($request),
            $membership,
        ));
    }

    public function revokeMembershipSessions(
        StateFingerprintCommandRequest $request,
        string $membership,
    ): JsonResponse {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->revokeMembershipSessions(
                $this->actor($request),
                $membership,
                (string) $request->validated('state_fingerprint'),
                (string) $request->validated('idempotency_key'),
            )
        ));
    }

    private function changeMembershipRole(
        ChangeMembershipRoleRequest $request,
        string $membership,
        bool $assign,
    ): JsonResponse {
        return $this->command(function () use ($request, $membership, $assign): OrganizationAdministrationCommandResult {
            $arguments = [
                $this->actor($request),
                $membership,
                (string) $request->validated('role_id'),
                (int) $request->validated('expected_membership_version'),
                (int) $request->validated('expected_role_version'),
                (string) $request->validated('idempotency_key'),
            ];

            return $assign
                ? $this->commands->assignMembershipRole(...$arguments)
                : $this->commands->revokeMembershipRole(...$arguments);
        });
    }

    private function changeTrainerCohort(
        ChangeTrainerCohortRequest $request,
        string $membership,
        bool $assign,
    ): JsonResponse {
        return $this->command(function () use ($request, $membership, $assign): OrganizationAdministrationCommandResult {
            $arguments = [
                $this->actor($request),
                $membership,
                (string) $request->validated('cohort_id'),
                (int) $request->validated('expected_membership_version'),
                (int) $request->validated('expected_cohort_version'),
                (string) $request->validated('idempotency_key'),
            ];

            return $assign
                ? $this->commands->assignTrainerCohort(...$arguments)
                : $this->commands->revokeTrainerCohort(...$arguments);
        });
    }

    private function changeAppointment(
        StateFingerprintCommandRequest|RevokeAuthorityAppointmentRequest $request,
        string $membership,
        string $appointment,
        string $operation,
    ): JsonResponse {
        return $this->command(function () use (
            $request,
            $membership,
            $appointment,
            $operation,
        ): OrganizationAdministrationCommandResult {
            $actor = $this->actor($request);
            $stateFingerprint = (string) $request->validated(
                'state_fingerprint',
            );
            $idempotencyKey = (string) $request->validated(
                'idempotency_key',
            );

            return match ($operation) {
                'accept' => $this->commands->acceptAppointment(
                    $actor,
                    $membership,
                    $appointment,
                    $stateFingerprint,
                    $idempotencyKey,
                ),
                'surrender' => $this->commands->surrenderAppointment(
                    $actor,
                    $membership,
                    $appointment,
                    $stateFingerprint,
                    $idempotencyKey,
                ),
                'revoke' => $this->commands->revokeAppointment(
                    $actor,
                    $membership,
                    $appointment,
                    $stateFingerprint,
                    (string) $request->validated('reason_key'),
                    $idempotencyKey,
                ),
                default => throw new OrganizationAdministrationRejected,
            };
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
        $payload = [
            'data' => $this->queries->commandResult(
                request()->user() instanceof User
                    ? request()->user()
                    : throw new OrganizationAdministrationRejected,
                $result,
            ),
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
        ListOrganizationAdministrationRequest $request,
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

    private function limit(ListOrganizationAdministrationRequest $request): int
    {
        return (int) ($request->validated('limit') ?? 25);
    }

    private function cursor(ListOrganizationAdministrationRequest $request): ?string
    {
        return $this->nullableString($request->validated('cursor'));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableDate(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== ''
            ? CarbonImmutable::parse($value)
            : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item),
        ));
    }
}
