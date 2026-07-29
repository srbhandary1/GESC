<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\InstitutionInvite;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\Exceptions\FoundationConfigurationException;
use App\Tenancy\Exceptions\InstitutionInviteUnavailable;
use App\Tenancy\InstitutionInviteAcceptor;
use App\Tenancy\InstitutionInviteCodeDigester;
use Illuminate\Support\Facades\DB;

final class InstitutionInviteAcceptorTest extends TenancyIntegrationTestCase
{
    public function test_invite_acceptance_creates_scoped_membership_role_and_enrollment_once(): void
    {
        $rawCode = 'GEI-7F4G-9R2K-P6XM';
        $organization = $this->createOrganization('Sakleshpur Partner College');
        $issuer = $this->createUser();
        [, $issuerContext] = $this->createMembership(
            $organization,
            $issuer,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $role = $this->createRole($issuerContext, 'institution-learner');
        $cohort = $this->createCohort($issuerContext);
        $invite = $this->contexts()->run(
            $issuerContext,
            fn (): InstitutionInvite => InstitutionInvite::query()->forceCreate([
                'code_digest' => $this->app
                    ->make(InstitutionInviteCodeDigester::class)
                    ->digest($rawCode),
                'membership_type' => Membership::TYPE_LEARNER,
                'role_id' => $role->getKey(),
                'cohort_id' => $cohort->getKey(),
                'status' => InstitutionInvite::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]),
        );
        $learner = $this->createUser();
        $acceptor = $this->app->make(InstitutionInviteAcceptor::class);

        $first = $acceptor->accept($rawCode, $learner);
        $second = $acceptor->accept($rawCode, $learner);

        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertSame($organization->getKey(), $first->organization->getKey());
        $this->assertSame($first->membership->getKey(), $second->membership->getKey());
        $this->assertNotNull($first->enrollment);
        $this->assertSame($first->enrollment->getKey(), $second->enrollment?->getKey());
        $this->assertSame(1, DB::table('memberships')
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $learner->getKey())
            ->count());
        $this->assertSame(1, DB::table('membership_roles')
            ->where('membership_id', $first->membership->getKey())
            ->count());
        $this->assertSame(1, DB::table('enrollments')
            ->where('learner_membership_id', $first->membership->getKey())
            ->count());
        $this->assertSame(1, DB::table('audit_events')
            ->where('subject_id', $invite->getKey())
            ->count());
        $this->assertSame(1, DB::table('outbox_events')
            ->where('aggregate_id', $invite->getKey())
            ->count());

        $recorded = json_encode([
            DB::table('institution_invites')->first(),
            DB::table('audit_events')->where('subject_id', $invite->getKey())->first(),
            DB::table('outbox_events')->where('aggregate_id', $invite->getKey())->first(),
        ]);

        $this->assertIsString($recorded);
        $this->assertStringNotContainsString($rawCode, $recorded);

        try {
            $acceptor->accept($rawCode, $this->createUser());
            $this->fail('An accepted invitation must not be transferable.');
        } catch (InstitutionInviteUnavailable) {
            $this->assertSame(1, DB::table('enrollments')
                ->where('organization_id', $organization->getKey())
                ->count());
        }
    }

    public function test_expired_invite_fails_without_creating_authoritative_records(): void
    {
        $rawCode = 'GEI-EXPIRED-SYNTHETIC';
        $organization = $this->createOrganization('Expired Invite Institution');
        $issuer = $this->createUser();
        [, $context] = $this->createMembership(
            $organization,
            $issuer,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->contexts()->run(
            $context,
            fn (): InstitutionInvite => InstitutionInvite::query()->forceCreate([
                'code_digest' => $this->app
                    ->make(InstitutionInviteCodeDigester::class)
                    ->digest($rawCode),
                'membership_type' => Membership::TYPE_LEARNER,
                'status' => InstitutionInvite::STATUS_PENDING,
                'expires_at' => now()->subMinute(),
            ]),
        );
        $learner = $this->createUser();

        try {
            $this->app->make(InstitutionInviteAcceptor::class)
                ->accept($rawCode, $learner);
            $this->fail('Expected an expired invitation to fail.');
        } catch (InstitutionInviteUnavailable) {
            $this->assertSame(0, DB::table('memberships')
                ->where('user_id', $learner->getKey())
                ->count());
            $this->assertSame(0, DB::table('audit_events')->count());
            $this->assertSame(0, DB::table('outbox_events')->count());
        }
    }

    public function test_invalid_cohort_assignment_rolls_back_membership_and_invite_acceptance(): void
    {
        $rawCode = 'GEI-ROLLBACK-SYNTHETIC';
        $organization = $this->createOrganization('Rollback Institution');
        $issuer = $this->createUser();
        [, $context] = $this->createMembership(
            $organization,
            $issuer,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $cohort = $this->createCohort($context);
        $invite = $this->contexts()->run(
            $context,
            fn (): InstitutionInvite => InstitutionInvite::query()->forceCreate([
                'code_digest' => $this->app
                    ->make(InstitutionInviteCodeDigester::class)
                    ->digest($rawCode),
                'membership_type' => Membership::TYPE_ORGANIZATION_ADMIN,
                'cohort_id' => $cohort->getKey(),
                'status' => InstitutionInvite::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]),
        );
        $candidate = $this->createUser();

        try {
            $this->app->make(InstitutionInviteAcceptor::class)
                ->accept($rawCode, $candidate);
            $this->fail('Expected an invalid invite/cohort combination to fail.');
        } catch (FoundationConfigurationException) {
            $this->assertSame(0, DB::table('memberships')
                ->where('user_id', $candidate->getKey())
                ->count());
            $this->assertSame(
                InstitutionInvite::STATUS_PENDING,
                DB::table('institution_invites')
                    ->where('id', $invite->getKey())
                    ->value('status'),
            );
            $this->assertSame(0, DB::table('audit_events')->count());
            $this->assertSame(0, DB::table('outbox_events')->count());
        }
    }

    public function test_invite_acceptance_rechecks_current_user_status_inside_transaction(): void
    {
        $rawCode = 'GEI-USER-STATUS-SYNTHETIC';
        $organization = $this->createOrganization('User Status Institution');
        $issuer = $this->createUser();
        [, $context] = $this->createMembership(
            $organization,
            $issuer,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $invite = $this->contexts()->run(
            $context,
            fn (): InstitutionInvite => InstitutionInvite::query()
                ->forceCreate([
                    'code_digest' => $this->app
                        ->make(InstitutionInviteCodeDigester::class)
                        ->digest($rawCode),
                    'membership_type' => Membership::TYPE_LEARNER,
                    'status' => InstitutionInvite::STATUS_PENDING,
                    'expires_at' => now()->addDay(),
                ]),
        );
        $staleActiveUser = $this->createUser();

        DB::table('users')
            ->where('id', $staleActiveUser->getKey())
            ->update(['status' => User::STATUS_SUSPENDED]);

        try {
            $this->app
                ->make(InstitutionInviteAcceptor::class)
                ->accept($rawCode, $staleActiveUser);
            $this->fail('A suspended invite candidate must fail closed.');
        } catch (InstitutionInviteUnavailable) {
            $this->assertDatabaseMissing('memberships', [
                'user_id' => $staleActiveUser->getKey(),
            ]);
            $this->assertDatabaseHas('institution_invites', [
                'id' => $invite->getKey(),
                'status' => InstitutionInvite::STATUS_PENDING,
            ]);
        }
    }
}
