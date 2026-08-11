<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class WebSessionPolicyTest extends TestCase
{
    public function test_production_cookie_and_sanctum_csrf_defaults_are_fail_closed(): void
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$config = require 'config/session.php';
echo json_encode([
    'cookie' => $config['cookie'],
    'secure' => $config['secure'],
    'http_only' => $config['http_only'],
    'encrypt' => $config['encrypt'],
    'same_site' => $config['same_site'],
], JSON_THROW_ON_ERROR);
PHP;
        $process = new Process(
            [PHP_BINARY, '-r', $script],
            base_path(),
            [
                'APP_ENV' => 'production',
                'SESSION_COOKIE' => false,
                'SESSION_SECURE_COOKIE' => false,
                'SESSION_HTTP_ONLY' => false,
                'SESSION_ENCRYPT' => false,
                'SESSION_SAME_SITE' => false,
            ],
        );
        $process->mustRun();

        /** @var array{cookie: string, secure: bool, http_only: bool, encrypt: bool, same_site: string} $production */
        $production = json_decode(
            $process->getOutput(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame('__Host-global_english_session', $production['cookie']);
        self::assertTrue($production['secure']);
        self::assertTrue($production['http_only']);
        self::assertTrue($production['encrypt']);
        self::assertSame('lax', $production['same_site']);
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
