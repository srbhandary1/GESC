<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class WebSessionPolicyTest extends TestCase
{
    public function test_production_cookie_and_sanctum_csrf_defaults_are_fail_closed(): void
    {
        self::assertSame('__Host-global_english_session', config('session.cookie'));
        self::assertTrue(config('session.secure'));
        self::assertTrue(config('session.http_only'));
        self::assertTrue(config('session.encrypt'));
        self::assertSame('lax', config('session.same_site'));
        self::assertContains('localhost:5174', config('sanctum.stateful'));
        self::assertContains('127.0.0.1:5174', config('sanctum.stateful'));
        self::assertSame(
            ValidateCsrfToken::class,
            config('sanctum.middleware.validate_csrf_token'),
        );
        self::assertFalse(Route::has('passport.device'));
    }

    public function test_stateful_otp_mutation_rejects_a_missing_csrf_token(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => '+919876543210',
            ])
            ->assertStatus(419)
            ->assertJsonPath('status', 419);
    }
}
