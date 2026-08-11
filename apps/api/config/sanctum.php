<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

$configuredStatefulDomains = explode(',', (string) env(
    'SANCTUM_STATEFUL_DOMAINS',
    sprintf(
        '%s%s',
        'localhost,localhost:3000,localhost:5174,127.0.0.1,127.0.0.1:5174,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ),
));
$localStatefulDomains = env('APP_ENV', 'production') === 'local'
    ? ['localhost:5174', '127.0.0.1:5174']
    : [];

return [
    'stateful' => array_values(array_unique([
        ...$configuredStatefulDomains,
        ...$localStatefulDomains,
    ])),

    'guard' => ['web'],

    // Browser bearer tokens are intentionally not issued. Web/PWA uses the
    // stateful web guard and its Secure, HttpOnly session cookie.
    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
