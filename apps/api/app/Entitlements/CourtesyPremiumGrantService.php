<?php

declare(strict_types=1);

namespace App\Entitlements;

use App\Models\Membership;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

final readonly class CourtesyPremiumGrantService
{
    public const string REASON = 'courtesy_premium_month';

    public function __construct(
        private DirectCatalogSeeder $catalog,
        private EntitlementAccess $access,
    ) {}

    /**
     * @param  list<string>|null  $usernames
     */
    public function grantForOrganization(
        string $organizationId,
        ?string $actorMembershipId,
        bool $dryRun = false,
        ?array $usernames = null,
    ): CourtesyPremiumGrantSummary {
        if (! $this->access->applies($organizationId)) {
            throw new RuntimeException('Courtesy Premium grants apply only to Global English Direct.');
        }

        $now = Date::now()->toIso8601String();
        $this->catalog->seed($organizationId, $now);
        $plan = DB::table('entitlement_plans')
            ->where('organization_id', $organizationId)
            ->where('code', DirectPlanDefaults::MONTHLY_ROLEPLAY)
            ->where('status', 'active')
            ->first();

        if (! $plan instanceof stdClass) {
            throw new RuntimeException('The Premium plan is not available.');
        }

        $wanted = $this->normalizedUsernameSet($usernames);
        $courtesyIds = $this->courtesyMembershipIds($organizationId);
        $memberships = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('membership_type', Membership::TYPE_LEARNER)
            ->where('status', Membership::STATUS_ACTIVE)
            ->orderBy('id')
            ->get(['id', 'user_id']);
        $userIds = [];

        foreach ($memberships as $membership) {
            $userIds[] = $this->string($membership, 'user_id');
        }

        $usernamesByUser = [];

        if ($userIds !== []) {
            foreach (DB::table('users')->whereIn('id', $userIds)->get(['id', 'username']) as $user) {
                $usernamesByUser[$this->string($user, 'id')] = $this->string($user, 'username');
            }
        }

        $starts = CarbonImmutable::now('Asia/Kolkata');
        $coveredUntil = $starts->addMonth()->subHour();
        $granted = 0;
        $skipped = 0;
        $matched = [];

        foreach ($memberships as $membership) {
            $membershipId = $this->string($membership, 'id');
            $username = $usernamesByUser[$this->string($membership, 'user_id')] ?? '';

            if ($wanted !== null) {
                if ($username === '' || ! isset($wanted[$username])) {
                    continue;
                }

                $matched[$username] = true;
            }

            if (isset($courtesyIds[$membershipId]) || $this->alreadyCovered($organizationId, $membershipId, $coveredUntil)) {
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                $this->access->grantPlan(
                    $organizationId,
                    $membershipId,
                    $plan,
                    'operator_grant',
                    $actorMembershipId,
                    ['reason' => self::REASON],
                    $starts,
                );
            }

            $granted++;
        }

        $unmatched = 0;

        if ($wanted !== null) {
            $unmatched = count($wanted) - count($matched);
        }

        return new CourtesyPremiumGrantSummary(
            considered: $wanted === null ? $memberships->count() : count($matched),
            granted: $granted,
            skipped: $skipped,
            unmatchedUsernames: max(0, $unmatched),
        );
    }

    public function grantedMembershipCount(string $organizationId): int
    {
        return count($this->courtesyMembershipIds($organizationId));
    }

    /**
     * @param  list<string>|null  $usernames
     * @return array<string, true>|null
     */
    private function normalizedUsernameSet(?array $usernames): ?array
    {
        if ($usernames === null) {
            return null;
        }

        $set = [];

        foreach ($usernames as $username) {
            $canonical = strtolower(trim($username));

            if ($canonical !== '') {
                $set[$canonical] = true;
            }
        }

        return $set;
    }

    /**
     * @return array<string, true>
     */
    private function courtesyMembershipIds(string $organizationId): array
    {
        $ids = [];
        $rows = DB::table('entitlement_events as events')
            ->join('learner_entitlements as entitlements', 'entitlements.id', '=', 'events.entitlement_id')
            ->where('events.organization_id', $organizationId)
            ->where('events.event_type', 'granted')
            ->get(['entitlements.learner_membership_id', 'events.payload']);

        foreach ($rows as $row) {
            $raw = $row->payload ?? null;
            $payload = is_array($raw)
                ? $raw
                : json_decode(is_string($raw) ? $raw : '', true);

            if (is_array($payload) && ($payload['reason'] ?? null) === self::REASON) {
                $ids[$this->string($row, 'learner_membership_id')] = true;
            }
        }

        return $ids;
    }

    private function alreadyCovered(
        string $organizationId,
        string $membershipId,
        CarbonImmutable $coveredUntil,
    ): bool {
        $active = DB::table('learner_entitlements')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'active')
            ->first();

        if (! $active instanceof stdClass) {
            return false;
        }

        $raw = $active->plan_snapshot ?? null;
        $snapshot = is_array($raw)
            ? $raw
            : json_decode(is_string($raw) ? $raw : '', true);
        $code = is_array($snapshot) && is_string($snapshot['code'] ?? null)
            ? $snapshot['code']
            : '';

        if ($code !== DirectPlanDefaults::MONTHLY_ROLEPLAY) {
            return false;
        }

        $expires = $active->expires_at ?? null;

        if (! is_string($expires) || $expires === '') {
            return true;
        }

        return CarbonImmutable::parse($expires, 'Asia/Kolkata')->greaterThanOrEqualTo($coveredUntil);
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) && $value !== '' ? $value : '';
    }

    public function directOrganizationId(): ?string
    {
        $organizationId = DB::table('organizations')
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->where('kind', Organization::KIND_PLATFORM_DIRECT)
            ->value('id');

        return is_string($organizationId) && $organizationId !== '' ? $organizationId : null;
    }
}
