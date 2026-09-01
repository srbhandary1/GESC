<?php

declare(strict_types=1);

namespace App\Entitlements;

use App\Entitlements\Exceptions\EntitlementDenied;
use App\Learning\LearnerAuthority;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class EntitlementAccess
{
    public function __construct(
        private LearnerAuthority $authority,
        private DirectCatalogSeeder $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $learner): array
    {
        $context = $this->authority->require($learner);

        return $this->snapshotForContext($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotForContext(OrganizationContext $context): array
    {
        if (! $this->applies($context->organizationId)) {
            return $this->unmeteredSnapshot();
        }

        $this->catalog->seed($context->organizationId, Date::now()->toIso8601String());
        $membershipId = (string) $context->membershipId;
        $entitlement = $this->resolve($context->organizationId, $membershipId);

        return $this->present($context->organizationId, $entitlement);
    }

    public function assertLevel(User $learner, int $levelNumber): void
    {
        $snapshot = $this->snapshot($learner);
        $max = $snapshot['max_level_number'];

        if (is_int($max) && $levelNumber > $max) {
            throw new EntitlementDenied(EntitlementDenied::LEVEL_LOCKED);
        }
    }

    public function assertRoleplay(User $learner): void
    {
        $snapshot = $this->snapshot($learner);

        if ($snapshot['allows_roleplay'] !== true) {
            throw new EntitlementDenied(EntitlementDenied::ROLEPLAY_LOCKED);
        }
    }

    public function consumeLiveAi(User $learner, string $chargeKey, int $seconds): void
    {
        $context = $this->authority->require($learner);

        if (! $this->applies($context->organizationId)) {
            return;
        }

        $seconds = max(1, $seconds);

        try {
            DB::transaction(function () use ($chargeKey, $seconds, $context): void {
                $entitlement = $this->resolve(
                    $context->organizationId,
                    (string) $context->membershipId,
                );

                if ($this->string($entitlement, 'status') !== 'active') {
                    throw new EntitlementDenied(EntitlementDenied::EXPIRED);
                }

                $snapshot = $this->decodedSnapshot($entitlement);
                $cap = isset($snapshot['live_ai_cap_seconds']) && is_numeric($snapshot['live_ai_cap_seconds'])
                    ? (int) $snapshot['live_ai_cap_seconds']
                    : null;

                if ($cap === null) {
                    if (! ($snapshot['is_demo'] ?? false)) {
                        return;
                    }

                    $cap = DirectPlanDefaults::DEMO_LIVE_AI_CAP_SECONDS;
                }

                $existing = DB::table('live_ai_meter_charges')
                    ->where('entitlement_id', $this->string($entitlement, 'id'))
                    ->where('charge_key', $chargeKey)
                    ->lockForUpdate()
                    ->first(['id']);

                if ($existing instanceof stdClass) {
                    return;
                }

                $used = (int) $entitlement->live_ai_seconds_used;

                if (($used + $seconds) > $cap) {
                    throw new EntitlementDenied(EntitlementDenied::DEMO_AI_EXHAUSTED);
                }

                $now = Date::now();
                DB::table('live_ai_meter_charges')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $context->organizationId,
                    'entitlement_id' => $this->string($entitlement, 'id'),
                    'charge_key' => $chargeKey,
                    'seconds' => $seconds,
                    'charged_at' => $now,
                ]);
                DB::table('learner_entitlements')
                    ->where('id', $this->string($entitlement, 'id'))
                    ->update([
                        'live_ai_seconds_used' => $used + $seconds,
                        'updated_at' => $now,
                    ]);
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                return;
            }

            throw $exception;
        }
    }

    public function applies(string $organizationId): bool
    {
        $kind = DB::table('organizations')
            ->where('id', $organizationId)
            ->value('kind');

        return $kind === Organization::KIND_PLATFORM_DIRECT;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function levelAccess(array $snapshot, int $levelNumber): string
    {
        $max = $snapshot['max_level_number'] ?? null;

        if (is_int($max) && $levelNumber > $max) {
            return 'upgrade_required';
        }

        return 'allowed';
    }

    private function resolve(string $organizationId, string $membershipId): stdClass
    {
        $row = DB::table('learner_entitlements')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->when(
                DB::transactionLevel() > 0,
                static fn ($query) => $query->lockForUpdate(),
            )
            ->first();

        if (! $row instanceof stdClass) {
            return $this->grantAutoDemo($organizationId, $membershipId);
        }

        if ($this->string($row, 'status') === 'active') {
            $this->touchDemoClock($row);
            $this->expireIfNeeded($row);
            $fresh = DB::table('learner_entitlements')
                ->where('id', $this->string($row, 'id'))
                ->first();

            if ($fresh instanceof stdClass) {
                return $fresh;
            }
        }

        return $row;
    }

    private function grantAutoDemo(string $organizationId, string $membershipId): stdClass
    {
        $plan = DB::table('entitlement_plans')
            ->where('organization_id', $organizationId)
            ->where('code', DirectPlanDefaults::DEMO)
            ->where('status', 'active')
            ->first();

        if (! $plan instanceof stdClass) {
            throw new EntitlementDenied(EntitlementDenied::EXPIRED);
        }

        $now = CarbonImmutable::now('Asia/Kolkata');
        $id = (string) Str::ulid();
        $snapshot = $this->planSnapshot($plan);
        $expires = $now->addHours((int) $plan->duration_value);
        DB::table('learner_entitlements')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'learner_membership_id' => $membershipId,
            'plan_id' => $this->string($plan, 'id'),
            'plan_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'status' => 'active',
            'source' => 'auto_demo',
            'starts_at' => $now,
            'first_sign_in_at' => $now,
            'expires_at' => $expires,
            'live_ai_seconds_used' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->event($organizationId, $id, 'granted', null, [
            'source' => 'auto_demo',
            'plan_code' => DirectPlanDefaults::DEMO,
        ]);
        $row = DB::table('learner_entitlements')->where('id', $id)->first();

        if (! $row instanceof stdClass) {
            throw new EntitlementDenied(EntitlementDenied::EXPIRED);
        }

        return $row;
    }

    private function touchDemoClock(stdClass $row): void
    {
        $snapshot = $this->decodedSnapshot($row);

        if (($snapshot['is_demo'] ?? false) !== true) {
            return;
        }

        if ($this->nullableString($row, 'first_sign_in_at') !== null) {
            return;
        }

        $now = CarbonImmutable::now('Asia/Kolkata');
        $hours = (int) ($snapshot['duration_value'] ?? DirectPlanDefaults::DEMO_DURATION_HOURS);
        DB::table('learner_entitlements')
            ->where('id', $this->string($row, 'id'))
            ->whereNull('first_sign_in_at')
            ->update([
                'first_sign_in_at' => $now,
                'expires_at' => $now->addHours($hours),
                'updated_at' => $now,
            ]);
    }

    private function expireIfNeeded(stdClass $row): void
    {
        $expiresAt = $this->nullableString($row, 'expires_at');

        if ($expiresAt === null) {
            return;
        }

        if (CarbonImmutable::parse($expiresAt)->greaterThan(Date::now())) {
            return;
        }

        if ($this->string($row, 'status') !== 'active') {
            return;
        }

        $now = Date::now();
        DB::table('learner_entitlements')
            ->where('id', $this->string($row, 'id'))
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
                'updated_at' => $now,
            ]);
        $this->event(
            $this->string($row, 'organization_id'),
            $this->string($row, 'id'),
            'expired',
            null,
            [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(string $organizationId, stdClass $row): array
    {
        $snapshot = $this->decodedSnapshot($row);
        $cap = $this->nullableInt($row, 'live_ai_cap_seconds')
            ?? (isset($snapshot['live_ai_cap_seconds']) && is_int($snapshot['live_ai_cap_seconds'])
                ? $snapshot['live_ai_cap_seconds']
                : null);
        $used = (int) $row->live_ai_seconds_used;
        $active = $this->string($row, 'status') === 'active';
        $tax = DB::table('organization_tax_settings')
            ->where('organization_id', $organizationId)
            ->first();
        $allowsRoleplay = $active && (bool) ($snapshot['allows_roleplay'] ?? false);
        $maxLevel = isset($snapshot['max_level_number']) && is_int($snapshot['max_level_number'])
            ? $snapshot['max_level_number']
            : null;

        if (! $active) {
            $maxLevel = DirectPlanDefaults::DEMO_MAX_LEVEL;
        }

        return [
            'applies' => true,
            'entitlement_id' => $this->string($row, 'id'),
            'plan_code' => is_string($snapshot['code'] ?? null) ? $snapshot['code'] : '',
            'plan_name' => is_string($snapshot['name'] ?? null) ? $snapshot['name'] : '',
            'status' => $this->string($row, 'status'),
            'source' => $this->string($row, 'source'),
            'allows_roleplay' => $allowsRoleplay,
            'max_level_number' => $maxLevel,
            'is_demo' => (bool) ($snapshot['is_demo'] ?? false),
            'starts_at' => CarbonImmutable::parse($this->string($row, 'starts_at'))->utc()->toIso8601String(),
            'first_sign_in_at' => $this->nullableString($row, 'first_sign_in_at') === null
                ? null
                : CarbonImmutable::parse((string) $row->first_sign_in_at)->utc()->toIso8601String(),
            'expires_at' => $this->nullableString($row, 'expires_at') === null
                ? null
                : CarbonImmutable::parse((string) $row->expires_at)->utc()->toIso8601String(),
            'live_ai_seconds_used' => $used,
            'live_ai_cap_seconds' => $cap,
            'live_ai_seconds_remaining' => ! $active
                ? 0
                : ($cap === null ? null : max(0, $cap - $used)),
            'gstin_is_placeholder' => $tax instanceof stdClass && (bool) $tax->gstin_is_placeholder,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unmeteredSnapshot(): array
    {
        return [
            'applies' => false,
            'entitlement_id' => null,
            'plan_code' => null,
            'plan_name' => null,
            'status' => 'not_applicable',
            'source' => null,
            'allows_roleplay' => true,
            'max_level_number' => null,
            'is_demo' => false,
            'starts_at' => null,
            'first_sign_in_at' => null,
            'expires_at' => null,
            'live_ai_seconds_used' => 0,
            'live_ai_cap_seconds' => null,
            'live_ai_seconds_remaining' => null,
            'gstin_is_placeholder' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function grantPlan(
        string $organizationId,
        string $learnerMembershipId,
        stdClass $plan,
        string $source,
        ?string $actorMembershipId,
        array $payload,
        CarbonImmutable $starts,
    ): string {
        $expires = $this->string($plan, 'billing_period') === 'calendar_month'
            ? $starts->addMonths((int) $plan->duration_value)
            : $starts->addHours((int) $plan->duration_value);
        $id = (string) Str::ulid();
        $this->expireActive($organizationId, $learnerMembershipId);
        DB::table('learner_entitlements')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'learner_membership_id' => $learnerMembershipId,
            'plan_id' => $this->string($plan, 'id'),
            'plan_snapshot' => json_encode($this->planSnapshot($plan), JSON_THROW_ON_ERROR),
            'status' => 'active',
            'source' => $source,
            'starts_at' => $starts,
            'first_sign_in_at' => (bool) $plan->is_demo ? null : $starts,
            'expires_at' => (bool) $plan->is_demo ? null : $expires,
            'live_ai_seconds_used' => 0,
            'created_at' => $starts,
            'updated_at' => $starts,
        ]);
        $this->event($organizationId, $id, 'granted', $actorMembershipId, [
            'source' => $source,
            'plan_code' => $this->string($plan, 'code'),
            ...$payload,
        ]);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function planSnapshot(stdClass $plan): array
    {
        return [
            'code' => $this->string($plan, 'code'),
            'name' => $this->string($plan, 'name'),
            'amount_ex_gst_paise' => (int) $plan->amount_ex_gst_paise,
            'billing_period' => $this->string($plan, 'billing_period'),
            'duration_value' => (int) $plan->duration_value,
            'allows_roleplay' => (bool) $plan->allows_roleplay,
            'max_level_number' => $this->nullableInt($plan, 'max_level_number'),
            'live_ai_cap_seconds' => $this->nullableInt($plan, 'live_ai_cap_seconds'),
            'is_demo' => (bool) $plan->is_demo,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function event(
        string $organizationId,
        string $entitlementId,
        string $eventType,
        ?string $actorMembershipId,
        array $payload,
    ): void {
        DB::table('entitlement_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'entitlement_id' => $entitlementId,
            'event_type' => $eventType,
            'actor_membership_id' => $actorMembershipId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => Date::now(),
        ]);
    }

    public function expireActive(
        string $organizationId,
        string $membershipId,
        ?string $exceptId = null,
    ): void {
        $query = DB::table('learner_entitlements')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'active');

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $now = Date::now();
        $query->update([
            'status' => 'expired',
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedSnapshot(stdClass $row): array
    {
        $raw = $row->plan_snapshot ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
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
