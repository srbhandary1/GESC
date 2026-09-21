<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Provisional delayed-review intervals
    |--------------------------------------------------------------------------
    |
    | These defaults require named academic review before production use.
    | Domain services must read this configuration and must not allow an AI
    | response to alter the order or timing of review occurrences.
    |
    */
    'interval_days' => [1, 3, 7, 14],
    'local_due_hour' => 9,
    'version' => 'provisional-v1',

    /*
    | Privacy-gated delayed-review return email. Disabled until an APP
    | records independent privacy/security/tenancy review. The worker never
    | includes transcript, audio or lesson text. Recovery-contact identifiers
    | remain digests, so destination delivery stays unavailable even if the
    | flag is flipped without a reviewed adapter.
    */
    'return_email' => [
        'enabled' => env('DELAYED_REVIEW_EMAIL_ENABLED', false),
        'approval' => env('DELAYED_REVIEW_EMAIL_APPROVAL', ''),
        'home_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/#home',
    ],
];
