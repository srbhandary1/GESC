<?php

declare(strict_types=1);

return [
    'identity' => [
        'digest_key' => env('AUTH_IDENTITY_DIGEST_KEY'),
    ],

    'web' => [
        'idle_minutes' => (int) env('AUTH_WEB_IDLE_MINUTES', 120),
        'absolute_minutes' => (int) env('AUTH_WEB_ABSOLUTE_MINUTES', 10_080),
    ],

    'privileged' => [
        'idle_minutes' => (int) env('AUTH_PRIVILEGED_IDLE_MINUTES', 30),
        'absolute_minutes' => (int) env('AUTH_PRIVILEGED_ABSOLUTE_MINUTES', 720),
        'mfa_fresh_minutes' => (int) env('AUTH_PRIVILEGED_MFA_FRESH_MINUTES', 15),
        'role_keys' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env(
                'AUTH_PRIVILEGED_ROLE_KEYS',
                'organization_owner,organization_admin,trainer,content_reviewer,content_editor,support'
            )),
        ))),
    ],

    'otp' => [
        'ttl_minutes' => (int) env('AUTH_OTP_TTL_MINUTES', 5),
        'max_attempts' => (int) env('AUTH_OTP_MAX_ATTEMPTS', 5),
        'request_limit_per_destination' => (int) env('AUTH_OTP_REQUEST_LIMIT_PER_DESTINATION', 3),
        'request_limit_minutes' => (int) env('AUTH_OTP_REQUEST_LIMIT_MINUTES', 10),
        'request_limit_per_ip' => (int) env('AUTH_OTP_REQUEST_LIMIT_PER_IP', 10),
        'verify_limit_per_challenge' => (int) env('AUTH_OTP_VERIFY_LIMIT_PER_CHALLENGE', 10),
        'verify_limit_minutes' => (int) env('AUTH_OTP_VERIFY_LIMIT_MINUTES', 10),
        'driver' => env('AUTH_OTP_DRIVER', 'local'),
        'local_fixed_code' => env(
            'AUTH_OTP_LOCAL_FIXED_CODE',
            env('APP_ENV', 'production') === 'local' ? '000000' : null,
        ),
        'local_base_url' => env('OTP_PROVIDER_BASE_URL', 'http://127.0.0.1:8089'),
        'local_timeout_seconds' => (int) env('AUTH_OTP_LOCAL_TIMEOUT_SECONDS', 2),
    ],

    'mobile' => [
        'access_token_minutes' => (int) env('AUTH_MOBILE_ACCESS_TOKEN_MINUTES', 15),
        'family_inactivity_days' => (int) env('AUTH_MOBILE_FAMILY_INACTIVITY_DAYS', 30),
        'family_absolute_days' => (int) env('AUTH_MOBILE_FAMILY_ABSOLUTE_DAYS', 90),
    ],

    'federated' => [
        'driver' => env('AUTH_FEDERATED_DRIVER', 'oidc'),
        'web_return_url' => env('AUTH_FEDERATED_WEB_RETURN_URL', '/'),
        'google' => [
            'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
            'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_OAUTH_REDIRECT_URI'),
        ],
        'facebook' => [
            'client_id' => env('FACEBOOK_OAUTH_CLIENT_ID'),
            'client_secret' => env('FACEBOOK_OAUTH_CLIENT_SECRET'),
            'redirect_uri' => env('FACEBOOK_OAUTH_REDIRECT_URI'),
        ],
    ],
];
