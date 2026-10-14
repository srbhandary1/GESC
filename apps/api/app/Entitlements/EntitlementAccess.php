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

    public function assertFreeSpeech(User $learner): void
    {
        $snapshot = $this->snapshot($learner);

        if ($snapshot['allows_free_speech'] !== true) {
            throw new EntitlementDenied(EntitlementDenied::FREE_SPEECH_LOCKED);
        }
    }

    public function assertSessionPart(User $learner, string $partId): void
    {
        $snapshot = $this->snapshot($learner);
        $allowed = $snapshot['allowed_session_parts'] ?? [];

        if (! is_array($allowed) || ! in_array($partId, $allowed, true)) {
            throw new EntitlementDenied(EntitlementDenied::SESSION_LOCKED);
        }
    }

    public function assertLiveAiAvailable(User $learner): void
    {
        $snapshot = $this->snapshot($learner);
        if (($snapshot['applies'] ?? false) === true && (($snapshot['status'] ?? '') !== 'active'
            || ($snapshot['live_ai_seconds_remaining'] !== null && $snapshot['live_ai_seconds_remaining'] <= 0))) {
            throw new EntitlementDenied(($snapshot['is_demo'] ?? false) === true
                ? EntitlementDenied::DEMO_AI_EXHAUSTED : EntitlementDenied::AI_EXHAUSTED);
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

                if (
                    ! $entitlement instanceof stdClass
                    || $this->string($entitlement, 'status') !== 'active'
                ) {
                    throw new EntitlementDenied(EntitlementDenied::EXPIRED);
                }

                $snapshot = $this->decodedSnapshot($entitlement);
                $cap = $this->effectiveLiveAiCap($entitlement, $snapshot);

                if ($cap === null) {
                    return;
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
                    throw new EntitlementDenied(
                        ($snapshot['is_demo'] ?? false) === true
                            ? EntitlementDenied::DEMO_AI_EXHAUSTED
                            : EntitlementDenied::AI_EXHAUSTED,
                    );
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

    private function resolve(string $organizationId, string $membershipId): ?stdClass
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
            return null;
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
    private function present(string $organizationId, ?stdClass $row): array
    {
        if (! $row instanceof stdClass) {
            return $this->absentDirectSnapshot($organizationId);
        }

        $snapshot = $this->decodedSnapshot($row);
        $cap = $this->effectiveLiveAiCap($row, $snapshot);
        $used = (int) $row->live_ai_seconds_used;
        $active = $this->string($row, 'status') === 'active';
        $tax = DB::table('organization_tax_settings')
            ->where('organization_id', $organizationId)
            ->first();
        $allowsRoleplay = $active && (bool) ($snapshot['allows_roleplay'] ?? false);
        $allowsFreeSpeech = $active && (
            array_key_exists('allows_free_speech', $snapshot)
                ? (bool) $snapshot['allows_free_speech']
                : ! (($snapshot['is_demo'] ?? false) === true)
        );
        $allowedSessionParts = $active
            ? $this->allowedSessionParts($snapshot)
            : [];
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
            'allows_free_speech' => $allowsFreeSpeech,
            'allowed_session_parts' => $allowedSessionParts,
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
    private function absentDirectSnapshot(string $organizationId): array
    {
        $tax = DB::table('organization_tax_settings')
            ->where('organization_id', $organizationId)
            ->first();

        return [
            'applies' => true,
            'entitlement_id' => null,
            'plan_code' => null,
            'plan_name' => null,
            'status' => 'expired',
            'source' => null,
            'allows_roleplay' => false,
            'allows_free_speech' => false,
            'allowed_session_parts' => [],
            'max_level_number' => DirectPlanDefaults::DEMO_MAX_LEVEL,
            'is_demo' => false,
            'starts_at' => null,
            'first_sign_in_at' => null,
            'expires_at' => null,
            'live_ai_seconds_used' => 0,
            'live_ai_cap_seconds' => null,
            'live_ai_seconds_remaining' => 0,
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
            'allows_free_speech' => true,
            'allowed_session_parts' => DirectPlanDefaults::PAID_SESSION_PARTS,
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

        if (DirectPlanDefaults::isTalkPack($plan)) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }
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
            'first_sign_in_at' => $starts,
            'expires_at' => $expires,
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
     * @return array{expires_at: CarbonImmutable, seconds: int}
     */
    public function addTalkPack(
        string $organizationId,
        stdClass $entitlement,
        stdClass $plan,
        string $checkoutId,
        CarbonImmutable $now,
    ): array {
        $snapshot = $this->decodedSnapshot($entitlement);

        if (
            $this->string($entitlement, 'status') !== 'active'
            || ($snapshot['is_demo'] ?? false) === true
            || ($snapshot['billing_period'] ?? '') !== 'calendar_month'
        ) {
            throw new EntitlementDenied(EntitlementDenied::TALK_PACK_REQUIRES_PAID);
        }

        $seconds = $this->nullableInt($plan, 'live_ai_cap_seconds') ?? 0;

        if ($seconds < 1) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        $membershipEndRaw = $this->nullableString($entitlement, 'expires_at');

        if ($membershipEndRaw === null) {
            throw new EntitlementDenied(EntitlementDenied::TALK_PACK_REQUIRES_PAID);
        }

        $membershipEnd = CarbonImmutable::parse($membershipEndRaw);
        $packHorizon = $now->addDays(DirectPlanDefaults::TALK_PACK_MAX_DAYS);
        $expires = $packHorizon->lessThan($membershipEnd) ? $packHorizon : $membershipEnd;

        if ($expires->lessThanOrEqualTo($now)) {
            throw new EntitlementDenied(EntitlementDenied::EXPIRED);
        }

        DB::table('live_ai_talk_packs')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'entitlement_id' => $this->string($entitlement, 'id'),
            'checkout_id' => $checkoutId,
            'plan_code' => $this->string($plan, 'code'),
            'seconds' => $seconds,
            'expires_at' => $expires,
            'created_at' => $now,
        ]);
        $this->event($organizationId, $this->string($entitlement, 'id'), 'talk_pack_granted', null, [
            'source' => 'checkout',
            'plan_code' => $this->string($plan, 'code'),
            'checkout_id' => $checkoutId,
            'seconds' => $seconds,
            'expires_at' => $expires->utc()->toIso8601String(),
        ]);

        return [
            'expires_at' => $expires,
            'seconds' => $seconds,
        ];
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
            'allows_free_speech' => DirectPlanDefaults::allowsFreeSpeechFor($plan),
            'allowed_session_parts' => DirectPlanDefaults::allowedSessionPartsFor($plan),
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

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function effectiveLiveAiCap(stdClass $entitlement, array $snapshot): ?int
    {
        $planCap = $this->membershipCap($snapshot);

        if ($planCap === null) {
            return null;
        }

        return $planCap + $this->activeTalkPackSeconds($this->string($entitlement, 'id'));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    private function allowedSessionParts(array $snapshot): array
    {
        $parts = $snapshot['allowed_session_parts'] ?? null;

        if (is_array($parts)) {
            return array_values(array_filter(
                $parts,
                static fn ($part): bool => is_string($part) && $part !== '',
            ));
        }

        if (($snapshot['is_demo'] ?? false) === true) {
            return DirectPlanDefaults::DEMO_SESSION_PARTS;
        }

        return DirectPlanDefaults::PAID_SESSION_PARTS;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function membershipCap(array $snapshot): ?int
    {
        if (isset($snapshot['live_ai_cap_seconds']) && is_numeric($snapshot['live_ai_cap_seconds'])) {
            return (int) $snapshot['live_ai_cap_seconds'];
        }

        $code = is_string($snapshot['code'] ?? null) ? $snapshot['code'] : '';

        return DirectPlanDefaults::membershipLiveAiCap($code);
    }

    private function activeTalkPackSeconds(string $entitlementId): int
    {
        return (int) DB::table('live_ai_talk_packs')
            ->where('entitlement_id', $entitlementId)
            ->where('expires_at', '>', Date::now())
            ->sum('seconds');
    }
}
