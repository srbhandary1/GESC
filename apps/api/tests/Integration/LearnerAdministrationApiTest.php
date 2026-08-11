<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Models\AuthenticationSession;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\InstitutionInviteAcceptor;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

final class LearnerAdministrationApiTest extends TenancyIntegrationTestCase
{
    private const string OTP = '864209';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new LearnerAdministrationOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new LearnerAdministrationNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_learner_administration_is_tenant_scoped_strict_and_replay_safe(): void
    {
        $actor = $this->signInWeb('+919100000021');
        $organization = $this->createOrganization('Learner Administration');
        $foreignOrganization = $this->createOrganization('Foreign Learners');
        [$actorMembership, $actorContext] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $actorMembership,
            $actorContext,
            [
                'learners.read',
                'learners.profile.correct',
                'learners.lifecycle.manage',
                'enrollments.read',
                'enrollments.manage',
                'enrollments.curriculum.assign',
                'institution_invites.manage',
                'roles.manage',
                'cohorts.read',
                'trainer_profiles.read',
            ],
            'learner-administrator',
        );
        $safeRole = $this->learnerSafeRole($organization, $actorContext);
        $cohort = $this->createCohort($actorContext, 'Conversation Cohort');
        $learnerUser = $this->createUser();
        $learnerUser->forceFill(['display_name' => 'Asha Learner'])->save();
        [$learnerMembership] = $this->createMembership(
            $organization,
            $learnerUser,
            Membership::TYPE_LEARNER,
        );
        $this->assignRole($organization, $learnerMembership, $safeRole);
        $this->insertLearnerProfile(
            $organization,
            $learnerMembership,
            'everyday-conversation',
        );
        $foreignUser = $this->createUser();
        $foreignUser->forceFill(['display_name' => 'Foreign Learner'])->save();
        [$foreignMembership] = $this->createMembership(
            $foreignOrganization,
            $foreignUser,
            Membership::TYPE_LEARNER,
        );
        $this->selectMembership($actorMembership);

        $overview = $this->getJson('/api/v1/operator/overview')
            ->assertOk()
            ->assertJsonPath('navigation', ['overview', 'people', 'learners'])
            ->assertJsonPath('panels', [
                'cohorts',
                'institution_invites',
                'roles',
                'trainer_profiles',
                'learner_directory',
                'learner_enrollments',
                'learner_invitations',
            ]);
        $this->assertNoStore($overview);

