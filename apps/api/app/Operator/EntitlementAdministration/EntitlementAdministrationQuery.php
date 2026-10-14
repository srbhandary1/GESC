<?php

declare(strict_types=1);

namespace App\Operator\EntitlementAdministration;

use App\Conversation\Metering\LearnerProviderUsageQuery;
use App\Entitlements\CourtesyPremiumGrantService;
use App\Entitlements\DirectCatalogSeeder;
use App\Entitlements\GstCalculator;
use App\Models\Membership;
use App\Models\User;
use App\Operator\EntitlementAdministration\Exceptions\EntitlementAdministrationRejected;
use App\Operator\OrganizationAdministration\OrganizationAdministrationCommandResult;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class EntitlementAdministrationQuery
{
    public function __construct(
        private EntitlementAdministrationAuthorization $authorization,
        private DirectCatalogSeeder $catalog,
        private CourtesyPremiumGrantService $courtesy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function workspace(User $actor): array
    {
        $this->authorization->assert($actor, EntitlementAdministrationAuthorization::PLANS_READ);
        $organizationId = $this->authorization->contextFor($actor)->organizationId;
        $this->catalog->seed($organizationId, Date::now()->toIso8601String());

        return [
            'tax' => $this->taxRow($organizationId),
            'plans' => $this->planRows($organizationId),
            'courtesy_premium' => [
                'granted_memberships' => $this->courtesy->grantedMembershipCount($organizationId),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function learnerEntitlement(User $actor, string $learnerMembershipId): array
    {
        $this->authorization->assert($actor, EntitlementAdministrationAuthorization::ENTITLEMENTS_READ);
        $organizationId = $this->authorization->contextFor($actor)->organizationId;
        $resolvedMembershipId = $this->resolveLearnerMembershipId($organizationId, $learnerMembershipId);
        $rows = DB::table('learner_entitlements')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $resolvedMembershipId)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
        $items = [];
        $entitlementIds = [];

        foreach ($rows as $row) {
            $items[] = $this->entitlement($row);
            $entitlementIds[] = $this->string($row, 'id');
        }

        $events = [];
        if ($entitlementIds !== []) {
            $eventRows = DB::table('entitlement_events')
                ->where('organization_id', $organizationId)
                ->whereIn('entitlement_id', $entitlementIds)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            foreach ($eventRows as $eventRow) {
                $payload = json_decode((string) ($eventRow->payload ?? '{}'), true);
                $payload = is_array($payload) ? $payload : [];
                $events[] = [
                    'id' => $this->string($eventRow, 'id'),
                    'entitlement_id' => $this->string($eventRow, 'entitlement_id'),
                    'event_type' => $this->string($eventRow, 'event_type'),
                    'actor_membership_id' => $this->nullableString($eventRow, 'actor_membership_id'),
                    'occurred_at' => $this->string($eventRow, 'occurred_at'),
                    'plan_code' => is_string($payload['plan_code'] ?? null) ? $payload['plan_code'] : null,
                    'source' => is_string($payload['source'] ?? null) ? $payload['source'] : null,
                    'reason' => is_string($payload['reason'] ?? null) ? $payload['reason'] : null,
                    'payment_reference' => is_string($payload['payment_reference'] ?? null) ? $payload['payment_reference'] : null,
                ];
            }
        }

        return [
            'learner_membership_id' => $resolvedMembershipId,
            'provider_usage' => app(LearnerProviderUsageQuery::class)->read($organizationId, $resolvedMembershipId),
            'entitlements' => $items,
            'events' => $events,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function commandResult(User $actor, OrganizationAdministrationCommandResult $result): array
    {
        $organizationId = $this->authorization->contextFor($actor)->organizationId;

        return [
            'result_type' => $result->resultType,
            'result_id' => $result->resultId,
            'completed_at' => $result->completedAt->utc()->toIso8601String(),
            'workspace' => match ($result->resultType) {
                'entitlement' => $this->learnerEntitlement(
                    $actor,
                    $this->membershipForEntitlement($organizationId, $result->resultId),
                ),
                'learner_membership' => $this->learnerEntitlement($actor, $result->resultId),
                default => $this->workspace($actor),
            },
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function planRows(string $organizationId): array
    {
        $tax = $this->tax($organizationId);
        $plans = [];

        foreach (DB::table('entitlement_plans')->where('organization_id', $organizationId)->orderBy('code')->get() as $plan) {
            $payable = GstCalculator::payable(
                (int) $plan->amount_ex_gst_paise,
                (int) $tax->gst_rate_bps,
                (bool) $tax->gst_registered,
            );
            $plans[] = [
                'id' => $this->string($plan, 'id'),
                'code' => $this->string($plan, 'code'),
                'name' => $this->string($plan, 'name'),
                'amount_ex_gst_paise' => (int) $plan->amount_ex_gst_paise,
                'gst_paise' => $payable['gst_paise'],
                'total_paise' => $payable['total_paise'],
                'billing_period' => $this->string($plan, 'billing_period'),
                'duration_value' => (int) $plan->duration_value,
                'allows_roleplay' => (bool) $plan->allows_roleplay,
                'max_level_number' => $this->nullableInt($plan, 'max_level_number'),
                'live_ai_cap_seconds' => $this->nullableInt($plan, 'live_ai_cap_seconds'),
                'is_demo' => (bool) $plan->is_demo,
                'status' => $this->string($plan, 'status'),
                'version' => (int) $plan->version,
            ];
        }

        return $plans;
    }

    /**
     * @return array<string, mixed>
     */
    private function taxRow(string $organizationId): array
    {
        $tax = $this->tax($organizationId);

        return [
            'gst_registered' => (bool) $tax->gst_registered,
            'gstin' => $this->string($tax, 'gstin'),
            'gst_rate_bps' => (int) $tax->gst_rate_bps,
            'gstin_is_placeholder' => (bool) $tax->gstin_is_placeholder,
            'version' => (int) $tax->version,
        ];
    }

    private function tax(string $organizationId): stdClass
    {
        $tax = DB::table('organization_tax_settings')
            ->where('organization_id', $organizationId)
            ->first();

        if (! $tax instanceof stdClass) {
            throw new EntitlementAdministrationRejected;
        }

        return $tax;
    }

    /**
     * @return array<string, mixed>
     */
    private function entitlement(stdClass $row): array
    {
        $snapshot = json_decode((string) $row->plan_snapshot, true);
        $snapshot = is_array($snapshot) ? $snapshot : [];

        return [
            'id' => $this->string($row, 'id'),
            'plan_code' => is_string($snapshot['code'] ?? null) ? $snapshot['code'] : '',
            'status' => $this->string($row, 'status'),
            'source' => $this->string($row, 'source'),
            'starts_at' => $this->string($row, 'starts_at'),
            'first_sign_in_at' => $this->nullableString($row, 'first_sign_in_at'),
            'expires_at' => $this->nullableString($row, 'expires_at'),
            'live_ai_seconds_used' => (int) $row->live_ai_seconds_used,
            'version' => 1,
        ];
    }

    private function resolveLearnerMembershipId(string $organizationId, string $identifier): string
    {
        $trimmed = trim($identifier);
        if ($trimmed === '') {
            throw new EntitlementAdministrationRejected;
        }

        if (preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/i', $trimmed) === 1) {
            $byId = DB::table('memberships')
                ->where('organization_id', $organizationId)
                ->where('id', $trimmed)
                ->where('membership_type', Membership::TYPE_LEARNER)
                ->first(['id']);
            if ($byId instanceof stdClass) {
                return $this->string($byId, 'id');
            }
        }

        $normalized = strtolower($trimmed);
        $byUsername = DB::table('memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.organization_id', $organizationId)
            ->where('memberships.membership_type', Membership::TYPE_LEARNER)
            ->whereRaw('LOWER(users.username) = ?', [$normalized])
            ->orderByDesc('memberships.joined_at')
            ->first(['memberships.id']);

        if (! $byUsername instanceof stdClass) {
            throw new EntitlementAdministrationRejected;
        }

        return $this->string($byUsername, 'id');
    }

    private function membershipForEntitlement(string $organizationId, string $entitlementId): string
    {
        $membershipId = DB::table('learner_entitlements')
            ->where('organization_id', $organizationId)
            ->where('id', $entitlementId)
            ->value('learner_membership_id');

        if (! is_string($membershipId) || $membershipId === '') {
            throw new EntitlementAdministrationRejected;
        }

        return $membershipId;
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) && $value !== '' ? $value : '';
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableInt(stdClass $row, string $field): ?int
    {
        $value = $row->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
