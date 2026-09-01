<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\AuthenticatedPrincipal;
use App\Http\Requests\Operator\EntitlementAdministration\EmptyEntitlementCommandRequest;
use App\Http\Requests\Operator\EntitlementAdministration\GrantLearnerEntitlementRequest;
use App\Http\Requests\Operator\EntitlementAdministration\UpdateEntitlementPlanRequest;
use App\Http\Requests\Operator\EntitlementAdministration\UpdateEntitlementTaxRequest;
use App\Models\User;
use App\Operator\EntitlementAdministration\EntitlementAdministrationCommands;
use App\Operator\EntitlementAdministration\EntitlementAdministrationQuery;
use App\Operator\EntitlementAdministration\Exceptions\EntitlementAdministrationRejected;
use App\Operator\OrganizationAdministration\OrganizationAdministrationCommandResult;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperatorEntitlementAdministrationController extends Controller
{
    public function __construct(
        private readonly EntitlementAdministrationQuery $queries,
        private readonly EntitlementAdministrationCommands $commands,
    ) {}

    public function workspace(Request $request): JsonResponse
    {
        return $this->read(fn (): array => $this->queries->workspace($this->actor($request)));
    }

    public function learner(Request $request, string $learner): JsonResponse
    {
        return $this->read(
            fn (): array => $this->queries->learnerEntitlement($this->actor($request), $learner),
        );
    }

    public function updateTax(UpdateEntitlementTaxRequest $request): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->updateTax(
                actor: $this->actor($request),
                gstRegistered: (bool) $request->validated('gst_registered'),
                gstin: (string) $request->validated('gstin'),
                gstRateBps: (int) $request->validated('gst_rate_bps'),
                expectedVersion: (int) $request->validated('expected_version'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function updatePlan(UpdateEntitlementPlanRequest $request, string $plan): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->updatePlan(
                actor: $this->actor($request),
                planId: $plan,
                name: (string) $request->validated('name'),
                amountExGstPaise: (int) $request->validated('amount_ex_gst_paise'),
                durationValue: (int) $request->validated('duration_value'),
                allowsRoleplay: (bool) $request->validated('allows_roleplay'),
                maxLevelNumber: $request->validated('max_level_number') === null
                    ? null
                    : (int) $request->validated('max_level_number'),
                liveAiCapSeconds: $request->validated('live_ai_cap_seconds') === null
                    ? null
                    : (int) $request->validated('live_ai_cap_seconds'),
                status: (string) $request->validated('status'),
                expectedVersion: (int) $request->validated('expected_version'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function grantCourtesyPremium(EmptyEntitlementCommandRequest $request): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->grantCourtesyPremium(
                actor: $this->actor($request),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function grant(GrantLearnerEntitlementRequest $request, string $learner): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->grant(
                actor: $this->actor($request),
                learnerMembershipId: $learner,
                planId: (string) $request->validated('plan_id'),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ), true);
    }

    public function restartClock(EmptyEntitlementCommandRequest $request, string $entitlement): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->restartDemoClock(
                actor: $this->actor($request),
                entitlementId: $entitlement,
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function restartMeter(EmptyEntitlementCommandRequest $request, string $entitlement): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->restartDemoMeter(
                actor: $this->actor($request),
                entitlementId: $entitlement,
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function revoke(EmptyEntitlementCommandRequest $request, string $entitlement): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->revoke(
                actor: $this->actor($request),
                entitlementId: $entitlement,
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ));
    }

    public function clearThrottle(EmptyEntitlementCommandRequest $request, string $learner): JsonResponse
    {
        return $this->command(fn (): OrganizationAdministrationCommandResult => (
            $this->commands->clearSignInThrottle(
                actor: $this->actor($request),
                learnerMembershipId: $learner,
                idempotencyKey: (string) $request->validated('idempotency_key'),
            )
        ));
    }

    /**
     * @param  Closure(): array<string, mixed>  $query
     */
    private function read(Closure $query): JsonResponse
    {
        return response()->json($query())
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @param  Closure(): OrganizationAdministrationCommandResult  $command
     */
    private function command(Closure $command, bool $createdOnFirst = false): JsonResponse
    {
        $result = $command();
        $user = request()->user();

        if (! $user instanceof User) {
            throw new EntitlementAdministrationRejected;
        }

        return response()->json([
            'data' => $this->queries->commandResult($user, $result),
            'replayed' => $result->replayed,
        ], $createdOnFirst && ! $result->replayed
            ? Response::HTTP_CREATED
            : Response::HTTP_OK,
        )->header('Cache-Control', 'private, no-store');
    }

    private function actor(Request $request): User
    {
        return AuthenticatedPrincipal::fromRequest($request)->user;
    }
}