        $directory = $this->getJson('/api/v1/operator/learners?q=asha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('choices.cohorts.0.id', $cohort->getKey())
            ->assertJsonPath('choices.cohorts.0.version', 1)
            ->assertJsonPath('data.0.membership_id', $learnerMembership->getKey())
            ->assertJsonPath('data.0.display_name', 'Asha Learner')
            ->assertJsonPath('data.0.profile.current_goal_key', 'everyday-conversation')
            ->assertJsonPath('data.0.profile.current_goal_version', 1)
            ->assertJsonPath('data.0.attempt_count', 0)
            ->assertJsonPath('data.0.allowed_actions', [
                'learner.goal_correct',
                'learner.suspend',
                'learner_enrollment.create',
            ])
            ->assertJsonMissing(['user_id'])
            ->assertJsonMissing(['Foreign Learner']);
        $this->assertNoStore($directory);

        $this->getJson('/api/v1/operator/learners?unexpected=true')
            ->assertUnprocessable();
        $this->getJson('/api/v1/operator/learners?q=%25')
            ->assertUnprocessable();
        $this->getJson('/api/v1/operator/learners/'.$foreignMembership->getKey())
            ->assertNotFound();

        $this->postCommand(
            '/api/v1/operator/learners/'.$learnerMembership->getKey().'/goal-corrections',
            ['goal_key' => 'workplace-confidence', 'expected_version' => 1],
            (string) Str::ulid(),
        )
            ->assertForbidden()
            ->assertJsonPath('message_key', 'problem.fresh_mfa_required');
        $this->freshenCurrentWebSession($actor);

        $goalKey = (string) Str::ulid();
        $corrected = $this->postCommand(
            '/api/v1/operator/learners/'.$learnerMembership->getKey().'/goal-corrections',
            ['goal_key' => 'workplace-confidence', 'expected_version' => 1],
            $goalKey,
        )
            ->assertOk()
            ->assertJsonPath('data.profile.current_goal_key', 'workplace-confidence')
            ->assertJsonPath('data.profile.current_goal_version', 2)
            ->assertJsonPath('replayed', false)
            ->assertJsonMissing(['user_id']);
        $this->assertNoStore($corrected);
        $this->postCommand(
            '/api/v1/operator/learners/'.$learnerMembership->getKey().'/goal-corrections',
            ['goal_key' => 'workplace-confidence', 'expected_version' => 1],
            $goalKey,
        )
            ->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('data.profile.current_goal_version', 2);
        self::assertSame(1, DB::table('learner_goal_corrections')
            ->where('learner_membership_id', $learnerMembership->getKey())
            ->count());
        self::assertSame(
            'everyday-conversation',
            DB::table('learner_goals')
                ->where('learner_membership_id', $learnerMembership->getKey())
                ->value('goal_key'),
        );

        $enrollmentKey = (string) Str::ulid();
        $createdEnrollment = $this->postCommand(
            '/api/v1/operator/learners/'.$learnerMembership->getKey().'/enrollments',
            [
                'expected_membership_version' => 1,
                'expected_version' => 0,
                'cohort_id' => $cohort->getKey(),
                'expected_cohort_version' => 1,
            ],
            $enrollmentKey,
        )
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_curriculum')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.cohort.id', $cohort->getKey())
            ->assertJsonPath('data.curriculum_pin', null)
            ->assertJsonPath('replayed', false);
        $enrollmentId = $createdEnrollment->json('data.id');
        self::assertIsString($enrollmentId);
        $this->postCommand(
            '/api/v1/operator/learners/'.$learnerMembership->getKey().'/enrollments',
            [
                'expected_membership_version' => 1,
                'expected_version' => 0,
                'cohort_id' => $cohort->getKey(),
                'expected_cohort_version' => 1,
            ],
            $enrollmentKey,
        )
            ->assertOk()
            ->assertJsonPath('data.id', $enrollmentId)
            ->assertJsonPath('replayed', true);

        $enrollments = $this->getJson(
            '/api/v1/operator/learners/'.$learnerMembership->getKey().'/enrollments',
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enrollmentId)
            ->assertJsonPath('data.0.trainers', [])
            ->assertJsonMissing(['user_id']);
        $this->assertNoStore($enrollments);

