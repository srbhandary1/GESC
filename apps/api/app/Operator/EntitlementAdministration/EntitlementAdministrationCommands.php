<?php

declare(strict_types=1);

namespace App\Operator\EntitlementAdministration;

use App\Authentication\AuthenticationSecretDigester;
use App\Entitlements\CourtesyPremiumGrantService;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\EntitlementAccess;
use App\Models\Membership;
use App\Models\User;
use App\Operator\EntitlementAdministration\Exceptions\EntitlementAdministrationConflict;
use App\Operator\EntitlementAdministration\Exceptions\EntitlementAdministrationRejected;
use App\Operator\OrganizationAdministration\OrganizationAdministrationCommandResult;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use stdClass;

final readonly class EntitlementAdministrationCommands
{
    public function __construct(
        private EntitlementAdministrationAuthorization $authorization,
        private EntitlementAdministrationIdempotency $idempotency,
        private EntitlementAccess $access,
        private CourtesyPremiumGrantService $courtesy,
        private AuthenticationSecretDigester $digester,
    ) {}

    public function updateTax(
        User $actor,
        bool $gstRegistered,
        string $gstin,
        int $gstRateBps,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: [EntitlementAdministrationAuthorization::PLANS_MANAGE],
            operation: 'entitlement.tax.update',
            targetType: 'organization_tax_settings',
            targetId: $this->organizationId($actor),
            body: [
                'expected_version' => $expectedVersion,
                'gst_rate_bps' => $gstRateBps,
                'gst_registered' => $gstRegistered,
                'gstin' => $gstin,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($gstRegistered, $gstin, $gstRateBps, $expectedVersion): array {
                $updated = DB::table('organization_tax_settings')
                    ->where('organization_id', $organizationId)
                    ->where('version', $expectedVersion)
                    ->update([
                        'gst_registered' => $gstRegistered,
                        'gstin' => $gstin,
                        'gst_rate_bps' => $gstRateBps,
                        'gstin_is_placeholder' => $gstin === DirectPlanDefaults::PLACEHOLDER_GSTIN,
                        'version' => $expectedVersion + 1,
                        'updated_by_membership_id' => $actorMembershipId,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new EntitlementAdministrationConflict;
                }

                return [
                    'result_type' => 'tax_settings',
                    'result_id' => $organizationId,
                ];
            },
        );
    }

    public function updatePlan(
        User $actor,
        string $planId,
        string $name,
        int $amountExGstPaise,
        int $durationValue,
        bool $allowsRoleplay,
        ?int $maxLevelNumber,
        ?int $liveAiCapSeconds,
        string $status,
        int $expectedVersion,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: [EntitlementAdministrationAuthorization::PLANS_MANAGE],
            operation: 'entitlement.plan.update',
            targetType: 'entitlement_plan',
            targetId: $planId,
            body: [
                'allows_roleplay' => $allowsRoleplay,
                'amount_ex_gst_paise' => $amountExGstPaise,
                'duration_value' => $durationValue,
                'expected_version' => $expectedVersion,
                'live_ai_cap_seconds' => $liveAiCapSeconds,
                'max_level_number' => $maxLevelNumber,
                'name' => $name,
                'status' => $status,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use (
                $planId,
                $name,
                $amountExGstPaise,
                $durationValue,
                $allowsRoleplay,
                $maxLevelNumber,
                $liveAiCapSeconds,
                $status,
                $expectedVersion,
            ): array {
                $updated = DB::table('entitlement_plans')
                    ->where('organization_id', $organizationId)
                    ->where('id', $planId)
                    ->where('version', $expectedVersion)
                    ->update([
                        'name' => $name,
                        'amount_ex_gst_paise' => $amountExGstPaise,
                        'duration_value' => $durationValue,
                        'allows_roleplay' => $allowsRoleplay,
                        'max_level_number' => $maxLevelNumber,
                        'live_ai_cap_seconds' => $liveAiCapSeconds,
                        'status' => $status,
                        'version' => $expectedVersion + 1,
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    throw new EntitlementAdministrationConflict;
                }

                return [
                    'result_type' => 'entitlement_plan',
                    'result_id' => $planId,
                ];
            },
        );
    }

    public function grant(
        User $actor,
        string $learnerMembershipId,
        string $planId,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: [EntitlementAdministrationAuthorization::ENTITLEMENTS_GRANT],
            operation: 'entitlement.grant',
            targetType: 'learner_membership',
            targetId: $learnerMembershipId,
            body: [
                'plan_id' => $planId,
            ],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($learnerMembershipId, $planId): array {
                $this->lockedLearner($organizationId, $learnerMembershipId);
                $plan = DB::table('entitlement_plans')
                    ->where('organization_id', $organizationId)
                    ->where('id', $planId)
                    ->where('status', 'active')
                    ->first();

                if (! $plan instanceof stdClass || DirectPlanDefaults::isTalkPack($plan)) {
                    throw new EntitlementAdministrationRejected;
                }

                $starts = CarbonImmutable::now('Asia/Kolkata');
                $id = $this->access->grantPlan(
                    $organizationId,
                    $learnerMembershipId,
                    $plan,
                    'operator_grant',
                    $actorMembershipId,
                    [],
                    $starts,
                );

                return [
                    'result_type' => 'entitlement',
                    'result_id' => $id,
                ];
            },
        );
    }

    public function grantCourtesyPremium(
        User $actor,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        $organizationId = $this->organizationId($actor);

        if (! $this->access->applies($organizationId)) {
            throw new EntitlementAdministrationRejected;
        }

        return $this->execute(
            actor: $actor,
            permissions: [EntitlementAdministrationAuthorization::ENTITLEMENTS_GRANT],
            operation: 'entitlement.courtesy_premium.grant',
            targetType: 'organization',
            targetId: $organizationId,
            body: [],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ): array {
                $this->courtesy->grantForOrganization(
                    $organizationId,
                    $actorMembershipId,
                );

                return [
                    'result_type' => 'courtesy_premium_batch',
                    'result_id' => $organizationId,
                ];
            },
        );
    }

    public function restartDemoClock(
        User $actor,
        string $entitlementId,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->mutateEntitlement(
            $actor,
            $entitlementId,
            $idempotencyKey,
            'entitlement.demo_clock.restart',
            EntitlementAdministrationAuthorization::ENTITLEMENTS_ADJUST,
            function (string $organizationId, string $actorMembershipId, stdClass $row, CarbonImmutable $now) use ($entitlementId): array {
                $starts = CarbonImmutable::now('Asia/Kolkata');
                DB::table('learner_entitlements')->where('id', $entitlementId)->update([
                    'status' => 'active',
                    'first_sign_in_at' => $starts,
                    'expires_at' => $starts->addHours(DirectPlanDefaults::DEMO_DURATION_HOURS),
                    'updated_at' => $starts,
                ]);
                $this->access->event($organizationId, $entitlementId, 'demo_clock_restarted', $actorMembershipId, []);

                return [
                    'result_type' => 'entitlement',
                    'result_id' => $entitlementId,
                ];
            },
        );
    }

    public function restartDemoMeter(
        User $actor,
        string $entitlementId,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->mutateEntitlement(
            $actor,
            $entitlementId,
            $idempotencyKey,
            'entitlement.demo_meter.restart',
            EntitlementAdministrationAuthorization::ENTITLEMENTS_ADJUST,
            function (string $organizationId, string $actorMembershipId, stdClass $row, CarbonImmutable $now) use ($entitlementId): array {
                DB::table('live_ai_meter_charges')->where('entitlement_id', $entitlementId)->delete();
                DB::table('learner_entitlements')->where('id', $entitlementId)->update([
                    'live_ai_seconds_used' => 0,
                    'updated_at' => $now,
                ]);
                $this->access->event($organizationId, $entitlementId, 'demo_meter_restarted', $actorMembershipId, []);

                return [
                    'result_type' => 'entitlement',
                    'result_id' => $entitlementId,
                ];
            },
        );
    }

    public function revoke(
        User $actor,
        string $entitlementId,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->mutateEntitlement(
            $actor,
            $entitlementId,
            $idempotencyKey,
            'entitlement.revoke',
            EntitlementAdministrationAuthorization::ENTITLEMENTS_REVOKE,
            function (string $organizationId, string $actorMembershipId, stdClass $row, CarbonImmutable $now) use ($entitlementId): array {
                DB::table('learner_entitlements')->where('id', $entitlementId)->update([
                    'status' => 'revoked',
                    'updated_at' => $now,
                ]);
                $this->access->event($organizationId, $entitlementId, 'revoked', $actorMembershipId, []);

                return [
                    'result_type' => 'entitlement',
                    'result_id' => $entitlementId,
                ];
            },
        );
    }

    public function clearSignInThrottle(
        User $actor,
        string $learnerMembershipId,
        string $idempotencyKey,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: [EntitlementAdministrationAuthorization::ENTITLEMENTS_ADJUST],
            operation: 'entitlement.signin_throttle.clear',
            targetType: 'learner_membership',
            targetId: $learnerMembershipId,
            body: [],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($learnerMembershipId): array {
                $learner = $this->lockedLearner($organizationId, $learnerMembershipId);
                $username = DB::table('users')
                    ->where('id', $this->string($learner, 'user_id'))
                    ->value('username');

                if (is_string($username) && $username !== '') {
                    $account = strtolower(trim($username));
                    RateLimiter::clear(
                        $this->digester->digest('password-sign-in-account', $account),
                    );
                }

                return [
                    'result_type' => 'learner_membership',
                    'result_id' => $learnerMembershipId,
                ];
            },
        );
    }

    /**
     * @param  Closure(string, string, stdClass, CarbonImmutable): array{result_type: string, result_id: string}  $mutate
     */
    private function mutateEntitlement(
        User $actor,
        string $entitlementId,
        string $idempotencyKey,
        string $operation,
        string $permission,
        Closure $mutate,
    ): OrganizationAdministrationCommandResult {
        return $this->execute(
            actor: $actor,
            permissions: [$permission],
            operation: $operation,
            targetType: 'entitlement',
            targetId: $entitlementId,
            body: [],
            idempotencyKey: $idempotencyKey,
            command: function (
                string $organizationId,
                string $actorMembershipId,
                string $commandId,
                CarbonImmutable $now,
            ) use ($entitlementId, $mutate): array {
                $row = DB::table('learner_entitlements')
                    ->where('organization_id', $organizationId)
                    ->where('id', $entitlementId)
                    ->lockForUpdate()
                    ->first();

                if (! $row instanceof stdClass) {
                    throw new EntitlementAdministrationRejected;
                }

                return $mutate($organizationId, $actorMembershipId, $row, $now);
            },
        );
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $body
     * @param  Closure(string, string, string, CarbonImmutable): array{result_type: string, result_id: string}  $command
     */
    private function execute(
        User $actor,
        array $permissions,
        string $operation,
        string $targetType,
        string $targetId,
        array $body,
        string $idempotencyKey,
        Closure $command,
    ): OrganizationAdministrationCommandResult {
        try {
            return DB::transaction(function () use (
                $actor,
                $permissions,
                $operation,
                $targetType,
                $targetId,
                $body,
                $idempotencyKey,
                $command,
            ): OrganizationAdministrationCommandResult {
                $fingerprint = $this->idempotency->fingerprint(
                    $operation,
                    $targetType,
                    $targetId,
                    $body,
                );
                $claim = $this->idempotency->claim(
                    $actor,
                    $idempotencyKey,
                    $operation,
                    $targetType,
                    $targetId,
                    $fingerprint,
                );
                $context = $this->authorization->contextFor($actor);
                $organization = DB::table('organizations')
                    ->where('id', $context->organizationId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first(['id']);

                if (! $organization instanceof stdClass) {
                    throw new EntitlementAdministrationRejected;
                }

                $actorMembership = $this->authorization->actorMembership($actor, true);
                $this->authorization->permissionRows($actor, true);
                $this->authorization->assertAll($actor, $permissions);

                if ($claim->replayed) {
                    if (
                        $claim->resultType === null
                        || $claim->resultId === null
                        || $claim->completedAt === null
                    ) {
                        throw new EntitlementAdministrationConflict(
                            EntitlementAdministrationConflict::IDEMPOTENCY,
                        );
                    }

                    return new OrganizationAdministrationCommandResult(
                        resultType: $claim->resultType,
                        resultId: $claim->resultId,
                        replayed: true,
                        completedAt: $claim->completedAt,
                    );
                }

                $now = CarbonImmutable::now();
                $outcome = $command(
                    $context->organizationId,
                    $this->string($actorMembership, 'id'),
                    $claim->id,
                    $now,
                );
                $this->idempotency->complete(
                    $claim,
                    $outcome['result_type'],
                    $outcome['result_id'],
                    $now,
                );

                return new OrganizationAdministrationCommandResult(
                    resultType: $outcome['result_type'],
                    resultId: $outcome['result_id'],
                    replayed: false,
                    completedAt: $now,
                );
            }, 3);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23503', '23505', '23514', '55000'], true)) {
                throw new EntitlementAdministrationConflict(previous: $exception);
            }

            throw $exception;
        }
    }

    private function organizationId(User $actor): string
    {
        return $this->authorization->contextFor($actor)->organizationId;
    }

    private function lockedLearner(string $organizationId, string $membershipId): stdClass
    {
        $row = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->where('membership_type', Membership::TYPE_LEARNER)
            ->lockForUpdate()
            ->first();

        if (! $row instanceof stdClass) {
            throw new EntitlementAdministrationRejected;
        }

        return $row;
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) && $value !== '' ? $value : '';
    }
}
