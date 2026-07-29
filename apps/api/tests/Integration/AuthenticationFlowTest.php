<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\AuthenticationSecretDigester;
use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Models\AuthenticationSession;
use App\Models\InstitutionInvite;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OtpChallenge;
use App\Tenancy\DirectLearnerProvisioner;
use App\Tenancy\InstitutionInviteCodeDigester;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    private RecordingOtpDeliveryAdapter $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->delivery = new RecordingOtpDeliveryAdapter;
        $this->app->instance(OtpDeliveryAdapter::class, $this->delivery);
        $this->app->instance(OtpCodeGenerator::class, new FixedOtpCodeGenerator('123456'));
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_otp_is_hashed_single_use_and_creates_a_cookie_session(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+05:30');

        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertAccepted()
            ->assertJsonStructure(['challenge_id', 'expires_at']);
        self::assertStringContainsString(
            'no-store',
            (string) $issued->headers->get('Cache-Control'),
        );

        $challengeId = $issued->json('challenge_id');

        self::assertIsString($challengeId);
        self::assertSame([['+919876543210', '123456']], $this->delivery->deliveries);

        $challenge = OtpChallenge::query()->findOrFail($challengeId);
        self::assertNotSame('+919876543210', $challenge->destination_digest);
        self::assertNotSame('123456', $challenge->code_digest);
        self::assertTrue($challenge->expires_at->equalTo(now()->addMinutes(5)));

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543210',
                'code' => '123456',
            ])
            ->assertOk()
            ->assertJsonPath('session.current', true)
            ->assertJsonPath('session.privileged', false)
            ->assertJsonPath('session.mfa_verified_at', null)
            ->assertJsonStructure([
                'user' => ['id'],
                'session' => [
                    'id',
                    'current',
                    'created_at',
                    'last_activity_at',
                    'expires_at',
                    'privileged',
                    'mfa_verified_at',
                ],
            ]);

        $publicSessionId = $verified->json('session.id');
        self::assertIsString($publicSessionId);
        self::assertStringContainsString(
            'no-store',
            (string) $verified->headers->get('Cache-Control'),
        );
        $this->adoptSessionCookie($verified);

        $authenticationSession = AuthenticationSession::query()->findOrFail($publicSessionId);
        $storageSessionId = Crypt::decryptString(
            $authenticationSession->storage_session_ciphertext,
        );
        self::assertNotSame($publicSessionId, $authenticationSession->storage_session_digest);
        self::assertStringNotContainsString(
            $storageSessionId,
            $authenticationSession->storage_session_ciphertext,
        );
        self::assertSame(
            $this->app->make(AuthenticationSecretDigester::class)->digest(
                'web-session-storage',
                $storageSessionId,
            ),
            $authenticationSession->storage_session_digest,
        );
        self::assertFalse($authenticationSession->is_privileged);
        self::assertTrue($authenticationSession->idle_expires_at->equalTo(now()->addMinutes(120)));
        self::assertTrue($authenticationSession->absolute_expires_at->equalTo(now()->addDays(7)));

        $this->assertDatabaseCount('users', 1);

        $loggedOut = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();
        self::assertStringContainsString(
            'no-store',
            (string) $loggedOut->headers->get('Cache-Control'),
        );
        $this->assertDatabaseCount('user_identities', 1);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('authentication_sessions', 1);
        $this->assertDatabaseHas('otp_challenges', [
            'id' => $challengeId,
            'failed_attempts' => 0,
        ]);
        self::assertNotNull($challenge->fresh()?->consumed_at);

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543210',
                'code' => '123456',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'One or more request values are invalid.');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_invite_aware_json_otp_accepts_the_institution_without_direct_membership_on_postgresql(): void
    {
        $rawInviteCode = 'GEI-POSTGRES-WEB-INVITE';
        [$organizationId, $inviteId] = $this->createInstitutionInvite(
            $rawInviteCode,
        );
        $records = [];
        Log::listen(static function (MessageLogged $event) use (&$records): void {
            $records[] = [
                'message' => $event->message,
                'context' => $event->context,
            ];
        });

        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543220',
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543220',
                'code' => '123456',
                'institution_invite_code' => $rawInviteCode,
            ])
            ->assertOk()
            ->assertJsonPath('session.current', true);
        $userId = $verified->json('user.id');
        self::assertIsString($userId);

        $directOrganizationId = DB::table('organizations')
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->value('id');
        self::assertIsString($directOrganizationId);
        self::assertSame(1, DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->count());
        self::assertSame(0, DB::table('memberships')
            ->where('organization_id', $directOrganizationId)
            ->where('user_id', $userId)
            ->count());
        $this->assertDatabaseHas('institution_invites', [
            'id' => $inviteId,
            'status' => InstitutionInvite::STATUS_ACCEPTED,
            'accepted_by_user_id' => $userId,
            'accepted_idempotency_key' => $challengeId,
        ]);
        $this->assertDatabaseCount('authentication_sessions', 1);

        $recorded = json_encode([
            'invite' => DB::table('institution_invites')
                ->where('id', $inviteId)
                ->first(),
            'sessions' => DB::table('sessions')->pluck('payload')->all(),
            'audit' => DB::table('audit_events')->get()->all(),
            'outbox' => DB::table('outbox_events')->get()->all(),
            'logs' => $records,
        ], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($rawInviteCode, $recorded);
    }

    public function test_invalid_json_invite_is_non_enumerating_and_rolls_back_on_postgresql(): void
    {
        $unknownInviteCode = 'GEI-POSTGRES-UNKNOWN-INVITE';
        $invalidInviteChallenge = $this->withHeader(
            'Origin',
            'https://localhost',
        )->postJson('/api/v1/auth/otp/requests', [
            'mobile_number' => '+919876543221',
        ])->assertAccepted()->json('challenge_id');
        $wrongOtpChallenge = $this->withHeader(
            'Origin',
            'https://localhost',
        )->postJson('/api/v1/auth/otp/requests', [
            'mobile_number' => '+919876543222',
        ])->assertAccepted()->json('challenge_id');
        self::assertIsString($invalidInviteChallenge);
        self::assertIsString($wrongOtpChallenge);

        $invalidInvite = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $invalidInviteChallenge,
                'mobile_number' => '+919876543221',
                'code' => '123456',
                'institution_invite_code' => $unknownInviteCode,
            ])
            ->assertUnprocessable();
        $wrongOtp = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $wrongOtpChallenge,
                'mobile_number' => '+919876543222',
                'code' => '000000',
                'institution_invite_code' => $unknownInviteCode,
            ])
            ->assertUnprocessable();

        self::assertSame(
            $this->problemFingerprint($wrongOtp),
            $this->problemFingerprint($invalidInvite),
        );
        $this->assertDatabaseHas('otp_challenges', [
            'id' => $invalidInviteChallenge,
            'consumed_at' => null,
            'failed_attempts' => 0,
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_identities', 0);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('authentication_sessions', 0);
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseCount('outbox_events', 0);

        $validInviteCode = 'GEI-POSTGRES-RETRY-INVITE';
        $this->createInstitutionInvite($validInviteCode);
        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $invalidInviteChallenge,
                'mobile_number' => '+919876543221',
                'code' => '123456',
                'institution_invite_code' => $validInviteCode,
            ])
            ->assertOk();
    }

    public function test_five_failed_attempts_consume_a_challenge_without_provisioning(): void
    {
        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertAccepted();

        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withHeader('Origin', 'https://localhost')
                ->postJson('/api/v1/auth/otp/verifications', [
                    'challenge_id' => $challengeId,
                    'mobile_number' => '+919876543210',
                    'code' => '000000',
                ])
                ->assertUnprocessable()
                ->assertJsonMissing(['mobile_number' => '+919876543210']);
        }

        $this->assertDatabaseHas('otp_challenges', [
            'id' => $challengeId,
            'failed_attempts' => 5,
        ]);
        self::assertNotNull(OtpChallenge::query()->findOrFail($challengeId)->consumed_at);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_session_idle_expiry_and_user_revocation_are_enforced(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+05:30');

        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543210',
                'code' => '123456',
            ])
            ->assertOk();
        $sessionId = $verified->json('session.id');
        self::assertIsString($sessionId);
        $this->adoptSessionCookie($verified);

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $sessionId)
            ->assertJsonPath('data.0.current', true);

        CarbonImmutable::setTestNow(now()->addMinutes(121));

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/auth/sessions')
            ->assertUnauthorized();

        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $sessionId,
            'revocation_reason' => 'expired',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.session.expired',
            'subject_id' => $sessionId,
        ]);
    }

    public function test_ordinary_session_absolute_lifetime_is_enforced_even_when_idle_is_active(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');
        $authenticated = $this->authenticateWithOtp('+919876543210');
        $sessionId = $authenticated['response']->json('session.id');
        self::assertIsString($sessionId);

        $authenticationSession = AuthenticationSession::query()->findOrFail($sessionId);
        $authenticationSession->forceFill([
            'idle_expires_at' => now()->addDays(8),
        ])->save();
        CarbonImmutable::setTestNow(
            $authenticationSession->started_at->copy()->addDays(7),
        );

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/auth/sessions')
            ->assertUnauthorized();

        self::assertSame(
            'expired',
            $authenticationSession->fresh()?->revocation_reason,
        );
    }

    public function test_user_can_revoke_an_arbitrary_session_and_then_all_remaining_sessions(): void
    {
        $this->useBrowserCookie(null);
        $first = $this->authenticateWithOtp('+919876543210');
        $firstSessionId = $first['response']->json('session.id');
        $firstUserId = $first['response']->json('user.id');
        self::assertIsString($firstSessionId);
        self::assertIsString($firstUserId);

        $this->useBrowserCookie(null);
        $second = $this->authenticateWithOtp('+919876543210');
        $secondSessionId = $second['response']->json('session.id');
        self::assertIsString($secondSessionId);
        self::assertSame($firstUserId, $second['response']->json('user.id'));
        self::assertNotSame($firstSessionId, $secondSessionId);

        $firstSession = AuthenticationSession::query()->findOrFail($firstSessionId);
        $secondSession = AuthenticationSession::query()->findOrFail($secondSessionId);
        $firstStorageId = Crypt::decryptString($firstSession->storage_session_ciphertext);
        $secondStorageId = Crypt::decryptString($secondSession->storage_session_ciphertext);

        $this->useBrowserCookie($first['cookie']);
        $this->withHeader('Origin', 'https://localhost')
            ->deleteJson('/api/v1/auth/sessions/'.$secondSessionId)
            ->assertNoContent();

        self::assertNull($firstSession->fresh()?->revoked_at);
        self::assertSame(
            'user_revoked',
            $secondSession->fresh()?->revocation_reason,
        );
        self::assertTrue(DB::table('sessions')->where('id', $firstStorageId)->exists());
        self::assertFalse(DB::table('sessions')->where('id', $secondStorageId)->exists());

        $this->withHeader('Origin', 'https://localhost')
            ->deleteJson('/api/v1/auth/sessions')
            ->assertNoContent();

        self::assertSame(
            'user_revoked_all',
            $firstSession->fresh()?->revocation_reason,
        );
        self::assertFalse(DB::table('sessions')->where('id', $firstStorageId)->exists());
        self::assertSame(
            0,
            AuthenticationSession::query()
                ->where('user_id', $firstUserId)
                ->whereNull('revoked_at')
                ->count(),
        );
    }

    public function test_expired_otp_is_consumed_without_provisioning(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');
        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        CarbonImmutable::setTestNow(now()->addMinutes(5));

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543210',
                'code' => '123456',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'One or more request values are invalid.');

        self::assertNotNull(OtpChallenge::query()->findOrFail($challengeId)->consumed_at);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_new_otp_supersedes_the_previous_active_challenge(): void
    {
        $first = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertAccepted();
        $second = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertAccepted();
        $firstId = $first->json('challenge_id');
        $secondId = $second->json('challenge_id');
        self::assertIsString($firstId);
        self::assertIsString($secondId);
        self::assertNotSame($firstId, $secondId);

        $superseded = OtpChallenge::query()->findOrFail($firstId);
        self::assertNotNull($superseded->consumed_at);
        self::assertNotNull($superseded->superseded_at);
        self::assertNull(OtpChallenge::query()->findOrFail($secondId)->consumed_at);

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $firstId,
                'mobile_number' => '+919876543210',
                'code' => '123456',
            ])
            ->assertUnprocessable();
        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $secondId,
                'mobile_number' => '+919876543210',
                'code' => '123456',
            ])
            ->assertOk();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_and_unknown_phone_numbers_receive_enumeration_equivalent_responses(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');
        $this->app->make(DirectLearnerProvisioner::class)
            ->provisionVerifiedIdentity('phone', '+919876543210');

        $existing = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertAccepted();
        $unknown = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919999999999',
            ])
            ->assertAccepted();

        self::assertSame(
            array_keys((array) $existing->json()),
            array_keys((array) $unknown->json()),
        );
        self::assertSame(
            $existing->json('expires_at'),
            $unknown->json('expires_at'),
        );

        $existingChallengeId = $existing->json('challenge_id');
        self::assertIsString($existingChallengeId);
        $wrongCode = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $existingChallengeId,
                'mobile_number' => '+919876543210',
                'code' => '000000',
            ])
            ->assertUnprocessable();
        $wrongDestination = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $existingChallengeId,
                'mobile_number' => '+918888888888',
                'code' => '123456',
            ])
            ->assertUnprocessable();
        $unknownChallenge = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => (string) Str::ulid(),
                'mobile_number' => '+919876543210',
                'code' => '123456',
            ])
            ->assertUnprocessable();

        self::assertSame(
            $this->problemFingerprint($wrongCode),
            $this->problemFingerprint($wrongDestination),
        );
        self::assertSame(
            $this->problemFingerprint($wrongCode),
            $this->problemFingerprint($unknownChallenge),
        );
    }

    public function test_otp_requests_and_failures_do_not_log_sensitive_values(): void
    {
        $records = [];
        Log::listen(static function (MessageLogged $event) use (&$records): void {
            $records[] = [
                'level' => $event->level,
                'message' => $event->message,
                'context' => $event->context,
            ];
        });

        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);
        $challenge = OtpChallenge::query()->findOrFail($challengeId);

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543210',
                'code' => '000000',
            ])
            ->assertUnprocessable();

        self::assertNotEmpty($records);
        $encoded = json_encode($records, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('+919876543210', $encoded);
        self::assertStringNotContainsString('000000', $encoded);
        self::assertStringNotContainsString('123456', $encoded);
        self::assertStringNotContainsString($challenge->destination_digest, $encoded);
        self::assertStringNotContainsString($challenge->code_digest, $encoded);
    }

    public function test_non_stateful_json_verification_fails_before_consuming_the_otp(): void
    {
        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);
        $cookie = $this->adoptSessionCookie($issued);

        $this->withoutHeader('Origin');
        $this->useBrowserCookie($cookie);
        $this->postJson('/api/v1/auth/otp/verifications', [
            'challenge_id' => $challengeId,
            'mobile_number' => '+919876543210',
            'code' => '123456',
        ])->assertUnauthorized();

        self::assertNull(OtpChallenge::query()->findOrFail($challengeId)->consumed_at);
        $this->assertDatabaseCount('users', 0);

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543210',
                'code' => '123456',
            ])
            ->assertOk();
    }

    public function test_privilege_transition_tightens_both_idle_and_absolute_lifetimes(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');

        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543210',
                'code' => '123456',
            ])
            ->assertOk();
        $sessionId = $verified->json('session.id');
        self::assertIsString($sessionId);
        $this->adoptSessionCookie($verified);

        $membership = DB::table('memberships')->first(['id', 'organization_id']);
        self::assertNotNull($membership);
        $roleId = (string) Str::ulid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $membership->organization_id,
            'key' => 'organization_admin',
            'name' => 'Organization administrator',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('membership_roles')->insert([
            'organization_id' => $membership->organization_id,
            'membership_id' => $membership->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CarbonImmutable::setTestNow(now()->addMinute());

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.0.privileged', true)
            ->assertJsonPath('data.0.mfa_verified_at', null);

        $authenticationSession = AuthenticationSession::query()->findOrFail($sessionId);
        self::assertTrue(
            $authenticationSession->absolute_expires_at->equalTo(
                $authenticationSession->started_at->copy()->addHours(12),
            ),
        );
        self::assertTrue(
            $authenticationSession->idle_expires_at->equalTo(now()->addMinutes(30)),
        );
    }

    public function test_privilege_transition_after_the_tightened_absolute_boundary_revokes_before_downstream_access(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');
        $authenticated = $this->authenticateWithOtp('+919876543210');
        $sessionId = $authenticated['response']->json('session.id');
        self::assertIsString($sessionId);
        $authenticationSession = AuthenticationSession::query()
            ->findOrFail($sessionId);
        $authenticationSession->forceFill([
            'idle_expires_at' => $authenticationSession->started_at
                ->copy()
                ->addDays(2),
        ])->save();

        CarbonImmutable::setTestNow(
            $authenticationSession->started_at
                ->copy()
                ->addHours(12)
                ->addMinute(),
        );
        $membership = DB::table('memberships')->first([
            'id',
            'organization_id',
        ]);
        self::assertNotNull($membership);
        $roleId = (string) Str::ulid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $membership->organization_id,
            'key' => 'organization_admin',
            'name' => 'Organization administrator',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('membership_roles')->insert([
            'organization_id' => $membership->organization_id,
            'membership_id' => $membership->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeader('Origin', 'https://localhost')
            ->getJson('/api/v1/auth/sessions')
            ->assertUnauthorized();

        $authenticationSession->refresh();
        self::assertTrue($authenticationSession->is_privileged);
        self::assertSame('expired', $authenticationSession->revocation_reason);
        self::assertTrue(
            $authenticationSession->absolute_expires_at->equalTo(
                $authenticationSession->started_at->copy()->addHours(12),
            ),
        );
        self::assertTrue(
            $authenticationSession->absolute_expires_at->lessThan(now()),
        );
        $this->assertDatabaseHas('audit_events', [
            'action' => 'authentication.session.expired',
            'subject_id' => $sessionId,
        ]);
    }

    public function test_json_otp_contract_rejects_noncanonical_and_extra_fields(): void
    {
        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+91 98765 43210',
            ])
            ->assertUnprocessable();
        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
                'unexpected' => true,
            ])
            ->assertUnprocessable();

        self::assertCount(0, $this->delivery->deliveries);
    }

    public function test_postgresql_connection_timezone_is_forced_to_utc(): void
    {
        $setting = DB::selectOne("select current_setting('TIMEZONE') as timezone");

        self::assertNotNull($setting);
        self::assertSame('UTC', $setting->timezone);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function adoptSessionCookie(TestResponse $response): string
    {
        $cookie = $response->getCookie((string) config('session.cookie'));

        self::assertNotNull($cookie);
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());

        $value = (string) $cookie->getValue();
        $this->withCookie($cookie->getName(), $value);

        return $value;
    }

    /**
     * @return array{response: TestResponse<Response>, cookie: string}
     */
    private function authenticateWithOtp(string $mobileNumber): array
    {
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
                'code' => '123456',
            ])
            ->assertOk();

        return [
            'response' => $verified,
            'cookie' => $this->adoptSessionCookie($verified),
        ];
    }

    private function useBrowserCookie(?string $cookie): void
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();

        if ($cookie !== null) {
            $this->withCookie((string) config('session.cookie'), $cookie);
        }
    }

    /**
     * @return array{string, string}
     */
    private function createInstitutionInvite(string $rawCode): array
    {
        $now = now();
        $organizationId = (string) Str::ulid();
        $inviteId = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'system_key' => null,
            'name' => 'PostgreSQL OTP Institution',
            'kind' => Organization::KIND_INSTITUTION,
            'status' => Organization::STATUS_ACTIVE,
            'timezone' => 'Asia/Kolkata',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('institution_invites')->insert([
            'id' => $inviteId,
            'organization_id' => $organizationId,
            'code_digest' => $this->app
                ->make(InstitutionInviteCodeDigester::class)
                ->digest($rawCode),
            'membership_type' => Membership::TYPE_LEARNER,
            'status' => InstitutionInvite::STATUS_PENDING,
            'expires_at' => $now->copy()->addDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$organizationId, $inviteId];
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<string, mixed>
     */
    private function problemFingerprint(TestResponse $response): array
    {
        $problem = $response->json();
        self::assertIsArray($problem);
        unset($problem['correlation_id']);
        unset($problem['instance']);

        return $problem;
    }
}

final class FixedOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class RecordingOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    /**
     * @var list<array{string, string}>
     */
    public array $deliveries = [];

    public function send(string $normalizedMobileNumber, string $code): void
    {
        $this->deliveries[] = [$normalizedMobileNumber, $code];
    }
}