        $this->postCommand(
            '/api/v1/operator/enrollments/'.$enrollmentId.'/cohort-changes',
            [
                'expected_version' => 1,
                'cohort_id' => null,
                'expected_cohort_version' => null,
            ],
            (string) Str::ulid(),
        )
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.cohort', null);
        $this->postCommand(
            '/api/v1/operator/enrollments/'.$enrollmentId.'/withdrawals',
            ['expected_version' => 2],
            (string) Str::ulid(),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn')
            ->assertJsonPath('data.version', 3);

        $this->postCommand(
            '/api/v1/operator/learners/'.$learnerMembership->getKey().'/suspensions',
            ['expected_version' => 1],
            (string) Str::ulid(),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.allowed_actions', ['learner.reactivate']);
        $this->postCommand(
            '/api/v1/operator/learners/'.$learnerMembership->getKey().'/reactivations',
            ['expected_version' => 2],
            (string) Str::ulid(),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.version', 3);

        $invitationBody = [
            'role_id' => $safeRole->getKey(),
            'expected_role_version' => 1,
            'cohort_id' => null,
            'expected_cohort_version' => null,
            'expires_in_hours' => 72,
            'expected_version' => 0,
        ];
        $invitationKey = (string) Str::ulid();
        $invitation = $this->postCommand(
            '/api/v1/operator/learner-invitations',
            $invitationBody,
            $invitationKey,
        )
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('replayed', false);
        $invitationId = $invitation->json('data.id');
        $activationCode = $invitation->json('activation_code');
        self::assertIsString($invitationId);
        self::assertIsString($activationCode);
        self::assertGreaterThanOrEqual(22, strlen($activationCode));
        $this->postCommand(
            '/api/v1/operator/learner-invitations',
            $invitationBody,
            $invitationKey,
        )
            ->assertOk()
            ->assertJsonPath('data.id', $invitationId)
            ->assertJsonPath('activation_code', null)
            ->assertJsonPath('replayed', true);
        $this->postCommand(
            '/api/v1/operator/learner-invitations',
            [...$invitationBody, 'organization_id' => $foreignOrganization->getKey()],
            (string) Str::ulid(),
        )->assertUnprocessable();

        $candidate = $this->createUser();
        $accepted = $this->app->make(InstitutionInviteAcceptor::class)
            ->accept($activationCode, $candidate);
        self::assertFalse($accepted->replayed);
        self::assertSame(Membership::TYPE_LEARNER, $accepted->membership->membership_type);
        self::assertNull($accepted->enrollment);
        self::assertSame(1, DB::table('membership_roles')
            ->where('organization_id', $organization->getKey())
            ->where('membership_id', $accepted->membership->getKey())
            ->where('role_id', $safeRole->getKey())
            ->count());

        $invitations = $this->getJson('/api/v1/operator/learner-invitations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invitationId)
            ->assertJsonPath('data.0.status', 'accepted')
            ->assertJsonPath('choices.roles.0.id', $safeRole->getKey())
            ->assertJsonPath('choices.cohorts.0.id', $cohort->getKey())
            ->assertJsonMissing(['code_digest'])
            ->assertJsonMissing(['accepted_by_user_id'])
            ->assertJsonMissing([$activationCode]);
        $this->assertNoStore($invitations);
    }

    private function learnerSafeRole(
        Organization $organization,
        OrganizationContext $context,
    ): Role {
        $role = $this->createRole($context, 'learner-safe');
        $now = now();
        DB::table('role_permissions')->insert([
            'organization_id' => $organization->getKey(),
            'role_id' => $role->getKey(),
            'permission_id' => DB::table('permissions')
                ->where('key', 'learning.participate')
                ->value('id'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $role;
    }

    private function assignRole(
        Organization $organization,
        Membership $membership,
        Role $role,
    ): void {
        $now = now();
        DB::table('membership_roles')->insert([
            'organization_id' => $organization->getKey(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertLearnerProfile(
        Organization $organization,
        Membership $membership,
        string $goalKey,
    ): void {
        $profileId = (string) Str::ulid();
        $goalId = (string) Str::ulid();
        $now = now();
        DB::table('learner_profiles')->insert([
            'id' => $profileId,
            'organization_id' => $organization->getKey(),
            'learner_membership_id' => $membership->getKey(),
            'adult_confirmed_at' => $now,
            'onboarding_completed_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('learner_goals')->insert([
            'id' => $goalId,
            'organization_id' => $organization->getKey(),
            'learner_profile_id' => $profileId,
            'learner_membership_id' => $membership->getKey(),
            'goal_key' => $goalKey,
            'selected_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('learner_profile_current_goals')->insert([
            'id' => $goalId,
            'organization_id' => $organization->getKey(),
            'learner_profile_id' => $profileId,
            'learner_membership_id' => $membership->getKey(),
            'goal_key' => $goalKey,
            'version' => 1,
            'corrected_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function signInWeb(string $mobileNumber): User
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();

        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => $mobileNumber,
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);
        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => $mobileNumber,
                'code' => self::OTP,
            ])
            ->assertOk();
        $cookie = $verified->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
        $userId = $verified->json('user.id');
        self::assertIsString($userId);

        return User::query()->whereKey($userId)->sole();
    }

    private function selectMembership(Membership $membership): void
    {
        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $membership->getKey(),
            ])
            ->assertOk();
    }

    private function freshenCurrentWebSession(User $user): void
    {
        $session = AuthenticationSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->firstOrFail();
        $this->markTestAuthenticationSessionPrivileged(
            (string) $session->getKey(),
            $user,
            now(),
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function postCommand(
        string $uri,
        array $body,
        string $idempotencyKey,
    ): TestResponse {
        return $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->withHeader('Origin', 'https://localhost')
            ->postJson($uri, $body);
    }

    /** @param TestResponse<Response> $response */
    private function assertNoStore(TestResponse $response): void
    {
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }
}

final class LearnerAdministrationOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class LearnerAdministrationNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(string $normalizedMobileNumber, string $code): void {}
}
