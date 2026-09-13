<?php

declare(strict_types=1);

return [
    'attempt_retention_days' => 30,
    'client_sample_retention_days' => 30,
    'client_sample_policy_version' => 'speech-first-audible-v1',
    'client_sample_rate_bps' => env(
        'SPEECH_CLIENT_SAMPLE_RATE_BPS',
        env('APP_ENV') === 'production' ? 1000 : 10000,
    ),
    'small_cell_minimum' => 10,
    'maximum_latency_ms' => 120_000,
    'retention_batch_size' => 500,
    'invoice_variance_tolerance_bps' => 500,
    /*
     | Production rate cards are deliberately not application configuration.
     | They require real source evidence and the audited platform-only write
     | path delivered by SPEECH-ADMIN-01. Tests inject synthetic fixtures.
     */
    'published_rate_cards' => [],
];
