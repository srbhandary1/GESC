<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AuthenticatedPrincipal;
use App\Authentication\Mfa\PrivilegedMfaVerifier;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Http\Middleware\EnforceAuthenticationSession;
use App\Http\Middleware\RequireFreshPrivilegedMfa;
use App\Http\Middleware\RequireWebAuthentication;
use App\Models\AuthenticationSession;
use App\Models\MobileTokenFamily;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class PrivilegedMfaPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_default_privileged_mfa_verifier_fails_closed_for_invalid_secret(): void
    {
        self::assertNull(
            $this->app
                ->make(PrivilegedMfaVerifier::class)
                ->verify('not-base32', '123456', null),
        );
    }

    public function test_rfc_6238_vector_is_accepted_once_with_the_approved_window(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::createFromTimestampUTC(59),
        );
        $verifier = $this->app->make(PrivilegedMfaVerifier::class);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        self::assertSame(1, $verifier->verify($secret, '287082', null));
        self::assertNull($verifier->verify($secret, '287082', 1));
        self::assertNull($verifier->verify($secret, '287083', null));
    }

    public function test_privileged_session_without_mfa_assertion_is_denied(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');

        $this->assertPrivilegedMfaDenied(null);
    }

    public function test_privileged_session_with_stale_mfa_assertion_is_denied(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');

        $this->assertPrivilegedMfaDenied(now()->subMinutes(16)->toImmutable());
    }

    public function test_privileged_session_with_fresh_mfa_assertion_is_permitted(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');
        $request = $this->requestWithAuthenticationSession(
            now()->subMinutes(14)->toImmutable(),
        );

        $response = $this->app
            ->make(RequireFreshPrivilegedMfa::class)
            ->handle(
                $request,
                static fn (): Response => new Response(status: 204),
            );

        self::assertSame(204, $response->getStatusCode());
    }

    public function test_recovery_assertion_cannot_authorize_privileged_action(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');
        $request = $this->requestWithAuthenticationSession(
            now()->subMinute()->toImmutable(),
            AuthenticationSession::MFA_METHOD_RECOVERY_CODE,
            AuthenticationSession::MFA_SCOPE_FACTOR_REPLACEMENT,
        );

        $this->expectException(AuthorizationException::class);

        $this->app
            ->make(RequireFreshPrivilegedMfa::class)
            ->handle(
                $request,
                static fn (): Response => new Response(status: 204),
            );
    }

    public function test_factor_replacement_rejects_an_invalid_method_scope_pair(): void
    {
        CarbonImmutable::setTestNow('2026-07-30T10:00:00+00:00');
        $request = $this->requestWithAuthenticationSession(
            now()->subMinute()->toImmutable(),
            AuthenticationSession::MFA_METHOD_RECOVERY_CODE,
            AuthenticationSession::MFA_SCOPE_PRIVILEGED_ACTIONS,
        );
        $session = $request->attributes->get(
            EnforceAuthenticationSession::REQUEST_ATTRIBUTE,
        );

        self::assertInstanceOf(AuthenticationSession::class, $session);
        self::assertFalse(
            $this->app
                ->make(AuthenticationSessionService::class)
                ->hasFreshMfaForFactorReplacement($session),
        );
    }

    public function test_passport_principal_is_denied_from_web_only_operation(): void
    {
        $request = Request::create('/_test/operator-operation', 'GET');
        $request->attributes->set(
            AuthenticatedPrincipal::REQUEST_ATTRIBUTE,
            AuthenticatedPrincipal::mobile(
                new User(['status' => User::STATUS_ACTIVE]),
                new MobileTokenFamily,
            ),
        );

        $this->expectException(AuthorizationException::class);

        $this->app->make(RequireWebAuthentication::class)->handle(
            $request,
            static fn (): Response => new Response(status: 204),
        );
    }

    private function assertPrivilegedMfaDenied(?CarbonImmutable $verifiedAt): void
    {
        $this->expectException(AuthorizationException::class);

        $this->app
            ->make(RequireFreshPrivilegedMfa::class)
            ->handle(
                $this->requestWithAuthenticationSession($verifiedAt),
                static fn (): Response => new Response(status: 204),
            );
    }

    private function requestWithAuthenticationSession(
        ?CarbonImmutable $verifiedAt,
        string $method = AuthenticationSession::MFA_METHOD_TOTP,
        string $scope = AuthenticationSession::MFA_SCOPE_PRIVILEGED_ACTIONS,
    ): Request {
        $request = Request::create('/_test/privileged-operation', 'POST');
        $request->attributes->set(
            EnforceAuthenticationSession::REQUEST_ATTRIBUTE,
            (new AuthenticationSession)->forceFill([
                'is_privileged' => true,
                'mfa_verified_at' => $verifiedAt,
                'mfa_factor_id' => $verifiedAt === null
                    ? null
                    : '01J00000000000000000000001',
                'mfa_verified_method' => $verifiedAt === null ? null : $method,
                'mfa_verified_scope' => $verifiedAt === null ? null : $scope,
            ]),
        );

        return $request;
    }
}
