<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\Mobile\OAuthAuthorizationTarget;
use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Models\InstitutionInvite;
use App\Models\Membership;
use App\Models\Organization;
use App\Tenancy\InstitutionInviteCodeDigester;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class InviteAwareOtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private SqliteInviteOtpDeliveryAdapter $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);
        $this->seed(FoundationSeeder::class);
        $this->delivery = new SqliteInviteOtpDeliveryAdapter;
        $this->app->instance(OtpDeliveryAdapter::class, $this->delivery);
        $this->app->instance(
            OtpCodeGenerator::class,
            new SqliteInviteOtpCodeGenerator('123456'),
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_json_invite_otp_creates_only_the_institution_membership_on_sqlite(): void
    {
        $rawInviteCode = 'GEI-SQLITE-WEB-INVITE';
        [$organizationId, $inviteId] = $this->createInstitutionInvite(
            $rawInviteCode,
        );
        $logged = [];
        Log::listen(static function (MessageLogged $event) use (&$logged): void {
            $logged[] = [$event->message, $event->context];
        });

        $issued = $this->issueJsonChallenge('+919876543210');
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543210',
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
            'sessions' => $this->app['session']->all(),
            'audit' => DB::table('audit_events')->get()->all(),
            'outbox' => DB::table('outbox_events')->get()->all(),
            'logs' => $logged,
        ], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($rawInviteCode, $recorded);
    }

    public function test_invalid_json_invite_is_non_enumerating_and_rolls_back_the_otp_transaction_on_sqlite(): void
    {
        $unknownInviteCode = 'GEI-SQLITE-UNKNOWN-INVITE';
        $invalidInviteChallenge = $this->issueJsonChallenge('+919876543210')
            ->json('challenge_id');
        $wrongOtpChallenge = $this->issueJsonChallenge('+919876543211')
            ->json('challenge_id');
        self::assertIsString($invalidInviteChallenge);
        self::assertIsString($wrongOtpChallenge);

        $invalidInvite = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $invalidInviteChallenge,
                'mobile_number' => '+919876543210',
                'code' => '123456',
                'institution_invite_code' => $unknownInviteCode,
            ])
            ->assertUnprocessable();
        $wrongOtp = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $wrongOtpChallenge,
                'mobile_number' => '+919876543211',
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

        $validInviteCode = 'GEI-SQLITE-RETRY-INVITE';
        $this->createInstitutionInvite($validInviteCode);
        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $invalidInviteChallenge,
                'mobile_number' => '+919876543210',
                'code' => '123456',
                'institution_invite_code' => $validInviteCode,
            ])
            ->assertOk();
    }

    public function test_mobile_server_browser_encrypts_invite_state_and_creates_no_direct_membership_on_sqlite(): void
    {
        $rawInviteCode = 'GEI-SQLITE-MOBILE-INVITE';
        [$organizationId, $inviteId] = $this->createInstitutionInvite(
            $rawInviteCode,
        );
        $target = '/oauth/authorize?client_id=sqlite-invite-test';

        $challenge = $this->withSession([
            OAuthAuthorizationTarget::SESSION_KEY => $target,
        ])->post('/auth/mobile/sign-in/otp/requests', [
            'mobile_number' => '+919876543212',
            'institution_invite_code' => $rawInviteCode,
        ])
            ->assertOk()
            ->assertSee('Enter the verification code')
            ->assertDontSee($rawInviteCode);

        $inviteCiphertext = $this->app['session']->get(
            'mobile_oauth.invite_ciphertext',
        );
        self::assertIsString($inviteCiphertext);
        self::assertNotSame($rawInviteCode, $inviteCiphertext);
        self::assertSame(
            $rawInviteCode,
            Crypt::decryptString($inviteCiphertext),
        );
        self::assertStringNotContainsString(
            $rawInviteCode,
            json_encode(
                $this->app['session']->all(),
                JSON_THROW_ON_ERROR,
            ),
        );
        $this->adoptSessionCookie($challenge);

        $verified = $this->post(
            '/auth/mobile/sign-in/otp/verifications',
            ['code' => '123456'],
        )
            ->assertRedirect($target)
            ->assertSessionMissing('mobile_oauth.challenge_id')
            ->assertSessionMissing('mobile_oauth.mobile_ciphertext')
            ->assertSessionMissing('mobile_oauth.invite_ciphertext');

        $userId = DB::table('users')->value('id');
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
        $challengeId = DB::table('otp_challenges')->value('id');
        self::assertIsString($challengeId);
        $this->assertDatabaseHas('institution_invites', [
            'id' => $inviteId,
            'accepted_idempotency_key' => $challengeId,
        ]);
        $recorded = json_encode([
            DB::table('institution_invites')->where('id', $inviteId)->first(),
            DB::table('audit_events')->get()->all(),
            DB::table('outbox_events')->get()->all(),
            $this->app['session']->all(),
        ], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($rawInviteCode, $recorded);
    }

    public function test_invalid_mobile_invite_matches_wrong_otp_and_rolls_back_on_sqlite(): void
    {
        $unknownInviteCode = 'GEI-SQLITE-MOBILE-UNKNOWN';
        $target = '/oauth/authorize?client_id=sqlite-invalid-invite-test';

        $this->withSession([
            OAuthAuthorizationTarget::SESSION_KEY => $target,
        ])->post('/auth/mobile/sign-in/otp/requests', [
            'mobile_number' => '+919876543214',
            'institution_invite_code' => $unknownInviteCode,
        ])->assertOk();
        $invalidInviteChallengeId = $this->app['session']->get(
            'mobile_oauth.challenge_id',
        );
        self::assertIsString($invalidInviteChallengeId);

        $invalidInvite = $this->post(
            '/auth/mobile/sign-in/otp/verifications',
            ['code' => '123456'],
        )
            ->assertUnprocessable()
            ->assertSee('The verification code could not be verified.');
        $this->assertDatabaseHas('otp_challenges', [
            'id' => $invalidInviteChallengeId,
            'consumed_at' => null,
            'failed_attempts' => 0,
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('authentication_sessions', 0);

        $this->post('/auth/mobile/sign-in/otp/requests', [
            'mobile_number' => '+919876543215',
            'institution_invite_code' => $unknownInviteCode,
        ])->assertOk();
        $wrongOtp = $this->post(
            '/auth/mobile/sign-in/otp/verifications',
            ['code' => '000000'],
        )
            ->assertUnprocessable()
            ->assertSee('The verification code could not be verified.');

        self::assertSame(
            $invalidInvite->getStatusCode(),
            $wrongOtp->getStatusCode(),
        );
        self::assertSame(
            $invalidInvite->getContent(),
            $wrongOtp->getContent(),
        );
        self::assertStringNotContainsString(
            $unknownInviteCode,
            json_encode(
                $this->app['session']->all(),
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    public function test_phone_only_json_otp_still_assigns_global_english_direct_on_sqlite(): void
    {
        $challengeId = $this->issueJsonChallenge('+919876543213')
            ->json('challenge_id');
        self::assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => '+919876543213',
                'code' => '123456',
            ])
            ->assertOk();
        $userId = $verified->json('user.id');
        self::assertIsString($userId);

        self::assertSame(1, DB::table('memberships')
            ->join(
                'organizations',
                'organizations.id',
                '=',
                'memberships.organization_id',
            )
            ->where('memberships.user_id', $userId)
            ->where('organizations.system_key', Organization::DIRECT_SYSTEM_KEY)
            ->count());
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
            'name' => 'SQLite Invite Institution',
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
     * @return TestResponse<Response>
     */
    private function issueJsonChallenge(string $mobileNumber): TestResponse
    {
        return $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => $mobileNumber,
            ])
            ->assertAccepted();
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function adoptSessionCookie(TestResponse $response): void
    {
        $cookie = $response->getCookie((string) config('session.cookie'));

        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
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

final readonly class SqliteInviteOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class SqliteInviteOtpDeliveryAdapter implements OtpDeliveryAdapter
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
