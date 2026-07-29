<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\Mfa\PrivilegedMfaVerifier;
use App\Http\Middleware\EnforceAuthenticationSession;
use App\Http\Middleware\RequireFreshPrivilegedMfa;
use App\Models\AuthenticationSession;
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

    public function test_default_privileged_mfa_verifier_fails_closed(): void
    {
        $user = new User(['status' => User::STATUS_ACTIVE]);

        self::assertFalse(
            $this->app
                ->make(PrivilegedMfaVerifier::class)
                ->verify($user, 'untrusted-assertion'),
        );
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
    ): Request {
        $request = Request::create('/_test/privileged-operation', 'POST');
        $request->attributes->set(
            EnforceAuthenticationSession::REQUEST_ATTRIBUTE,
            (new AuthenticationSession)->forceFill([
                'is_privileged' => true,
                'mfa_verified_at' => $verifiedAt,
            ]),
        );

        return $request;
    }
}
