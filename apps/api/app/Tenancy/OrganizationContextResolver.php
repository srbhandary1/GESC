<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\User;
use App\Tenancy\Exceptions\AmbiguousOrganizationContext;
use App\Tenancy\Exceptions\InvalidOrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class OrganizationContextResolver
{
    public function forSelectedMembership(
        User $user,
        string $membershipId,
        ?string $correlationId = null,
    ): OrganizationContext {
        if (! Str::isUlid($membershipId) || $user->status !== User::STATUS_ACTIVE) {
            throw new InvalidOrganizationContext;
        }

        $membership = DB::table('memberships')
            ->join(
                'organizations',
                'organizations.id',
                '=',
                'memberships.organization_id',
            )
            ->where('memberships.id', $membershipId)
            ->where('memberships.user_id', $user->getKey())
            ->where('memberships.status', 'active')
            ->where('organizations.status', 'active')
            ->first([
                'memberships.id',
                'memberships.organization_id',
            ]);

        if (! $membership instanceof stdClass) {
            throw new InvalidOrganizationContext;
        }

        return $this->fromMembershipRow($user, $membership, $correlationId);
    }

    public function singleActiveMembership(
        User $user,
        ?string $correlationId = null,
    ): OrganizationContext {
        if ($user->status !== User::STATUS_ACTIVE) {
            throw new InvalidOrganizationContext;
        }

        /** @var list<stdClass> $memberships */
        $memberships = DB::table('memberships')
            ->join(
                'organizations',
                'organizations.id',
                '=',
                'memberships.organization_id',
            )
            ->where('memberships.user_id', $user->getKey())
            ->where('memberships.status', 'active')
            ->where('organizations.status', 'active')
            ->orderBy('memberships.id')
            ->limit(2)
            ->get([
                'memberships.id',
                'memberships.organization_id',
            ])
            ->all();

        if (count($memberships) > 1) {
            throw new AmbiguousOrganizationContext;
        }

        if ($memberships === []) {
            throw new InvalidOrganizationContext;
        }

        return $this->fromMembershipRow($user, $memberships[0], $correlationId);
    }

    private function fromMembershipRow(
        User $user,
        stdClass $membership,
        ?string $correlationId,
    ): OrganizationContext {
        if (
            ! is_string($membership->id)
            || ! is_string($membership->organization_id)
        ) {
            throw new InvalidOrganizationContext;
        }

        return new OrganizationContext(
            organizationId: $membership->organization_id,
            actorUserId: (string) $user->getKey(),
            membershipId: $membership->id,
            source: OrganizationContextSource::Membership,
            correlationId: $correlationId,
        );
    }
}
