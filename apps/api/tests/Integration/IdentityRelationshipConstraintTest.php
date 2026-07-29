<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Membership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

final class IdentityRelationshipConstraintTest extends TenancyIntegrationTestCase
{
    public function test_postgresql_rejects_invite_acceptance_membership_for_a_different_user(): void
    {
        $organization = $this->createOrganization('Invite Identity Tenant');
        $acceptingUser = $this->createUser();
        $otherUser = $this->createUser();
        $this->createMembership($organization, $acceptingUser);
        [$otherMembership] = $this->createMembership(
            $organization,
            $otherUser,
        );

        $this->expectPostgresConstraintViolation('23503');

        DB::table('institution_invites')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'code_digest' => hash('sha256', 'wrong-user-acceptance'),
            'membership_type' => Membership::TYPE_LEARNER,
            'role_id' => null,
            'cohort_id' => null,
            'status' => 'accepted',
            'expires_at' => now()->addDay(),
            'accepted_membership_id' => $otherMembership->getKey(),
            'accepted_by_user_id' => $acceptingUser->getKey(),
            'accepted_idempotency_key' => (string) Str::uuid(),
            'accepted_request_fingerprint' => hash('sha256', 'request'),
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_rejects_audit_actor_membership_for_a_different_user(): void
    {
        $organization = $this->createOrganization('Audit Identity Tenant');
        $actor = $this->createUser();
        $otherUser = $this->createUser();
        [$actorMembership] = $this->createMembership($organization, $actor);
        [$otherMembership] = $this->createMembership($organization, $otherUser);

        $this->expectPostgresConstraintViolation('23503');

        DB::table('audit_events')->insert([
            'id' => (string) Str::ulid(),
            'scope_kind' => 'organization',
            'organization_id' => $organization->getKey(),
            'actor_user_id' => $actor->getKey(),
            'actor_membership_id' => $otherMembership->getKey(),
            'target_organization_id' => null,
            'capability_key' => null,
            'reason_key' => null,
            'action' => 'membership.viewed',
            'subject_type' => 'membership',
            'subject_id' => $actorMembership->getKey(),
            'correlation_id' => null,
            'occurred_at' => now(),
        ]);
    }

    public function test_postgresql_rejects_mobile_selection_for_a_different_user(): void
    {
        $organization = $this->createOrganization('Mobile Identity Tenant');
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $this->createMembership($organization, $user);
        [$otherMembership] = $this->createMembership($organization, $otherUser);

        $this->expectPostgresConstraintViolation('23503');

        $this->insertMobileFamily(
            userId: (string) $user->getKey(),
            selectedOrganizationId: (string) $organization->getKey(),
            selectedMembershipId: (string) $otherMembership->getKey(),
        );
    }

    public function test_postgresql_rejects_mobile_selection_with_a_mismatched_organization(): void
    {
        $organizationA = $this->createOrganization('Mobile Selection Tenant A');
        $organizationB = $this->createOrganization('Mobile Selection Tenant B');
        $user = $this->createUser();
        [$membershipA] = $this->createMembership($organizationA, $user);

        $this->expectPostgresConstraintViolation('23503');

        $this->insertMobileFamily(
            userId: (string) $user->getKey(),
            selectedOrganizationId: (string) $organizationB->getKey(),
            selectedMembershipId: (string) $membershipA->getKey(),
        );
    }

    public function test_postgresql_requires_mobile_selection_organization_and_membership_as_a_pair(): void
    {
        $organization = $this->createOrganization('Mobile Selection Pair Tenant');
        $user = $this->createUser();

        $this->expectPostgresConstraintViolation('23514');

        $this->insertMobileFamily(
            userId: (string) $user->getKey(),
            selectedOrganizationId: (string) $organization->getKey(),
            selectedMembershipId: null,
        );
    }

    private function expectPostgresConstraintViolation(string $sqlState): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode($sqlState);
    }

    private function insertMobileFamily(
        string $userId,
        ?string $selectedOrganizationId,
        ?string $selectedMembershipId,
    ): void {
        $client = Client::query()->create([
            'name' => 'Identity relationship constraint client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => ['com.globalenglish.app:/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
        $now = now();

        DB::table('mobile_token_families')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $userId,
            'client_id' => $client->getKey(),
            'selected_organization_id' => $selectedOrganizationId,
            'selected_membership_id' => $selectedMembershipId,
            'installation_digest' => str_repeat('b', 64),
            'last_used_at' => $now,
            'absolute_expires_at' => $now->copy()->addDay(),
            'revoked_at' => null,
            'revocation_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
